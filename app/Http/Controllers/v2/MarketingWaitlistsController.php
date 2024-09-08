<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Mail\CampaignEmail;
use App\Mail\OnboardingVerifyEmail;
use App\Mail\WaitlistEmail;
use App\Mail\WaitlistVerifyLinkEmail;
use App\Models\ApiApp;
use App\Models\MarketingWaitlist;
use App\Models\PromoCodeType;
use App\Models\PromotionalCode;
use App\Models\PromotionalCodePhoto;
use App\Models\WcIntegration;
use App\Services\MondayAutomationsService;
use App\Services\VenueService;
use Carbon\Carbon;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MarketingWaitlistsController extends Controller
{

    protected $mondayAutomationService;

    public function __construct(MondayAutomationsService $mondayAutomationService)
    {
        $this->mondayAutomationService = $mondayAutomationService;
    }

    public function create(Request $request): \Illuminate\Http\JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:marketing_waitlists,email',
            // 'waitlist_type' => 'in:pre_launch,launch',
            'venue_name' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'country_code' => 'nullable|string',
            'full_name' => 'nullable|string',
            'promo_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $waitlister = MarketingWaitlist::create($request->all());


        $created_at = Carbon::now();
        $expired_at = $created_at->addMinutes(15); // Add 15mins
        $serverName = 'VenueBoost';

        $data = [
            // 'iat' => $created_at->timestamp, // Issued at: time when the token was generated
            // 'nbf' => $created_at->timestamp, // Not before
            'iss' => $serverName, // Issuer
            'exp' => $expired_at->timestamp, // Expire,
            'id' => $waitlister->id,
        ];

        $jwt_token = JWT::encode($data, env('JWT_SECRET'), 'HS256');
        $email_verify_link = 'https://venueboost.io' . "/waitlist/$jwt_token";

        Mail::to($request->email)->send(new WaitlistVerifyLinkEmail($request->full_name ?? null, $email_verify_link, false));

        try {
            $this->mondayAutomationService->automateWaitlistCreation($waitlister);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            // do nothing
        }

        return response()->json(['message' => 'Waitlist created successfully']);

    }

    public function verifyEmailLink(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $token = $request->input('token');

            $id = null;
            try {
                $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
                $id = $decoded->id;
            } catch (ExpiredException|\Exception $expiredException) {
                return response()->json(['message' => 'Invalid waitlist link'], 400);
            }

            $waitlister = MarketingWaitlist::where('id', $id)->first();
            if (!$waitlister) {
                return response()->json(['message' => 'Invalid waitlist email link'], 404);
            }

            // if waitlister first_email_sent is false send email
            if ($waitlister->first_email_sent === 0) {
                Mail::to( $waitlister->email)->send(new WaitlistEmail($request->full_name ?? null));
                $waitlister->first_email_sent = 1;
                $waitlister->save();
            }

            return response()->json(['message' => 'Valid link', 'waitlister' => $waitlister->email], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function generateEmailLink(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        try {
            $email = $request->input('email');


            $waitlister = DB::table('marketing_waitlists')->where('email', $email)->first();
            if (!$waitlister) {
                return response()->json(['message' => "No waitlist entry found for the provided email. Please ensure you've entered the correct email address or sign up if you haven't joined the waitlist yet."], 404);
            }

            $created_at = Carbon::now();
            $expired_at = $created_at->addMinutes(15); // Add 15mins
            $serverName = 'VenueBoost';

            $data = [
                // 'iat' => $created_at->timestamp, // Issued at: time when the token was generated
                // 'nbf' => $created_at->timestamp, // Not before
                'iss' => $serverName, // Issuer
                'exp' => $expired_at->timestamp, // Expire,
                'id' => $waitlister->id,
            ];

            $jwt_token = JWT::encode($data, env('JWT_SECRET'), 'HS256');
            $email_verify_link = 'https://venueboost.io' . "/waitlist/$jwt_token";

            Mail::to($email)->send(new WaitlistVerifyLinkEmail($waitlister->full_name ?? null, $email_verify_link, true));

            return response()->json(['message' => 'Waitlist link generated successfully']);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function listForAutomation(): \Illuminate\Http\JsonResponse
    {
        $waitlists = MarketingWaitlist::orderBy('created_at', 'desc')->get();


        return response()->json(['waitlists' => $waitlists]);
    }

    public function listForSuperadmin(): \Illuminate\Http\JsonResponse
    {
        // sort by created_at desc
        $waitlists = MarketingWaitlist::orderBy('created_at', 'desc')->get();

        // format created_at for each waitlist
        $formattedWaitlists = $waitlists->map(function ($waitlist) {
            $waitlist->created_at_formatted = $waitlist->created_at->format('Y-m-d H:i:s');
            return $waitlist;
        });


        return response()->json(['waitlists' => $formattedWaitlists]);
    }

}
