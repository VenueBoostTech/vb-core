<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\AffiliatePlan;
use App\Models\AffiliateWallet;
use App\Models\AffiliateWalletHistory;
use App\Models\Customer;
use App\Models\FirebaseUserToken;
use App\Models\HotelRestaurant;
use App\Models\LoginActivity;
use App\Models\MarketingLink;
use App\Models\Restaurant;
use App\Models\RestaurantConfiguration;
use App\Models\Subscription;
use App\Models\VenueCustomizedExperience;
use App\Models\VenueIndustry;
use App\Models\VenuePauseHistory;
use App\Models\VenueType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailChangeVerifyEmail;
use App\Mail\UserVerifyEmail;
use App\Mail\ByBestShopUserVerifyEmail;
use JWTAuth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use App\Models\User;
use Carbon\Carbon;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use stdClass;

/**
 * @OA\Info(
 *   title="Authentication API",
 *   version="1.0",
 *   description="This API allows authentication to the application
 * )
 */

/**
 * @OA\Tag(
 *   name="Authentication",
 *   description="Operations related to Authentication"
 * )
 */

use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class AuthController extends Controller
{

    /**
     * @OA\Post(
     *     path="/login",
     *     operationId="login",
     *     tags={"Authentication"},
     *     summary="Login to the application (SN Boost)",
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="password", type="string"),
     *         )
     *     ),
     *     @OA\Response(response="200", description="Success"),
     *     @OA\Response(response="422", description="Validation Error")
     * )
     */
    public function authenticate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'source_app' => 'nullable|string|in:sales-associate,event,flow-master,inventory,metri-coach,pos,staff',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $credentials = $request->only('email', 'password');
        $sourceApp = $request->input('source_app');
        $visionTrack = false;
        $allow_clockinout = false;

        if ($credentials['email'] === 'ggerveni+teamleader@gmail.com' && $credentials['password'] === 'Test12345!') {
            $allow_clockinout = true;
        }

        // Check for the specific email and password combination
        if ($credentials['email'] === 'vt-test-camera@venueboost.io' && $credentials['password'] === 'VB2232!$-') {
            // Attempt to authenticate as 'bybestapartments@gmail.com' with 'Test12345!'
            $credentials = ['email' => 'bybestapartments@gmail.com', 'password' => 'Test12345!'];
            $visionTrack = true;
        }


        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Email or password is incorrect. Please try again.'], 401);
        }

        return $this->respondWithToken($token, $sourceApp, $visionTrack, $allow_clockinout);
    }


    public function authenticateAffiliate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Email or password is incorrect. Please try again.'], 401);
        }

        return $this->respondWithTokenForAffiliate($token);
    }

    public function authenticateSuperadmin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Email or password is incorrect. Please try again.'], 401);
        }

        // Check if the user has a high-level role with the name 'Superadmin'
        $user = auth()->user();
        $superadminRole = 'Superadmin';

        if ($user->role->name !== $superadminRole) {
            return response()->json(['error' => 'You do not have the required role to access this.'], 403);
        }

        return $this->respondWithTokenForSuperadmin($token);
    }


    public function authenticateEndUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'source' => 'required|string',
            'venue_id' => 'nullable|integer,exists:restaurants,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Email or password is incorrect. Please try again.'], 401);
        }

