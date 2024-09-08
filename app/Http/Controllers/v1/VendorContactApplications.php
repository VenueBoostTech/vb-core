<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Mail\VenueDemoApprovedEmail;
use App\Models\Affiliate;
use App\Models\Restaurant;
use App\Models\SubscribedEmail;
use App\Services\MondayAutomationsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\VendorRegisterEmail;
use Carbon\Carbon;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
/**
 * @OA\Info(
 *   title="Vendor Contact Applications API",
 *   version="1.0",
 *   description="This API allows use to manage Vendor Contact Applications",
 * )
 */

/**
 * @OA\Tag(
 *   name="VendorContactApplications",
 *   description="Operations related to Vendor Contact Applications"
 * )
 */

class VendorContactApplications extends Controller
{

    protected $mondayAutomationService;

    public function __construct(MondayAutomationsService $mondayAutomationService)
    {
        $this->mondayAutomationService = $mondayAutomationService;
    }

    /**
     * @OA\Get(
     *     path="/vendor-contact-applications",
     *     summary="Get vendor contact applications",
     *     tags={"VendorContactApplications"},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search keyword",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contacts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Success message"
     *             ),
     *             @OA\Property(
     *                 property="contact_sales",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(
     *                         property="id",
     *                         type="integer",
     *                         description="Contact ID"
     *                     ),
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     )
     * )
     */
    public function get(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $search = $request->input('search');

//            $contact_sales = DB::table('contact_sales');
            $contact_sales = DB::table('contact_sales')->whereNotIn('contact_sales.id', [6, 7]);

            if ($search) {
                $contact_sales = $contact_sales->whereRaw('LOWER(restaurant_name) LIKE ?', ["%" . strtolower($search) . "%"]);
            }

            $contact_sales = $contact_sales
                ->leftJoin('restaurants', 'contact_sales.id', '=', 'restaurants.contact_id')
                ->orderBy('contact_sales.created_at', 'DESC')
                ->groupBy('contact_sales.id')
                ->select('contact_sales.*', DB::raw('GROUP_CONCAT(restaurants.id) AS restaurant_ids'))
                ->get()
                ->map(function ($contact_sale) {
                    $restaurant_ids = $contact_sale->restaurant_ids;
                    $contact_sale->restaurant_ids = $restaurant_ids !== "" ? explode(',', $restaurant_ids) : [];
                    return $contact_sale;
                })
                ->values();

            return response()->json(['message' => 'Vendor Contact Applications has been retrieve successfully', 'contact_sales' => $contact_sales], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/vendor-contact-applications/{id}",
     *     summary="Get a single vendor contact application",
     *     tags={"VendorContactApplications"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Contact ID",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contact retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="contact_sale",
     *                 type="object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     description="Contact ID"
     *                 ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Contact not found",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Not found message"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     )
     * )
     */
    public function getOne($id): \Illuminate\Http\JsonResponse
    {
        try {
            $contact_sale = DB::table('contact_sales')->where('id', $id)->first();
            if (!$contact_sale) {
                return response()->json(['message' => 'Not found'], 404);
            }
            return response()->json(['contact_sale' => $contact_sale], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/vendor-contact-applications",
     *     summary="Create a new vendor contact application",
     *     tags={"VendorContactApplications"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="first_name",
     *                 type="string",
     *                 description="First name",
     *             ),
     *             @OA\Property(
     *                 property="last_name",
     *                 type="string",
     *                 description="Last name",
     *             ),
     *             @OA\Property(
     *                 property="mobile",
     *                 type="string",
     *                 nullable=true,
     *                 description="Mobile number",
     *             ),
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 description="Email",
     *             ),
     *             @OA\Property(
     *                 property="restaurant_name",
     *                 type="string",
     *                 description="Restaurant name",
     *             ),
     *             @OA\Property(
     *                 property="restaurant_city",
     *                 type="string",
     *                 description="Restaurant city",
     *             ),
     *             @OA\Property(
     *                 property="restaurant_state",
     *                 type="string",
     *                 description="Restaurant state",
     *             ),
     *             @OA\Property(
     *                 property="restaurant_zipcode",
     *                 type="string",
     *                 description="Restaurant zipcode",
     *             ),
     *             @OA\Property(
     *                 property="restaurant_country",
     *                 type="string",
     *                 description="Restaurant country",
     *             ),
     *             @OA\Property(
     *                 property="contact_reason",
     *                 type="string",
     *                 description="Contact reason",
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contact created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Success message",
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Validation error message",
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message",
     *             )
     *         )
     *     )
     * )
     */
    public function create(Request $request): \Illuminate\Http\JsonResponse
    {

        $messages = [
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'mobile.required' => 'Mobile number is required',
//            'email.required' => 'Email number is required',
            'restaurant_name.required' => 'Venue name is required',
            'restaurant_city.required' => 'Venue city is required',
            'restaurant_state.required' => 'Venue state is required',
            'restaurant_zipcode.required' => 'Venue postal code is required',
            'restaurant_country.required' => 'Country field cannot be empty',
            'industry.required' => 'Industry field cannot be empty',
            'category.required' => 'Category field cannot be empty',
            'contact_reason.required' => 'Reason for contact field cannot be empty',
            'number_of_employees.required' => 'Number of employees is required',
            'annual_revenue.required' => 'Annual revenue is required',
            'social_media.required' => 'Social media information is required',
            'business_challenge.required' => 'Business challenge is required',
            'how_did_you_hear_about_us.required' => 'Please specify how you heard about us',
            'years_in_business.required' => 'Years in business is required'
        ];

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'mobile' => 'string',
            'email' => 'required|string|email',
            'restaurant_name' => 'required|string',
            'restaurant_city' => 'required|string',
            'restaurant_state' => 'required|string',
            'restaurant_zipcode' => 'required|string',
            'restaurant_country' => 'required|string',
            'industry' => 'required|string',
            'category' => 'required|string',
            'contact_reason' => 'required|string',
            'is_demo' => 'nullable|boolean',
            'number_of_employees' => 'required|string',
            'annual_revenue' => 'required|string',
            'website' => 'nullable|string',
            'social_media' => 'required|array',
            'business_challenge' => 'required|string',
            'how_did_you_hear_about_us' => 'required|string',
            'years_in_business' => 'required|integer',
            'biggest_additional_change' => 'nullable|string',
        ], $messages);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $contacts = $request->only(
                'first_name', 'last_name', 'mobile', 'email',
                'restaurant_name', 'restaurant_city', 'restaurant_state',
                'restaurant_zipcode', 'restaurant_country', 'contact_reason', 'is_demo', 'industry', 'category',
                'number_of_employees', 'annual_revenue',
                'website', 'business_challenge',
                'other_business_challenge', 'how_did_you_hear_about_us', 'how_did_you_hear_about_us_other',
                'years_in_business', 'biggest_additional_change'
            );

            if ($request->has('social_media')) {
                $socialMediaInput = $request->input('social_media');
                $contacts['social_media'] = is_string($socialMediaInput) ? $socialMediaInput : json_encode($socialMediaInput);
            }


            $contacts['status'] = 'pending';
            $contacts['created_at'] = date('Y-m-d H:i:s');
            $contacts['updated_at'] = date('Y-m-d H:i:s');

            // Check if affiliate_code exists and get affiliate_id...
            if ($request->has('affiliate_code')) {
                $affiliate = Affiliate::where('affiliate_code', $request->input('affiliate_code'))->first();
                if ($affiliate) {
                    $contacts['affiliate_id'] = $affiliate->id;
                    $contacts['affiliate_status'] = 'pending';
                    $contacts['affiliate_code'] = $request->input('affiliate_code');
                }
            }

            // Check if referral_code exists and get referer_id...
            if ($request->has('referral_code')) {
                $referralCode = Restaurant::where('referral_code', $request->input('referral_code'))->first();

                if ($referralCode) {
                    $num_invites = DB::table('restaurant_referrals')->where('restaurant_id', $referralCode->id)->count();
                    if ($num_invites < Controller::$LIMIT_NUM_REFERRAL) {
                        $contacts['referer_id'] = $referralCode->id;
                        $contacts['referral_status'] = 'pending';
                        $contacts['referral_code'] = $request->input('referral_code');
                    }
                }
            }

            $contactId = DB::table('contact_sales')->insertGetId($contacts);

            // check if it has subscribed email

            // check if this email is already on contact form submission
            $canSubscribe = false;
            $subscribedEmail = SubscribedEmail::where('email', $request->input('email'))->first();
            if (!$subscribedEmail) {
                $canSubscribe = true;
            }


            $storeAutomationSubscribed = false;

            if ($request->has('subscribe') ) {
                $wantsToSubscribe = $request->input('subscribe');

                if ($wantsToSubscribe && $canSubscribe) {
                    $storeAutomationSubscribed = true;
                    SubscribedEmail::create(
                        [
                            'email' => $request->input('email'),
                            'contact_sales_id' => $contactId,

                        ]
                    );

                }

            }


            $savedContact = DB::table('contact_sales')->where('id', $contactId)->first();

            try {
                $this->mondayAutomationService->automateContactSalesCreation($savedContact, $storeAutomationSubscribed);
            } catch (\Exception $e) {
                \Sentry\captureException($e);
                 // do nothing
            }

            return response()->json(['message' => 'Thank you for submitting your request to join VenueBoost! We have received your information and will review it shortly. We will notify you regarding the status of your request through phone or email.'], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/vendor-contact-applications/{id}",
     *     summary="Update a vendor contact application",
     *     tags={"VendorContactApplications"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the contact",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"approved", "declined"},
     *                 description="Status of the contact",
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contact updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Success message",
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Validation error message",
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Contact not found",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message",
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message",
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,declined',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $contact_sale = DB::table('contact_sales')->where('id', $id)->first();
            if (!$contact_sale) {
                return response()->json(['message' => 'Not found'], 404);
            }

            $status = $request->input('status');
            DB::table('contact_sales')->where('id', $id)->update([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($status == 'approved') {
                // Check if there's a non-nullable affiliate code
                if (!is_null($contact_sale->affiliate_code)) {
                    // Update the status to 'started'
                    DB::table('contact_sales')->where('id', $id)->update(['affiliate_status' => 'started']);
                }

                // Check if there's a non-nullable referral code
                if (!is_null($contact_sale->referral_code)) {
                    // Update the status to 'started'
                    DB::table('contact_sales')->where('id', $id)->update(['referral_status' => 'started']);
                }
                if ($contact_sale->is_demo) {
                    Mail::to($contact_sale->email)->send(new VenueDemoApprovedEmail($contact_sale->restaurant_name));
                }
                else {
                    $created_at = Carbon::now();
                    $expired_at = $created_at->addWeeks(1); // Add 1 week
                    $serverName ='VenueBoost';

                    $data = [
                        // 'iat' => $created_at->timestamp, // Issued at: time when the token was generated
                        // 'nbf' => $created_at->timestamp, // Not before
                        'iss' => $serverName, // Issuer
                        'exp' => $expired_at->timestamp, // Expire,
                        'id' => $contact_sale->id,
                    ];

                    $jwt_token = JWT::encode($data, env('JWT_SECRET'), 'HS256');
                    $register_link = 'https://venueboost.io' . "/$jwt_token" . "/apply/complete-registration" ;
                    Mail::to($contact_sale->email)->send(new VendorRegisterEmail($contact_sale->restaurant_name, $register_link));
                }

            }
            return response()->json(['message' => 'Vendor application has been updated successfully'], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/vendor-contact-applications/verify-register-link",
     *     summary="Verify registration link",
     *     tags={"VendorContactApplications"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="token",
     *                 type="string",
     *                 description="Registration token",
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Registration link is valid",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Success message",
     *             ),
     *             @OA\Property(
     *                 property="contact_sale",
     *                 type="object",
     *                 description="Contact sale data",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     description="Contact sale ID",
     *                 ),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Validation error message",
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invalid registration link",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message",
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message",
     *             ),
     *         ),
     *     ),
     * )
     */
    public function verifyRegisterLink(Request $request): \Illuminate\Http\JsonResponse
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
                return response()->json(['message' => 'Invalid register link'], 400);
            }

            $contact_sale = DB::table('contact_sales')->where('id', $id)->where('status', 'approved')->first();
            if (!$contact_sale) {
                return response()->json(['message' => 'Invalid register link'], 404);
            }

            $restaurants = DB::table('restaurants')->where('contact_id', $id)->first();
            if (!$restaurants) {
                return response()->json(['message' => 'Valid link', 'contact_sale' => $contact_sale], 200);
            }
            else {
                if ($restaurants->status == 'completed') {
                    return response()->json(['message' => 'Invalid register link, You are already registered'], 400);
                }
            }

            return response()->json(['message' => 'Valid link', 'restaurants' => $restaurants], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

}
