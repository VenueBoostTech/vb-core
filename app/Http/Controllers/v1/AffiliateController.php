<?php
namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Mail\AffiliateConfirmedEmail;
use App\Models\Affiliate;
use App\Models\AffiliatePlan;
use App\Models\AffiliateProgram;
use App\Models\AffiliateType;
use App\Models\MarketingLink;
use App\Models\User;
use App\Services\MondayAutomationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use function response;

/**
 * @OA\Info(
 *   title="AffiliateController API",
 *   version="1.0",
 *   description="This API allows use Affiliate Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Affiliate",
 *   description="Operations related to Affiliate"
 * )
 */


class AffiliateController extends Controller
{
    protected $mondayAutomationService;

    public function __construct(MondayAutomationsService $mondayAutomationService)
    {
        $this->mondayAutomationService = $mondayAutomationService;
    }

    public function listAffiliatePrograms(): JsonResponse
    {
        $affiliatePrograms = AffiliateProgram::whereNotNull('ap_unique')
            ->get();

        $result = $affiliatePrograms->map(function ($affiliateProgram) {
            return [
                'id' => $affiliateProgram->id,
                'name' => $affiliateProgram->name,
                'description' => $affiliateProgram->description,
                'ap_unique' => $affiliateProgram->ap_unique,
            ];
        });

        return response()->json(['affiliate_programs' => $result], 200);
    }

    public function venueAffiliateApply(Request $request): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // get user from venue

        $user = $venue->user;
        // create affiliate with user data and assign user_id to affiliate

        $affiliate = Affiliate::create([
            'first_name' => explode(' ', $user->name)[0],
            'last_name' => explode(' ', $user->name)[1],
            'website' => $user->website,
            'country' => $user->country_code,
            'registered_type' => 'venue',
            'user_id' => $user->id,
        ]);