//        Save Login Activity
        LoginActivity::create([
            'user_id' => auth()->user()->id,
            'app_source' => $request->source,
            'venue_id' => $request->venue_id,
        ]);

        return $this->respondWithTokenForEndUser($token, $request->source);
    }

    /**
     * Get the token array structure.
     *
     * @param string $token
     *
     * @return JsonResponse
     */
    protected function respondWithToken(string $token, ?string $sourceApp = null, $visionTrack, $allow_clockinout): JsonResponse
    {
        $ttl = auth()->guard('api')->factory()->getTTL() * 600;
        $refreshTtl = $ttl * 3; // Refresh token TTL (3x longer)
        // Generate refresh token
        $refreshToken = JWTAuth::claims([
            'refresh' => true,
            'exp' => now()->addSeconds($refreshTtl)->timestamp
        ])->fromUser(auth()->user());
        $user = auth()->user();

        $restaurants = $user->restaurants()->with([
            'venueType',
            'venueIndustry',
            'loyaltyPrograms',
            'inventories',
            'venuePauseHistories' => function ($query) {
                $query->whereNull('reactivated_at')
                    ->orderByDesc('created_at');
            },
            'addresses',
            'cuisineTypes',
            'vtSubscription.plan',
            'appSubscriptions',
        ])->get();

        $employee = $user->employee()->with('role:id,name')->get();
        $hasAppAccess = false;
        $is_vision_track = false;

        if (count($restaurants) > 0) {
            foreach ($restaurants as $restaurant) {
                // Update logo/cover URLs
                $restaurant->cover = $restaurant->cover && $restaurant->cover !== 'logo' && $restaurant->cover !== 'https://via.placeholder.com/300x300'
                    ? Storage::disk('s3')->temporaryUrl($restaurant->cover, '+5 minutes')
                    : null;
                $restaurant->logo = $restaurant->logo && $restaurant->logo !== 'logo' && $restaurant->logo !== 'https://via.placeholder.com/300x300'
                    ? Storage::disk('s3')->temporaryUrl($restaurant->logo, '+5 minutes')
                    : null;

                // Venue configuration
                $venueConfiguration = RestaurantConfiguration::where('venue_id', $restaurant->id)->first();
                $managedInformation = HotelRestaurant::where('venue_id', $restaurant->id)->first();

                $allow_reservation_from = $venueConfiguration?->allow_reservation_from ?? false;
                $has_hotel_restaurant = $managedInformation ? true : false;

                $restaurant->allow_reservation_from = $allow_reservation_from;
                $restaurant->has_hotel_restaurant = $has_hotel_restaurant;

                // Check app subscription if sourceApp is provided
                if ($sourceApp) {
                    $appSubscription = $restaurant->appSubscriptions()
                        ->join('vb_apps', 'app_subscriptions.vb_app_id', '=', 'vb_apps.id')
                        ->where('vb_apps.slug', $sourceApp)
                        ->where('app_subscriptions.status', 'active')
                        ->first();

                    if ($appSubscription) {
                        $hasAppAccess = true;
                        $restaurant->current_app_subscription = $appSubscription;
                    }
                }

                // Check VT subscription and set is_vision_track flag
                $vtSubscription = $restaurant->vtSubscription;
                if ($vtSubscription && $vtSubscription->status === 'active') {
                    $is_vision_track = true; // Set to true if any restaurant has active VT subscription
                    $vtPlan = $vtSubscription->plan;

                    if ($vtPlan) {
                        // Sort the features within the plan
                        $sortedFeatures = $this->sortGroupedFeatures($vtPlan->features);
                        $vtPlan->features = $sortedFeatures;
                    }
                } else {
                    $restaurant->vt_subscription = null;
                }

                // Regular subscription check
                $activeSubscription = Subscription::with(['subscriptionItems.pricingPlanPrice', 'pricingPlan'])
                    ->where('venue_id', $restaurant->id)
                    ->where(function ($query) {
                        $query->where('status', 'active')
                            ->orWhere('status', 'trialing');
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();

                // Set up subscription plan info
                $planName = $activeSubscription?->pricingPlan?->name;
                $planId = $activeSubscription?->pricingPlan?->id;
                $planCycle = $activeSubscription
                    ? $activeSubscription->subscriptionItems->first()->pricingPlanPrice->recurring['interval']
                    : null;

                $subscriptionPlan = new stdClass;
                $subscriptionPlan->name = $planName;
                $subscriptionPlan->recurring = $planCycle ? ($planCycle === 'month' ? 'Monthly' : 'Yearly') : null;

                // Trial period check
                $now = Carbon::now();
                $trialStart = $activeSubscription?->trial_start;
                $trialEnd = $activeSubscription?->trial_end;
                $isTrialMode = $trialStart && $trialEnd ? $now->between($trialStart, $trialEnd) : false;

                // Check upgrade modal status
                $venueCExperience = VenueCustomizedExperience::where('venue_id', $restaurant->id)->first();
                $upgrade_from_trial_modal_seen = $venueCExperience?->upgrade_from_trial_modal_seen;

                $show_upgrade_from_trial = false;
                if (!$upgrade_from_trial_modal_seen && $trialEnd && $now->greaterThan($trialEnd)) {
                    $show_upgrade_from_trial = true;
                    if ($venueCExperience) {
                        $venueCExperience->upgrade_from_trial_modal_seen = now();
                        $venueCExperience->save();
                    }
                }

                // Get features
                $features = DB::table('plan_features')
                    ->join('features', 'plan_features.feature_id', '=', 'features.id')
                    ->where('plan_features.plan_id', $planId)
                    ->where('features.active', 1)
                    ->pluck('features.name');

                // Build subscription object
                $subscription = new stdClass;
                $subscription->is_trial_mode = $isTrialMode;
                $subscription->show_upgrade_from_trial = $show_upgrade_from_trial;
                $subscription->is_active = (bool)$activeSubscription;
                $subscription->features = $features;
                $subscription->plan = $activeSubscription ? $subscriptionPlan : null;

                $restaurant->subscription = $subscription;
            }

            if (!$restaurants[0]->subscription->is_active) {
                return response()->json([
                    'inactive_message' => 'Oops! It seems you don\'t have an active subscription. Please contact us at contact@venueboost.io if you want to have a subscription or if you think this is a mistake.',
                ]);
            }

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'restaurants' => $restaurants,
                    'employee' => $employee,
                    'has_app_access' => $hasAppAccess,
                    'allow_clockinout' => $allow_clockinout,
                    'is_vision_track' => $visionTrack,
                ],
                'access_token' => $token,
                'refresh_token' => $refreshToken, // Add refresh token
                'token_type' => 'bearer',
                'expires_in' => $ttl,
                'refresh_expires_in' => $refreshTtl, // Add refresh token expiration
            ]);
        }

        // Employee response when no restaurants
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee' => $employee,
                'allow_clockinout' => $allow_clockinout,
                'is_vision_track' => $visionTrack,
                'has_app_access' => true,
            ],
            'access_token' => $token,
            'refresh_token' => $refreshToken, // Add refresh token
            'token_type' => 'bearer',
            'expires_in' => $ttl,
            'refresh_expires_in' => $refreshTtl, // Add refresh token expiration
        ]);
    }

    protected function respondWithTokenForAffiliate(string $token): JsonResponse
    {
        $ttl = auth()->guard('api')->factory()->getTTL() * 600;
        $user = auth()->user();

        // check if user is affiliate
        $affiliate = $user->affiliate()->first();

        // Check for the existence of the affiliate.
        if (!$affiliate) {
            return response()->json(['error' => 'Affiliate not found in our records. Please check your details and try again.'], 404);
        }

// Check for the status of the affiliate.
        switch ($affiliate->status) {
            case 'applied':
                return response()->json(['error' => 'You are not authorized yet. Your application is being reviewed.'], 403);

            case 'declined':
                return response()->json(['error' => 'Your affiliate application has been declined. For more details, please contact our support team at contact@venueboost.io.'], 403);

            case 'approved':
                // Proceed with the rest of the code if the affiliate is approved.
                break;

            default:
                return response()->json(['error' => 'Unexpected affiliate status. Please contact support team at contact@venueboost.io for further assistance.'], 500);
        }


        $affiliateLinks = DB::table('venue_affiliate')
            ->join('potential_venue_leads', 'potential_venue_leads.id', '=', 'venue_affiliate.potential_venue_lead_id')
            ->join('restaurants', 'restaurants.id', '=', 'potential_venue_leads.venue_id')
            ->leftJoin('affiliate_wallet_history', 'affiliate_wallet_history.registered_venue_id', '=', 'restaurants.id')
            ->select(
                'venue_affiliate.*',
                'potential_venue_leads.affiliate_status',
                'restaurants.name as venue_name',
                DB::raw('SUM(affiliate_wallet_history.amount) as total_amount')
            )
            ->where('venue_affiliate.affiliate_code', $affiliate->affiliate_code)
            ->groupBy('venue_affiliate.id', 'potential_venue_leads.affiliate_status', 'restaurants.name')
            ->get();


        $affiliateWalletHistory = [];
        $affiliateWallet = AffiliateWallet::where('affiliate_id', $affiliate->id)->first();

        if ($affiliateWallet) {
            $affiliateWalletHistory = AffiliateWalletHistory::where('affiliate_wallet_id', $affiliateWallet->id)->get();

            // map only amount and transaction date and transaction type
            $affiliateWalletHistory = $affiliateWalletHistory->map(function ($item) {
                return [
                    'amount' => $item->amount,
                    'date' => Carbon::parse($item->created_at)->format('Y-m-d'),
                    'transaction_type' => $item->transaction_type,
                ];
            });
        }


        $affiliatePlan = AffiliatePlan::where('affiliate_id', $affiliate->id)->first();

        $affiliateLink = MarketingLink::where('affiliate_code', $affiliate->affiliate_code)->first();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'affiliate_code' => $affiliate->affiliate_code,
                'affiliate_link' => $affiliateLink ? $affiliateLink->short_url : null,
                'email_verified_at' => $user->email_verified_at,
                'affiliate_usage_links' => $affiliateLinks,
                'affiliate_wallet_history' => $affiliateWalletHistory,
                'affiliate_plan' => $affiliatePlan,
                'balance' => $affiliateWallet?->balance ?? 0,

            ],
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttl,
        ]);
    }

    protected function respondWithTokenForEndUser(string $token, string $source): JsonResponse
    {
        $ttl = auth()->guard('api')->factory()->getTTL() * 600;
        $user = auth()->user();

        if ($source == 'bybest.shop_web') {
            $BYBEST_SHOP_ID = '66551ae760ba26d93d6d3a32';

            $customer = Customer::where('user_id', $user->id)->first();
            $venue = Restaurant::where('id', $customer?->venue_id)->first();

            if (!$venue) {
                return response()->json(['error' => 'User can\'t login.'], 403);
            }

            // Call the CRM API
            $response = Http::get("https://crmapi.pixelbreeze.xyz/api/crm-web/customers/{$user->id}", [
                'subAccountId' => $BYBEST_SHOP_ID,
            ]);

            if ($response->successful()) {
                $crmData = $response->json()['result']['endUser'] ?? null;
                $referralCode = $crmData['referralCode'] ?? null;
                $currentTierName = $crmData['currentTierName'] ?? null;
                $walletBalance = $crmData['wallet']['balance'] ?? null;
            } else {
                $venue = null;
                $referralCode = null;
                $currentTierName = null;
                $walletBalance = null;
            }
        } else {
            $venue = null;
            $referralCode = null;
            $currentTierName = null;
            $walletBalance = null;
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'referralCode' => $referralCode,
                'currentTierName' => $currentTierName,
                'walletBalance' => $walletBalance,
                'enduser'=> $user->enduser,
                'customer'=> $user->customer ?? null,
                'venue_app_key' => $venue->app_key ?? null,
            ],
            'venue' => [
                'id' => $venue?->id,
                'name' => $venue?->name,
            ],
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttl,
        ]);
    }

    protected function respondWithTokenForSuperadmin(string $token): JsonResponse
    {
        $ttl = auth()->guard('api')->factory()->getTTL() * 600;
        $user = auth()->user();

        $subscribed = FirebaseUserToken::where('user_id', $user->id)->exists();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttl,
            'notification_subscribed' => $subscribed,
        ]);
    }

    /**
     * Get the token array structure.
     *
     * @param string $token
     *
     * @return JsonResponse
     */
    protected function respondWithTokenOnRefresh(string $token): JsonResponse
    {
        $ttl = auth()->guard('api')->factory()->getTTL() * 600;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttl,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/logout",
     *     operationId="logout",
     *     tags={"Authentication"},
     *     summary="Logout from the application (SN Boost)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response="200", description="Success"),
     *     @OA\Response(response="401", description="Unauthenticated")
     * )
     */
    public function logout(): JsonResponse
    {

        $token = JWTAuth::getToken();

        if (!$token) {
            throw new BadRequestHttpException('Token not provided');
        }
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            auth()->logout();
        } catch (TokenInvalidException $e) {
            throw new AccessDeniedHttpException('The token is invalid');
        }

        return response()->json(['message' => 'Successfully logged out']);

    }

    /**
     * @OA\Post(
     *     path="/refresh",
     *     operationId="refresh",
     *     tags={"Authentication"},
     *     summary="Refresh a token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response="200", description="Success"),
     *     @OA\Response(response="401", description="Unauthenticated")
     * )
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::getToken();
            if (!$token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token not provided'
                ], 400);
            }

            // Verify this is a refresh token
            $payload = JWTAuth::getPayload($token);
            if (!$payload->get('refresh')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid refresh token'
                ], 401);
            }

            // Get user from token before refreshing
            $user = JWTAuth::toUser($token);

            // Get new access token
            $newAccessToken = JWTAuth::refresh($token);

            // Calculate TTL
            $ttl = config('jwt.ttl') * 60; // Convert minutes to seconds
            $refreshTtl = $ttl * 3;

            // Generate new refresh token
            $newRefreshToken = JWTAuth::claims([
                'refresh' => true,
                'exp' => now()->addSeconds($refreshTtl)->timestamp
            ])->fromUser($user);

            return response()->json([
                'status' => 'success',
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
                'token_type' => 'bearer',
                'expires_in' => $ttl,
                'refresh_expires_in' => $refreshTtl
            ]);

        } catch (TokenInvalidException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token is invalid'
            ], 401);
        } catch (TokenExpiredException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token has expired'
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot refresh token'
            ], 401);
        }
    }

    /**
     * Get user profile.
     *
     * @OA\Get(
     *     path="/users/user-profile",
     *     summary="Get user profile",
     *     tags={"Authentication"},
     *     security={{"Bearer": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="User profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     description="User ID",
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     description="User name",
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     description="User email",
     *                 ),
     *                 @OA\Property(
     *                     property="restaurant",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(
     *                         property="id",
     *                         type="integer",
     *                         description="Restaurant ID",
     *                     ),
     *                     @OA\Property(
     *                         property="cuisine_types",
     *                         type="array",
     *                         @OA\Items(
     *                             type="integer",
     *                         ),
     *                         description="Array of cuisine type IDs",
     *                     ),
     *                     @OA\Property(
     *                         property="amenities",
     *                         type="array",
     *                         @OA\Items(
     *                             type="integer",
     *                         ),
     *                         description="Array of amenity IDs",
     *                     ),
     *                     @OA\Property(
     *                         property="address",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(
     *                             property="id",
     *                             type="integer",
     *                             description="Address ID",
     *                         ),
     *                     ),
     *                     @OA\Property(
     *                         property="plan",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(
     *                             property="id",
     *                             type="integer",
     *                             description="Pricing plan ID",
     *                         ),
     *                     ),
     *                 ),
     *                 @OA\Property(
     *                     property="employee",
     *                     type="object",
     *                     nullable=true,
     *                 ),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Invalid token",
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
    public function getUserProfile(): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants()->with([
            'venueType',
            'venueIndustry',
            'loyaltyPrograms',
            'inventories',
            'venuePauseHistories' => function ($query) {
                $query->whereNull('reactivated_at')
                    ->orderByDesc('created_at');
            },
            'addresses',
            'cuisineTypes',
        ])->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }


        $user = auth()->user();
        $employee = $user->employee()->with('role:id,name')->get();
        $restaurants = $user->restaurants()->with([
            'venueType',
            'venueIndustry',
            'loyaltyPrograms',
            'venuePauseHistories' => function ($query) {
                $query->whereNull('reactivated_at')
                    ->orderByDesc('created_at');
            },
            'addresses',
            'cuisineTypes',
        ])->get();


        foreach ($restaurants as $restaurant) {
            // Update the logo/cover properties
            $restaurant->cover = $restaurant->cover && $restaurant->cover !== 'logo' && $restaurant->cover !== 'https://via.placeholder.com/300x300' ? Storage::disk('s3')->temporaryUrl($restaurant->cover, '+5 minutes') : null;
            $restaurant->logo = $restaurant->logo && $restaurant->logo !== 'logo' && $restaurant->logo !== 'https://via.placeholder.com/300x300' ? Storage::disk('s3')->temporaryUrl($restaurant->logo, '+5 minutes') : null;
            $venueConfiguration = RestaurantConfiguration::where('venue_id', $restaurant->id)->first();
            $managedInformation = HotelRestaurant::where('venue_id', $restaurant->id)->first();

            $allow_reservation_from = false;
            $has_hotel_restaurant = false;
            if ($venueConfiguration) {
                $allow_reservation_from = $venueConfiguration->allow_reservation_from;
            }
            if ($managedInformation) {
                $has_hotel_restaurant = true;
            }

            $restaurant->allow_reservation_from = $allow_reservation_from;
            $restaurant->has_hotel_restaurant = $has_hotel_restaurant;


            $activeSubscription = Subscription::with(['subscriptionItems.pricingPlanPrice', 'pricingPlan'])
                ->where('venue_id', $restaurant->id)
                ->where(function ($query) {
                    $query->where('status', 'active')
                        ->orWhere('status', 'trialing');
                })
                ->orderBy('created_at', 'desc')
                ->first();
            $planName = $activeSubscription?->pricingPlan?->name;
            $planId = $activeSubscription?->pricingPlan?->id;
            $planCycle = $activeSubscription ? $activeSubscription->subscriptionItems->first()->pricingPlanPrice->recurring['interval'] : null;

            $subscriptionPlan = new stdClass;
            $subscriptionPlan->name = $planName;
            $subscriptionPlan->recurring = $planCycle ? $planCycle === 'month' ? 'Monthly' : 'Yearly' : null;

            // check if the subscription is in trial mode, now is in between the trial period trial_start and trial_end
            $now = Carbon::now();
            $trialStart = $activeSubscription?->trial_start;
            $trialEnd = $activeSubscription?->trial_end;
            $isTrialMode = $now->between($trialStart, $trialEnd);

            $venueCExperience = VenueCustomizedExperience::where('venue_id', $restaurant->id)->first();
            $upgrade_from_trial_modal_seen = $venueCExperience?->upgrade_from_trial_modal_seen;

            // check if the upgrade from trial modal has been seen and if now is greater than the trial end date
            if (!$upgrade_from_trial_modal_seen && $now->greaterThan($trialEnd)) {
                $show_upgrade_from_trial = true;
                $venueCExperience->upgrade_from_trial_modal_seen = now();
                $venueCExperience->save();
            } else {
                $show_upgrade_from_trial = false;
            }

            $features = DB::table('plan_features')
                ->join('features', 'plan_features.feature_id', '=', 'features.id')
                ->where('plan_features.plan_id', $planId)
                ->where('features.active', 1) // If you have an 'active' flag on features
                ->pluck('features.name');

            $subscription = new stdClass;
            $subscription->is_trial_mode = $isTrialMode;
            $subscription->show_upgrade_from_trial = $show_upgrade_from_trial;
            $subscription->is_active = (bool)$activeSubscription;
            $subscription->features = $features;
            $subscription->plan = $activeSubscription ? $subscriptionPlan : null;


            $restaurant->subscription = $subscription;
        }
        $restaurant = $venue;
        $restaurant->cuisine_types = [];
        $restaurant->amenities = [];
        $restaurant->address = null;

        $cuisine_types_ids = [];
        $cuisine_types = DB::table('restaurant_cuisine_types')->where('restaurants_id', $restaurant->id)->get();
        foreach ($cuisine_types as $key => $value) {
            $cuisine_types_ids[] = $value->cuisine_types_id;
        }
        $restaurant->cuisine_types = $cuisine_types_ids;

        $amenities_ids = [];
        $amenities = DB::table('restaurant_amenities')->where('restaurants_id', $restaurant->id)->get();
        foreach ($amenities as $key => $value) {
            $amenities_ids[] = $value->amenities_id;
        }
        $restaurant->amenities = $amenities_ids;

        $rest_address = DB::table('restaurant_addresses')->where('restaurants_id', $restaurant->id)->first();
        $restaurant->address = $rest_address && $rest_address->address_id ? DB::table('addresses')->where('id', $rest_address->address_id)->first() : null;

        $restaurant->plan = DB::table('pricing_plans')->where('id', $restaurant->plan_id)->first();

        // Update the logo/cover properties
        $restaurant->cover = $restaurant->cover && $restaurant->cover !== 'logo' && $restaurant->cover !== 'https://via.placeholder.com/300x300' ? Storage::disk('s3')->temporaryUrl($restaurant->cover, '+5 minutes') : null;
        $restaurant->logo = $restaurant->logo && $restaurant->logo !== 'logo' && $restaurant->logo !== 'https://via.placeholder.com/300x300' ? Storage::disk('s3')->temporaryUrl($restaurant->logo, '+5 minutes') : null;

        $venueType = VenueType::where('id', $restaurant->venue_type)->first();
        $venueIndustry = VenueIndustry::where('id', $restaurant->venue_industry)->first();
        $restaurant->venue_type = $venueType;
        $restaurant->venue_industry = $venueIndustry;

        $venueConfiguration = RestaurantConfiguration::where('venue_id', $restaurant->id)->first();
        $managedInformation = HotelRestaurant::where('venue_id', $restaurant->id)->first();

        $allow_reservation_from = false;
        $has_hotel_restaurant = false;
        if ($venueConfiguration) {
            $allow_reservation_from = $venueConfiguration->allow_reservation_from;
        }
        if ($managedInformation) {
            $has_hotel_restaurant = true;
        }

        $restaurant->allow_reservation_from = $allow_reservation_from;
        $restaurant->has_hotel_restaurant = $has_hotel_restaurant;


        $activeSubscription = Subscription::with(['subscriptionItems.pricingPlanPrice', 'pricingPlan'])
            ->where('venue_id', $restaurant->id)
            ->where(function ($query) {
                $query->where('status', 'active')
                    ->orWhere('status', 'trialing');
            })
            ->orderBy('created_at', 'desc')
            ->first();
        $planName = $activeSubscription?->pricingPlan?->name;
        $planId = $activeSubscription?->pricingPlan?->id;
        $planCycle = $activeSubscription ? $activeSubscription->subscriptionItems->first()->pricingPlanPrice->recurring['interval'] : null;

        $subscriptionPlan = new stdClass;
        $subscriptionPlan->name = $planName;
        $subscriptionPlan->recurring = $planCycle ? $planCycle === 'month' ? 'Monthly' : 'Yearly' : null;

        // check if the subscription is in trial mode, now is in between the trial period trial_start and trial_end
        $now = Carbon::now();
        $trialStart = $activeSubscription?->trial_start;
        $trialEnd = $activeSubscription?->trial_end;
        $isTrialMode = $now->between($trialStart, $trialEnd);

        $venueCExperience = VenueCustomizedExperience::where('venue_id', $restaurant->id)->first();
        $upgrade_from_trial_modal_seen = $venueCExperience?->upgrade_from_trial_modal_seen;

        // check if the upgrade from trial modal has been seen and if now is greater than the trial end date
        if (!$upgrade_from_trial_modal_seen && $now->greaterThan($trialEnd)) {
            $show_upgrade_from_trial = true;
            $venueCExperience->upgrade_from_trial_modal_seen = now();
            $venueCExperience->save();
        } else {
            $show_upgrade_from_trial = false;
        }

        $features = DB::table('plan_features')
            ->join('features', 'plan_features.feature_id', '=', 'features.id')
            ->where('plan_features.plan_id', $planId)
            ->where('features.active', 1) // If you have an 'active' flag on features
            ->pluck('features.name');

        $subscription = new stdClass;
        $subscription->is_trial_mode = $isTrialMode;
        $subscription->show_upgrade_from_trial = $show_upgrade_from_trial;
        $subscription->is_active = (bool)$activeSubscription;
        $subscription->features = $features;
        $subscription->plan = $activeSubscription ? $subscriptionPlan : null;


        $restaurant->subscription = $subscription;

        if (!$restaurant->subscription->is_active) {
            return response()->json([
                'inactive_message' => 'Oops! It seems you don\'t have an active subscription. Please contact us at contact@venueboost.io if you want to have a subscription or if you think this is a mistake.',
            ]);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'country_code' => $user->country_code,
                'restaurants' => $restaurants,
                'employee' => $employee,
                'restaurant' => $restaurant,
            ],
        ]);
    }

    public function getUserData(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized: No authenticated user.'], 401);
        }

        if ($user->role->name !== 'Superadmin') {
            return response()->json(['error' => 'Unauthorized: Only super admins can see this'], 403);
        }

        $subscribed = FirebaseUserToken::where('user_id', $user->id)->exists();

        return response()->json([
            'notification_subscribed' => $subscribed,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/users/request-change-email",
     *     summary="Request email change",
     *     tags={"Authentication"},
     *     security={{"Bearer": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"email"},
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     format="email",
     *                     description="New email address",
     *                 ),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email change request successful",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Success",
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
     *                 description="Error message",
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Invalid token",
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
    public function requestChangeEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $user = auth()->user();

            $email = $request->input('email');
            $permitted_chars = '0123456789';
            $code = substr(str_shuffle($permitted_chars), 0, 6);

            $data = [
                'user_id' => $user->id,
                'email' => $user->email,
                'new_email' => $email,
                'code' => $code,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $update_request = DB::table('email_update_requests')->where('user_id', $user->id)->first();
            if ($update_request) {
                DB::table('email_update_requests')->where('id', $update_request->id)->update($data);
            } else {
                DB::table('email_update_requests')->insert($data);
            }

            Mail::to($user->email)->send(new EmailChangeVerifyEmail($user->name, $code));

            return response()->json([
                'message' => 'Email change request successful',
            ]);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/users/verify-change-email",
     *     summary="Verify email change",
     *     tags={"Authentication"},
     *     security={{"Bearer": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"code"},
     *                 @OA\Property(
     *                     property="code",
     *                     type="string",
     *                     description="Verification code",
     *                 ),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email change verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Success",
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
     *                 description="Error message",
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Invalid token",
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
    public function verifyChangeEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $user = auth()->user();
            $code = $request->input('code');

            $update_request = DB::table('email_update_requests')
                ->where('user_id', $user->id)
                ->where('code', $code)
                ->first();
            if ($update_request) {
                $now = Carbon::now();
                if ($now->diffInMinutes(Carbon::parse($update_request->created_at)) > 5) {
                    return response()->json(['message' => 'The code has been expired.'], 400);
                }

                DB::table('email_update_requests')->where('id', $update_request->id)->delete();

                $user = User::where('id', $user->id)->first();
                $user->email = $update_request->new_email;
                $user->save();
            } else {
                return response()->json(['message' => 'Invalid Code'], 400);
            }

            return response()->json([
                'message' => 'Email changed verified successfully',
            ]);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/users/change-password",
     *     summary="Change password",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"cur_password", "password"},
     *                 @OA\Property(
     *                     property="cur_password",
     *                     type="string",
     *                     description="Current password",
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     type="string",
     *                     description="New password",
     *                 ),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Success",
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
     *                 description="Error message",
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Invalid current password",
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
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cur_password' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $user = auth()->user();
            $cur_password = $request->input('cur_password');
            $password = $request->input('password');

            if (!$token = JWTAuth::attempt(['email' => $user->email, 'password' => $cur_password])) {
                return response()->json(['message' => 'Invalid current password'], 400);
            } else {
                $user = User::where('id', $user->id)->first();
                $user->password = bcrypt($password);
                $user->save();
            }

            return response()->json([
                'message' => 'Password changed successfully',
            ]);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function registerEndUser(Request $request): JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'password' => 'required|string',
            'source' => 'required|string',
            'referral_code' => 'nullable|string',
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
            'enduser' => true
        ]);

        if (!$user) {
            return response()->json(['error' => 'User not created'], 400);
        }

        // send verification email
        $created_at = Carbon::now();
        $expired_at = $created_at->addMinutes(5); // Add 5mins
        $serverName = env('APP_NAME');

        $data = [
            'iss' => $serverName, // Issuer
            'exp' => $expired_at->timestamp, // Expire,
            'id' => $user->id,
        ];

        if ($request->source == 'bybest.shop_web') {
            // Create customer in db
            $customer = Customer::create([
                'user_id' => $user->id,
                'name' => $request->first_name . ' ' . $request->last_name,
                'email' => $request->email,
                'phone' => '',
                'address' => '',
                'venue_id' => $venue->id,
            ]);

            if (!$customer) {
                return response()->json(['error' => 'Customer not created'], 400);
            }

            // Insert user to CRM Pixel Breeze
            try {
                $data_string = [
                    "crm_client_customer_id" => $user->id,
                    "source" => "bybest.shop_web",
                    "firstName" => $request->first_name,
                    "lastName" => $request->last_name,
                    "email" => $request->email,
                    "phone" => $customer->phone ?? null,
                    "password" => $request->password,
                    "referral_code" => $request->referral_code ?? null,
                ];

                $response = Http::withHeaders([
                    "Content-Type" => "application/json",
                ])->post('https://crmapi.pixelbreeze.xyz/api/add-end-user-to-sub-account', $data_string);
                // We might want to log or handle the response here

            } catch (\Throwable $th) {
                dd($th);
                \Sentry\captureException($th);
                // You may want to log this error or handle it in some way
            }
        }

        $jwt_token = JWT::encode($data, env('JWT_SECRET'), 'HS256');
        $email_verify_link = env('APP_URL') . "/verify-email/$jwt_token";

        if ($request->source == 'bybest.shop_web') {
            Mail::to($user->email)->send(new ByBestShopUserVerifyEmail($user->name, $email_verify_link));
        } else {
            Mail::to($user->email)->send(new UserVerifyEmail($user->name, $email_verify_link));
        }


        $credentials = $request->only('email', 'password');
        $token = JWTAuth::attempt($credentials);
        $ttl = auth()->guard('api')->factory()->getTTL() * 600;

        return response()->json([
            'message' => 'We sent a verification email',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
            ],
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttl,
        ], 200);
    }

    public function resendVerifyEmail(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = auth()->user();
            $user = User::where('id', $user->id)->first();
            if (!$user) {
                return response()->json(['message' => 'Invalid user'], 400);
            }

            $validator = Validator::make($request->all(), [
                'source' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            $created_at = Carbon::now();
            $expired_at = $created_at->addMinutes(5); // Add 5mins
            $serverName = env('APP_NAME');

            $data = [
                // 'iat' => $created_at->timestamp, // Issued at: time when the token was generated
                // 'nbf' => $created_at->timestamp, // Not before
                'iss' => $serverName, // Issuer
                'exp' => $expired_at->timestamp, // Expire,
                'id' => $user->id,
            ];

            $jwt_token = JWT::encode($data, env('JWT_SECRET'), 'HS256');
            $email_verify_link = env('APP_URL') . "/verify-email/$jwt_token";

            if ($request->source == 'bybest.shop_web') {
                Mail::to($user->email)->send(new ByBestShopUserVerifyEmail($user->name, $email_verify_link));
            } else {
                Mail::to($user->email)->send(new UserVerifyEmail($user->name, $email_verify_link));
            }


            return response()->json(['message' => 'We sent a verification email again'], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function verifyEmail(Request $request): \Illuminate\Http\JsonResponse
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
            } catch (ExpiredException $expiredException) {
                return response()->json(['message' => 'Expired link'], 400);
            } catch (\Exception $e) {
                \Sentry\captureException($e);
                return response()->json(['message' => 'Invalid link'], 400);
            }

            $user = User::where('id', $id)->first();
            if (!$user) {
                return response()->json(['message' => 'Invalid link'], 404);
            }

            if ($user->email_verified_at) {
                return response()->json(['message' => 'Invalid link'], 400);
            }

            $user->email_verified_at = date('Y-m-d H:i:s');
            $user->save();

            return response()->json([
                'message' => 'Your email has been verified successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                ],
            ], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function sortGroupedFeatures(array $features): array
    {
        $orderPriority = [
            'Dashboard' => 1,
            'Analytics' => 2,
            'Devices' => 3,
            'Staff Management' => 4,
            'Security' => 5,
            'Environment' => 6,
            'Vehicle Management' => 7,
            'Settings' => 8
        ];

        $sortedFeatures = [];

        foreach ($orderPriority as $feature => $priority) {
            if (isset($features[$feature])) {
                $sortedFeatures[$feature] = $features[$feature];
                if (is_array($sortedFeatures[$feature])) {
                    ksort($sortedFeatures[$feature]);
                }
            }
        }

        return $sortedFeatures;
    }

}
