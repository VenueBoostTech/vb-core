<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Mail\PrivacyRightsRequestsVerifyEmail;
use App\Models\PrivacyRightsRequest;
use App\Services\MondayAutomationsService;
use Carbon\Carbon;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use function response;

/**
 * @OA\Info(
 *   title="Legal API",
 *   version="1.0",
 *   description="This API allows users to retrieve and manage Legal Requests.",
 * )
 */

/**
 * @OA\Tag(
 *   name="Legal API",
 *   description="Operations related to Legal Requests"
 * )
 */


class LegalController extends Controller
{

    protected $mondayAutomationService;

    public function __construct(MondayAutomationsService $mondayAutomationService)
    {
        $this->mondayAutomationService = $mondayAutomationService;
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'privacy_request' => 'required | string',
            'request_contact_email' => 'required | email',
            'request_contact_phone' => 'required | string',
            'request_contact_name' => 'required | string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }


        $privacyRightsRequest = new PrivacyRightsRequest();
        $privacyRightsRequest->privacy_request = $request->input('privacy_request');
        $privacyRightsRequest->request_contact_email = $request->input('request_contact_email');
        $privacyRightsRequest->request_contact_phone = $request->input('request_contact_phone');
        $privacyRightsRequest->request_contact_name = $request->input('request_contact_name');
        $privacyRightsRequest->save();

        $created_at = Carbon::now();
        $expired_at = $created_at->addMinutes(240); // Add 240mins
        $serverName = 'VenueBoost';

        $data = [
            // 'iat' => $created_at->timestamp, // Issued at: time when the token was generated
            // 'nbf' => $created_at->timestamp, // Not before
            'iss' => $serverName, // Issuer
            'exp' => $expired_at->timestamp, // Expire,
            'id' => $privacyRightsRequest->id,
        ];

        $jwt_token = JWT::encode($data, env('JWT_SECRET'), 'HS256');
        $email_verify_link = 'https://venueboost.io' . "/privacy-rights-request-confirm/$jwt_token";

        Mail::to($privacyRightsRequest->request_contact_email)->send(new PrivacyRightsRequestsVerifyEmail($privacyRightsRequest->request_contact_name, $email_verify_link));


        try {
            $this->mondayAutomationService->privacyRightRequestCreation($privacyRightsRequest);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            // do nothing
        }


        return response()->json(['message' => 'Privacy Rights Requests Form submitted successfully'], 201);
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
                return response()->json(['message' => 'Invalid onboarding link'], 400);
            }

            $privacyRightsRequest = PrivacyRightsRequest::where('id', $id)->first();
            if (!$privacyRightsRequest) {
                return response()->json(['message' => 'Invalid privacy rights request link'], 404);
            }

            $privacyRightsRequest->email_verified_at = Carbon::now();

            $privacyRightsRequest->save();


            return response()->json([
                'message' => 'Valid link'
            ], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


}