        return response()->json(['message' => 'Affiliate created successfully for venue', 'data' => $affiliate], 201);

    }

    public function listAffiliatesByProgramId($programId): JsonResponse
    {
        // Find the affiliate program by ID
        $affiliateProgram = AffiliateProgram::find($programId);

        if (!$affiliateProgram) {
            return response()->json(['error' => 'Affiliate program not found'], 404);
        }

        // Get affiliates associated with the program, including their user relationship
        $affiliates = $affiliateProgram->affiliates()->with('user')->get();

        return response()->json(['data' => $affiliates]);
    }

    public function listAffiliatesByTypeId($typeId): JsonResponse
    {
        // Find the affiliate type by ID
        $affiliateType = AffiliateType::find($typeId);

        if (!$typeId) {
            return response()->json(['error' => 'Affiliate type not found'], 404);
        }

        // Get affiliates associated with the type, including their user relationship
        $affiliates = $affiliateType->affiliates()->with('user')->get();

        return response()->json(['data' => $affiliates]);
    }

    public function approveOrDeclineAffiliate(Request $request, $affiliateId): JsonResponse
    {
        // Find the affiliate by ID
        $affiliate = Affiliate::find($affiliateId);

        if (!$affiliate) {
            return response()->json(['error' => 'Affiliate not found'], 404);
        }

        if ($request->has('action')) {
            // Check if the action is valid (approve or decline)
            if (!in_array($request->input('action'), ['approve', 'decline'])) {
                return response()->json(['error' => 'Invalid action'], 400);
            }


            // If the action is to approve the affiliate, generate and set the unique code
            if ($request->input('action') == 'approve') {
                // Generate a unique code based on the affiliate's first name
                $randomChars = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 2);
                $uniqueCode = strtoupper(substr($affiliate->first_name, 0, 2) . mt_rand(100000, 999999) . $randomChars);

                // Assign the unique code
                $affiliate->affiliate_code = $uniqueCode;

                $user = User::find($affiliate->user_id);
                $user->email_verified_at = now();
                $user->save();
            }

            // Update the affiliate's status based on the action
            $affiliate->status = ($request->input('action') == 'approve') ? 'approved' : 'declined';


            $affiliate->save();

            if ($request->input('action') == 'approve') {
                // Send an email to the affiliate to notify them that their application has been approved
                Mail::to($affiliate->user->email)->send(new AffiliateConfirmedEmail($affiliate->first_name));
            }



            // Check if a link exists for the referral code
            $link = MarketingLink::where('affiliate_code', $affiliate->affiliate_code)->where('type', 'affiliate')->first();

            if (!$link) {

                // Create a new link using Rebrandly API
                $response = Http::withHeaders([
                    'apikey' => env('REBRANDLY_API_KEY'),
                    'Content-Type' => 'application/json'
                ])->post(env('REBRANDLY_API_URL'), [
                    'title' => 'Affiliate Link from affiliate' . $affiliate->first_name . ' ' . $affiliate->last_name,
                    // add timestamp to the end of the referral code to make it unique
                    // generate random slashtag
                    'slashtag' => time() . '' . Controller::generateRandomString(8),
                    'destination' => 'https://venueboost.io/affiliate-campaign?affiliate_code=' . $affiliate->affiliate_code,
                    'domain' => [
                        'id' => env('REBRANDLY_DOMAIN_ID'),
                        'fullName' => env('REBRANDLY_DOMAIN_NAME')
                    ]
                ]);
                if ($response->successful()) {
                    $responseData = $response->json();
                    $link = new MarketingLink();
                    $link->affiliate_code = $affiliate->affiliate_code;
                    $link->short_url = $responseData['shortUrl'];
                    $link->type = 'affiliate';
                    $link->save();
                } else {
                    return response()->json(['message' => 'Problem generating affiliate link'], 500);
                }

            }

            $existingAffiliate = Affiliate::with(['user','affiliateType'])->find($affiliate->id);
            try {
                $this->mondayAutomationService->affiliateManage($existingAffiliate, 'update');
            } catch (\Exception $e) {
                \Sentry\captureException($e);
                // do nothing
            }

            return response()->json(['message' => 'Affiliate ' . $request->input('action') . 'd successfully', 'data' => $affiliate]);
        }
        else {
            return response()->json(['error' => 'Action is required'], 400);
        }


    }

    public function createAffiliateProgram(Request $request): JsonResponse
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Create the affiliate program
        $affiliateProgram = AffiliateProgram::create([
            'name' => $request->name,
            'description' => $request->description,
            'commission_fee' => 0,
            'ap_unique' => $this->generateUniqueAffiliateCode($request->name),
            'status' => true,
        ]);

        if ($affiliateProgram) {
            return response()->json(['message' => 'Affiliate program created successfully', 'data' => $affiliateProgram], 201);
        }

        return response()->json(['error' => 'Failed to create affiliate program'], 500);
    }

    public function toggleAffiliateProgramStatus(Request $request, $programId): JsonResponse
    {
        // Find the affiliate program by ID
        $affiliateProgram = AffiliateProgram::find($programId);

        if (!$affiliateProgram) {
            return response()->json(['error' => 'Affiliate program not found'], 404);
        }

        // Check if the request contains 'status' parameter (true for activate, false for deactivate)
        if ($request->has('status')) {
            $status = $request->input('status');
            $affiliateProgram->status = $status;
            $affiliateProgram->save();

            $message = $status ? 'Affiliate program activated' : 'Affiliate program deactivated';
            return response()->json(['message' => $message, 'data' => $affiliateProgram]);
        } else {
            return response()->json(['error' => 'Please provide the status parameter (true/false)'], 400);
        }
    }

    public function connectAffiliateWithProgram(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'affiliate_id' => 'required|exists:affiliates,id',
            'affiliate_program_id' => 'required|exists:affiliate_programs,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $existingConnection = DB::table('affiliate_affiliate_program')
            ->where('affiliate_id', $request->affiliate_id)
            ->where('affiliate_program_id', $request->affiliate_program_id)
            ->first();

        if ($existingConnection) {
            DB::table('affiliate_affiliate_program')
                ->where('id', $existingConnection->id)
                ->update(['updated_at' => now()]);
        } else {
            DB::table('affiliate_affiliate_program')->insert([
                'affiliate_id' => $request->affiliate_id,
                'affiliate_program_id' => $request->affiliate_program_id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return response()->json(['message' => 'Affiliate connection updated successfully.'], 200);
    }

    public function registerAffiliate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'country' => 'required|string',
            'website' => 'nullable|string',
            'password' => 'required|string',
            'affiliate_type_id' => 'nullable|exists:affiliate_types,id', // Add validation for affiliate type ID
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // create user first
        $user = User::create([
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'country_code' => 'US',
        ]);

        if (!$user) {
            return response()->json(['error' => 'User not created'], 400);
        }


        // optional add also affiliate type
        $affiliate = Affiliate::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'country' => $request->country,
            'website' => $request->website,
            'user_id' => $user->id,
            'affiliate_type_id' => $request->affiliate_type_id,
        ]);

        $createdAffiliate = Affiliate::with(['user','affiliateType'])->find($affiliate->id);
        try {
            $this->mondayAutomationService->affiliateManage($createdAffiliate, 'create');
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            // do nothing
        }

        return response()->json(['message' => 'Affiliate created successfully', 'data' => $affiliate], 201);

    }

    public function updateAffiliateProgram(Request $request, $programId): JsonResponse
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Find the affiliate program by ID
        $affiliateProgram = AffiliateProgram::find($programId);

        if (!$affiliateProgram) {
            return response()->json(['error' => 'Affiliate program not found'], 404);
        }

        // Update the affiliate program with the provided data
        if ($request->has('name')) {
            $affiliateProgram->name = $request->name;
        }
        if ($request->has('description')) {
            $affiliateProgram->description = $request->description;
        }

        // Save the changes
        $affiliateProgram->save();

        return response()->json(['message' => 'Affiliate program updated successfully', 'data' => $affiliateProgram], 200);
    }



    public function createAffiliatePlan(Request $request): JsonResponse
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'plan_name' => 'required|string',
            'affiliate_id' => 'required|exists:affiliates,id',
            'percentage' => 'required_if:preferred_method,Fixed Percentage|nullable|numeric',
            'fixed_value' => 'required_if:preferred_method,Fixed Amount|nullable|numeric',
            'nr_of_months' => 'nullable|integer',
            'custom_plan_amount' => 'nullable|numeric',
            'plan_id' => 'nullable|exists:pricing_plans,id',
            'preferred_method' => 'required|string|in:Fixed Percentage,Fixed Amount,Sliding Scale',
            'lifetime' => 'required|boolean',
            'customer_interval_start' => 'required_if:preferred_method,Sliding Scale|nullable|integer',
            'customer_interval_end' => 'required_with:customer_interval_start|nullable|integer',
        ]);


        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid input data', 'errors' => $validator->errors()], 400);
        }

        // Find the affiliate and their associated affiliate type
        $affiliate = Affiliate::with('programs')->find($request->affiliate_id);

        if (!$affiliate) {
            return response()->json(['message' => 'Invalid affiliate ID'], 400);
        }


        // check if affiliate has affiliate program get the id
        if ($affiliate->programs->count() > 0) {
            $affiliateProgramId = $affiliate->programs->first()->id;
        } else {
            return response()->json(['message' => 'Affiliate has no affiliate program'], 400);
        }

        // Combinations can be
        $affiliatePlan = new AffiliatePlan([
            'plan_name' => $request->plan_name,
            'affiliate_id' => $affiliateProgramId,
            'affiliate_program_id' => $affiliate->id,
            'percentage' => $request->percentage,
            'fixed_value' => $request->fixed_value,
            'nr_of_months' => $request->nr_of_months,
            'custom_plan_amount' => $request->custom_plan_amount,
            'plan_id' => $request->plan_id,
            'preferred_method' => $request->preferred_method,
            'lifetime' => $request->lifetime,
            'customer_interval_start' => $request->customer_interval_start,
            'customer_interval_end' => $request->customer_interval_end,
        ]);

        $affiliatePlan->save();

        return response()->json(['message' => 'Affiliate plan created successfully'], 201);
    }


    public function getAffiliateTypesWithPrograms(): JsonResponse
    {
        // Get all affiliate types with their associated programs
        $affiliateTypes = AffiliateType::select('id', 'name', 'description', 'category')
            ->get();

        // Return the data as JSON
        return response()->json(['affiliate_types' => $affiliateTypes], 200);
    }

    private function generateUniqueAffiliateCode($name) {
        // Generate a unique affiliate code based on the name or any other criteria
        // For example, you can create a code by combining the first letters of the words in the name and a random string.

        // Convert the name to uppercase and remove any non-alphanumeric characters
        $cleanedName = preg_replace('/[^a-zA-Z0-9]/', '', strtoupper($name));

        // Generate a random string, e.g., using random_bytes or any other method
        $randomString = bin2hex(random_bytes(4));

        // Combine the cleaned name and random string to create a unique code
        $uniqueCode = 'AP-' . $cleanedName . '-' . $randomString;

        return $uniqueCode;
    }

}


