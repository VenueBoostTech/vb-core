<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Mail\OnboardingVerifyEmail;
use App\Mail\PostOnboardingSurveyFeedbackEmail;
use App\Mail\PostOnboardingWelcomeEmail;
use App\Mail\CompletedPreOnboardingEmail;
use App\Models\Address;
use App\Models\Affiliate;
use App\Models\AffiliatePlan;
use App\Models\AffiliateWallet;
use App\Models\AffiliateWalletHistory;
use App\Models\City;
use App\Models\Country;
use App\Models\CountryPaymentProvider;
use App\Models\Feature;
use App\Models\FeatureUsageCredit;
use App\Models\FeatureUsageCreditHistory;
use App\Models\MarketingWaitlist;
use App\Models\PasswordReset;
use App\Models\PotentialVenueLead;
use App\Models\PricingPlan;
use App\Models\PricingPlanPrice;
use App\Models\PromotionalCode;
use App\Models\Restaurant;
use App\Models\RestaurantConfiguration;
use App\Models\RestaurantReferral;
use App\Models\State;
use App\Models\SubscribedEmail;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\VenueAffiliate;
use App\Models\VenueCustomizedExperience;
use App\Models\VenueIndustry;
use App\Models\VenueLeadInfo;
use App\Models\VenueType;
use App\Models\VenueWallet;
use App\Models\Waitlist;
use App\Models\WalletHistory;
use App\Services\MondayAutomationsService;
use App\Services\TMActivePiecesAutomationsService;
use App\Traits\TracksOnboardingErrors;
use Carbon\Carbon;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JetBrains\PhpStorm\NoReturn;

class OnboardingController extends Controller
{
    protected $mondayAutomationService;
    protected $tmActivePiecersAutomationService;
    use TracksOnboardingErrors;

    public function __construct(MondayAutomationsService $mondayAutomationService, TMActivePiecesAutomationsService $tmActivePiecersAutomationService)
    {
        $this->mondayAutomationService = $mondayAutomationService;
        $this->tmActivePiecersAutomationService = $tmActivePiecersAutomationService;
    }

    public function create(Request $request): \Illuminate\Http\JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'representative_first_name' => 'required|string',
            'representative_last_name' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $potentialVenueLeadNew = new PotentialVenueLead();
        $potentialVenueLeadNew->email = $request->input('email');
        $potentialVenueLeadNew->representative_first_name = $request->input('representative_first_name');
        $potentialVenueLeadNew->representative_last_name = $request->input('representative_last_name');
        // Check if affiliate_code exists and get affiliate_id...
        if ($request->input('affiliate_code')) {
            $affiliate = Affiliate::where('affiliate_code', $request->input('affiliate_code'))->first();
            if ($affiliate) {

                $potentialVenueLeadNew->affiliate_code = $request->input('affiliate_code');
                $potentialVenueLeadNew->affiliate_id = $affiliate->id;
                $potentialVenueLeadNew->affiliate_status = 'pending';


            }
        }

        // Check if referral_code exists and get referer_id...
        if ($request->input('referral_code')) {
            $referralCode = Restaurant::where('referral_code', $request->input('referral_code'))->first();

            if ($referralCode) {
                $num_invites = DB::table('restaurant_referrals')->where('restaurant_id', $referralCode->id)->count();
                if ($num_invites < Controller::$LIMIT_NUM_REFERRAL) {

                    $potentialVenueLeadNew->referral_code = $request->input('referral_code');
                    $potentialVenueLeadNew->referer_id = $referralCode->id;
                    $potentialVenueLeadNew->referral_status = 'pending';

                }
            }
        }

        if ($request->input('promo_code')) {
            // Check if promotional_code exists and  end_date is greater than today...
            $promotionalCode  = PromotionalCode::where('code', $request->input('promo_code'))
                ->where('end', '>=', Carbon::now())
                ->first();

            if ($promotionalCode) {

                $potentialVenueLeadNew->promo_code = $request->input('promo_code');
                $potentialVenueLeadNew->promo_code_id = $promotionalCode->id;
            }
        }


        $potentialVenueLeadNew->from_september_new = true;
        $potentialVenueLeadNew->save();


        $created_at = Carbon::now();
        $expired_at = $created_at->addMinutes(1140); // Add 24 hours
        $serverName = 'VenueBoost';

        $data = [
            // 'iat' => $created_at->timestamp, // Issued at: time when the token was generated
            // 'nbf' => $created_at->timestamp, // Not before
            'iss' => $serverName, // Issuer
            'exp' => $expired_at->timestamp, // Expire,
            'id' => $potentialVenueLeadNew->id,
        ];

        $jwt_token = JWT::encode($data, env('JWT_SECRET'), 'HS256');
        $email_verify_link = 'https://venueboost.io' . "/onboarding/$jwt_token";

        Mail::to($request->email)->send(new OnboardingVerifyEmail($request->first_name ?? null, $email_verify_link, false));



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
                        'lead_id' => $potentialVenueLeadNew->id,

                    ]
                );

            }

        }
        try {
            $this->tmActivePiecersAutomationService->automateLeadCreation($potentialVenueLeadNew, $storeAutomationSubscribed);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            // do nothing
        }


        // Make the TikTok API call in a separate try-catch block
        try {
            $client = new Client();
            $token = 'e9d43fa991d07c689b23bd92b79f9f89a443fd57';

            $hashedEmail = hash('sha256', $request->input('email'));
            $hashedEventId = hash('sha256', uniqid('event_', true));
            $hashedExternalId = hash('sha256', $potentialVenueLeadNew->id);

            $payload = [
                "event_source" => "web",
                "event_source_id" => "CPMC3ABC77U5K3OPHTC0",
                "data" => [
                    [
                        "event" => "VenueBoost Get Started Submission",
                        "event_id" => $hashedEventId,
                        "event_time" => time(),

                        "user" => [
                            "email" => $hashedEmail,
                            "external_id" => $hashedExternalId
                        ]
                    ]
                ]
            ];

            $response = $client->post('https://business-api.tiktok.com/open_api/v1.3/event/track/', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Access-Token' => $token
                ],
                'json' => $payload
            ]);

            if ($response->getStatusCode() != 200) {
                $body = $response->getBody();
                Log::error('TikTok API error: ' . $body);
            }

        } catch (\Exception $e) {
            Log::error('Error sending event to TikTok API: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Lead form created successfully']);

    }

    public function verifyEmailLink(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            $this->logValidationError(null, 'verifyEmailLink', new ValidationException($validator));
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $token = $request->input('token');

            $id = null;
            try {
                $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
                $id = $decoded->id;
            } catch (ExpiredException|\Exception $expiredException) {
                $this->logOnboardingError(
                    $id,
                    'verifyEmailLink',
                    'TokenException',
                    'Invalid onboarding link',
                    $expiredException->getTraceAsString()
                );
                return response()->json(['message' => 'Invalid onboarding link'], 400);
            }

            $potentialVenueLead = PotentialVenueLead::where('id', $id)->first();
            if (!$potentialVenueLead) {
                $this->logOnboardingError(
                    $id,
                    'verifyEmailLink',
                    'NotFoundError',
                    'Invalid onboarding link - PotentialVenueLead not found',
                    null
                );
                return response()->json(['message' => 'Invalid onboarding link'], 404);
            }

            $currentOnboardingStep = $potentialVenueLead->current_onboarding_step;

            $potentialVenueLead->started_onboarding = true;
            $potentialVenueLead->current_onboarding_step = $currentOnboardingStep === 'initial_form_submitted' ? 'email_verified' : $currentOnboardingStep;
            $potentialVenueLead->email_verified = true;
            $potentialVenueLead->save();

            $responseData = [
                'message' => 'Valid link',
                'email' => $potentialVenueLead->email,
                'current_onboarding_step' => $currentOnboardingStep,
            ];

            $user = User::where('email', $potentialVenueLead->email)->first();
            if ($user) {
                $venue = Restaurant::with(['addresses', 'venueCustomizedExperience', 'venueIndustry', 'venueType'])
                    ->where('user_id', $user->id)
                    ->first();

                if ($venue) {
                    // Business details data
                    $responseData['business_details'] = [
                        'restaurant_name' => $venue->name,
                        'venue_type' => $venue->venueType->short_name,
                        'venue_industry' => $venue->venueIndustry->short_name,
                        'years_in_business' => $venue->years_in_business,
                        'address' => $venue->addresses->first(),
                    ];

                    // Industry data
                    $venueIndustry = $venue->venueIndustry->name;
                    $venueIndustryCombinations = [
                        'Food' => 'food',
                        'Sport & Entertainment' => 'sport_entertainment',
                        'Accommodation' => 'accommodation',
                        'Retail' => 'retail',
                    ];
                    $responseData['industry'] = $venueIndustryCombinations[$venueIndustry] ?? null;

                    // Interest and engagement data
                    if ($venue->venueCustomizedExperience) {
                        $responseData['interest_engagement'] = $venue->venueCustomizedExperience->toArray();
                    }

                    // Pricing plans
                    $pricingPlans = PricingPlan::where('category', $venueIndustry === 'Sport & Entertainment' ? 'sport_entertainment' : $venueIndustry)
                        ->with('pricingPlanPrices')
                        ->where('is_custom', 0)
                        ->where('active', 1)
                        ->where('stripe_id', '!=', null)
                        ->get();

                    $responseData['onboarding_pricing_plans'] = $pricingPlans->map(function ($plan) {
                        return [
                            'name' => $plan->name,
                            'description' => $plan->description,
                            'prices' => $plan->pricingPlanPrices()->select('unit_amount', 'recurring', 'trial_period_days', 'stripe_id')->get()
                        ];
                    });

                    // Recommended pricing plan
                    $venueLeadInfo = VenueLeadInfo::where('venue_id', $venue->id)->first();
                    $responseData['recommended_pricing_plan'] = $venueLeadInfo ? $venueLeadInfo->gpt_plan_suggested : 'Elevate';
                }
            }

            return response()->json($responseData, 200);
        } catch (\Exception $e) {
            $this->logOnboardingError(
                $id ?? null,
                'verifyEmailLink',
                get_class($e),
                $e->getMessage(),
                $e->getTraceAsString()
            );
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


    public function getPaymentGateway(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'country_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        // Get query parameters for filtering
        $countryCode = $request->input('country_code');
        $activeOnly = $request->input('active_only', true); // Default to true if not provided

        // get country id based on country code
        $country = Country::where('code', $countryCode)->first();
        if (!$country) {
            return response()->json(['message' => 'Invalid country code'], 400);
        }


        // get payment gateways based on country id and active
        $paymentGateways = CountryPaymentProvider::where('country_id', $country->id)->where('active', $activeOnly)->get();

        return response()->json($paymentGateways);
    }

    public function getPaymentGatewaysForSuperadmin(Request $request): \Illuminate\Http\JsonResponse
    {
        // Get query parameters for filtering
        $activeOnly = $request->input('active_only', true) ? 1 : 0; // Convert to integer for SQL

        // Raw SQL query to fetch countries with active payment providers
        $result = DB::select("
        SELECT c.code AS country_code, c.name AS country_name,
               (SELECT JSON_OBJECT('name', cpp.payment_provider,

                                   'start_time', cpp.start_time,
                                   'end_time', cpp.end_time)) AS payment_provider
        FROM countries c
        INNER JOIN country_payment_provider cpp ON c.id = cpp.country_id
        WHERE cpp.active = :activeOnly
    ", ['activeOnly' => $activeOnly]);

        // Decode the payment_provider field from JSON to object
        foreach ($result as $row) {
            $row->payment_provider = json_decode($row->payment_provider);
        }

        return response()->json($result);
    }

    public function verifyPasswordLink(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $token = $request->input('token');

            try {
                $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
                $id = $decoded->user_id;
            } catch (ExpiredException|\Exception $expiredException) {
                return response()->json(['message' => 'Invalid magic link'], 400);
            }

            $user = User::where('id', $id)->first();
            // validate also if the token is part of password reset tokens
            $passwordResetToken = PasswordReset::where('token', $token)->where('email', $user->email)->first();

            if (!$passwordResetToken) {
                return response()->json(['message' => 'Invalid magic link'], 400);
            }

            return response()->json([
                'message' => 'Valid link',
                'email' => $user->email,
            ], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


    public function generateOnboardingLink(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        try {
            $email = $request->input('email');


            $potentialVenueLead = PotentialVenueLead::where('email', $email)->first();
            if (!$potentialVenueLead) {
                return response()->json(['message' => "No venue record found for the provided email. Please ensure you've entered the correct email address for the venue"], 404);
            }

            $created_at = Carbon::now();
            $expired_at = $created_at->addMinutes(240); // Add 240mins
            $serverName = 'VenueBoost';

            $data = [
                // 'iat' => $created_at->timestamp, // Issued at: time when the token was generated
                // 'nbf' => $created_at->timestamp, // Not before
                'iss' => $serverName, // Issuer
                'exp' => $expired_at->timestamp, // Expire,
                'id' => $potentialVenueLead->id,
            ];

            $jwt_token = JWT::encode($data, env('JWT_SECRET'), 'HS256');
            $email_verify_link = 'https://venueboost.io' . "/onboarding/$jwt_token";

            Mail::to($email)->send(new OnboardingVerifyEmail($potentialVenueLead->representative_first_name ?? null, $email_verify_link, true));

            return response()->json(['message' => 'Onboarding Link generated successfully']);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function generateMagicLink(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'venue_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        try {
            $email = $request->input('email');
            $userExists = User::where('email', $email)->first();

            if (!$userExists) {
                return response()->json(['message' => "No user record found for the provided email. Please ensure you've entered the correct email address for the venue"], 404);
            }

            // check if potential venue lead exists and $potentialVenueLead->current_onboarding_step
            $potentialVenueLead = PotentialVenueLead::where('email', $email)->where('current_onboarding_step', 'subscription_plan_selection')->first();

            if (!$potentialVenueLead) {
                return response()->json(['message' => "No venue record found for the provided email. Please ensure you've entered the correct email address for the venue"], 404);
            }

            $venueExists = Restaurant::where('id', $request->input('venue_id'))->first();

            if (!$venueExists) {
                return response()->json(['message' => "No venue record found for the provided venue id. Please ensure you've entered the correct venue id"], 404);
            }

            // check if venue belongs to user

            if ($venueExists->user_id !== $userExists->id) {
                return response()->json(['message' => "The venue with the provided id does not belong to the user with the provided email"], 404);
            }

            // check if venue is already send the welcome email
            $venueExperience = VenueCustomizedExperience::where('venue_id', $venueExists->id)->first();
            $venuePostOnboardingEmailSentAt = $venueExperience->post_onboarding_welcome_email_sent_at ?? null;


            if ($venuePostOnboardingEmailSentAt) {
                return response()->json(['message' => "The venue with the provided id has already been sent the welcome email"], 404);
            }

            $created_at = Carbon::now();
            $expired_at = $created_at->addMinutes(1440); // Add 1440mins
            $serverName = 'VenueBoost';

            $data = [
                // 'iat' => $created_at->timestamp, // Issued at: time when the token was generated
                // 'nbf' => $created_at->timestamp, // Not before
                'iss' => $serverName, // Issuer
                'exp' => $expired_at->timestamp, // Expire,
                'user_id' => $userExists->id,
            ];

            $jwt_token = JWT::encode($data, env('JWT_SECRET'), 'HS256');
            $email_verify_link = 'https://venueboost.io' . "/reset-password/$jwt_token";

            $passwordReset = DB::table('password_resets')->where('email', $email)->first();

            if ($passwordReset) {
                // Record exists, update it
                DB::table('password_resets')->where('email', $email)->update([
                    'token' => $jwt_token
                ]);
            } else {
                // Record does not exist, create a new one
                DB::table('password_resets')->insert([
                    'email' => $email,
                    'token' => $jwt_token,
                    'created_at' => now()  // Add current time for created_at
                ]);
            }

            $venueExperience->update([
                'post_onboarding_welcome_email_sent_at' => Carbon::now()
            ]);

            Mail::to($email)->send(new PostOnboardingWelcomeEmail(  $userExists->name ?? $userExists->first_name .' '. $userExists->last_name, $email_verify_link));

            return response()->json(['message' => 'Magic Link generated successfully']);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function resetPassword(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => [
                'required',
                'string',
                'min:8', // minimum 8 characters
                'regex:/[a-z]/', // at least one lowercase letter
                'regex:/[A-Z]/', // at least one uppercase letter
                'regex:/[@$!%*#?&]/', // at least one special character
                'confirmed' // password confirmation
            ],
        ]);

        $passwordReset = DB::table('password_resets')->where([
            ['token', $request->token],
            ['email', $request->email]
        ])->first();

        if (!$passwordReset) {
            return response()->json(['message' => 'This password reset token is invalid.'], 404);
        }

        // Check if token has expired
        if (Carbon::parse($passwordReset->created_at)->addMinutes(1440)->isPast()) {
            return response()->json(['message' => 'This password reset token has expired.'], 404);
        }

        $user = User::where('email', $passwordReset->email)->first();
        if (!$user) {
            return response()->json(['message' => 'We can\'t find a user with that e-mail address.'], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the password reset token
        DB::table('password_resets')->where(['email'=> $request->email])->delete();

        return response()->json(['message' => 'Password has been successfully reset.']);
    }


    // list potential venue leads
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $excludedIds = [16, 17];
        $excludedRange = range(20, 26);
        $excludedIds = array_merge($excludedIds, $excludedRange);
        $potentialVenueLeads = PotentialVenueLead::with([
            'venue',
            'venueCustomizedExperience',
            'venueLeadInfo',
            'venueAffiliate',
            'restaurantReferral',
            'affiliate',
            'promoCode',
            'referrer'
        ])->whereNotIn('id', $excludedIds)
            ->orderBy('created_at', 'desc')
            ->get();

        // format created_at for each waitlist
        $formattedPotentialVenueLeads = $potentialVenueLeads->map(function ($potentialVenueLead) {
            $potentialVenueLead->created_at_formatted = $potentialVenueLead->created_at->format('Y-m-d H:i:s');
            $potentialVenueLead->venue_name = $potentialVenueLead->venue->name ?? null;
            return $potentialVenueLead;
        });

        // get it with relationship with venue
        return response()->json(['data' => $formattedPotentialVenueLeads]);
    }

    public function trackOnboarding(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // step should be in the  ['initial_form_submitted', 'email_verified', 'business_details', 'interest_engagement', 'subscription_plan_selection']
            'step' => 'required|string|in:initial_form_submitted,email_verified,business_details,interest_engagement,subscription_plan_selection',
            'email' => 'required|email',
        ]);


        if ($request->input('step') === 'business_details') {
            $validator->sometimes('restaurant_name', 'required|string', function ($input) {
                return $input->step === 'business_details';
            });

            $validator->sometimes('venue_type', 'required|string', function ($input) {
                return $input->step === 'business_details';
            });

            $validator->sometimes('venue_industry', 'required|string', function ($input) {
                return $input->step === 'business_details';
            });

            $validator->sometimes('address_line1', 'required|string', function ($input) {
                return $input->step === 'business_details';
            });

            $validator->sometimes('address_line2', 'nullable|string', function ($input) {
                return $input->step === 'business_details';
            });

            $validator->sometimes('country', 'required|integer', function ($input) {
                return $input->step === 'business_details';
            });

            $validator->sometimes('state', 'required|integer', function ($input) {
                return $input->step === 'business_details';
            });

            $validator->sometimes('city', 'required|integer', function ($input) {
                return $input->step === 'business_details';
            });

            $validator->sometimes('restaurant_zipcode', 'required|string', function ($input) {
                return $input->step === 'business_details';
            });

            $validator->sometimes('years_in_business', 'required|integer', function ($input) {
                return $input->step === 'business_details';
            });
        }

        if ($request->input('step') === 'interest_engagement') {
            $validator->sometimes('number_of_employees', 'required|integer', function ($input) {
                return $input->step === 'interest_engagement';
            });

            $validator->sometimes('annual_revenue', 'required|numeric', function ($input) {
                return $input->step === 'interest_engagement';
            });

            $validator->sometimes('website', 'nullable|string', function ($input) {
                return $input->step === 'interest_engagement';
            });

            $validator->sometimes('social_media', 'nullable|array', function ($input) {
                return $input->step === 'interest_engagement';
            });

            $validator->sometimes('social_media.*', 'in:facebook,twitter,instagram,tiktok', function ($input) {
                return $input->step === 'interest_engagement';
            });

            $validator->sometimes('business_challenge', 'required|string', function ($input) {
                return $input->step === 'interest_engagement';
            });

            $validator->sometimes('other_business_challenge', 'nullable|string', function ($input) {
                return $input->step === 'interest_engagement';
            });

            $validator->sometimes('contact_reason', 'required|string', function ($input) {
                return $input->step === 'interest_engagement';
            });

            $validator->sometimes('how_did_you_hear_about_us', 'required|string', function ($input) {
                return $input->step === 'interest_engagement';
            });

            $validator->sometimes('how_did_you_hear_about_us_other', 'nullable|string', function ($input) {
                return $input->step === 'interest_engagement';
            });

            $validator->sometimes('biggest_additional_change', 'nullable|string', function ($input) {
                return $input->step === 'interest_engagement';
            });

        }



        if ($validator->fails()) {
            $this->logValidationError($request->input('email'), 'trackOnboarding', new ValidationException($validator));
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        try {
            $email = $request->input('email');


            $potentialVenueLead = PotentialVenueLead::where('email', $email)->first();
            if (!$potentialVenueLead) {
                return response()->json(['message' => "No venue record found for the provided email. Please ensure you've entered the correct email address for the venue"], 404);
            }

            if ($request->input('step') === 'interest_engagement') {

                $userId = User::where('email', $email)->first()->id;
                $venue = Restaurant::where('user_id', $userId)->first();

                // Convert business_challenge to string if it's an array
                $businessChallenge = $request->input('business_challenge');
                if (is_array($businessChallenge)) {
                    $businessChallenge = implode(', ', $businessChallenge);
                }

                // Find or create a new VenueCustomizedExperience entry
                $venueCustomizedExperience = VenueCustomizedExperience::updateOrCreate(
                    [
                        'venue_id' => $venue->id,
                        'potential_venue_lead_id' => $potentialVenueLead->id,
                    ],
                    [
                        'contact_reason' => $request->input('contact_reason'),
                        'number_of_employees' => $request->input('number_of_employees'),
                        'annual_revenue' => $request->input('annual_revenue'),
                        'website' => $request->input('website'),
                        'social_media' => json_encode($request->input('social_media')),
                        'business_challenge' => $businessChallenge,
                        'other_business_challenge' => $request->input('other_business_challenge'),
                        'how_did_you_hear_about_us' => $request->input('how_did_you_hear_about_us'),
                        'how_did_you_hear_about_us_other' => $request->input('how_did_you_hear_about_us_other'),
                        'biggest_additional_change' => $request->input('biggest_additional_change'),
                    ]
                );

                // update potential venue lead onboarding step
                $potentialVenueLead->current_onboarding_step = $request->input('step');
                $potentialVenueLead->save();

                try {
                    $this->mondayAutomationService->onboardProcess($venue->id, 'interest_engagement');
                } catch (\Exception $e) {
                    \Sentry\captureException($e);
                    // do nothing
                }
            }

            if ($request->input('step') === 'business_details') {
                $venue_type = $request->input('venue_type');

                // find state name based on state id
                $state = State::where('id', $request->input('state'))->first();
                if (!$state) {
                    return response()->json(['error' => 'Invalid state ID'], 400);
                }

                // find country name based on country id
                $country = Country::where('id', $request->input('country'))->first();
                if (!$country) {
                    return response()->json(['error' => 'Invalid country ID'], 400);
                }

                // find city name based on city id
                $city = City::where('id', $request->input('city'))->first();
                if (!$city) {
                    return response()->json(['error' => 'Invalid city ID'], 400);
                }

                $restaurantAddressData = [
                    'address_line1' => $request->input('address_line1'),
                    'address_line2' => $request->input('address_line2'),
                    'state' => $state->name,
                    'city' => $city->name,
                    'country' => $country->name,
                    'postcode' => $request->input('restaurant_zipcode'),
                    'state_id' => $request->input('state'),
                    'city_id' => $request->input('city'),
                    'country_id' => $request->input('country'),
                ];

                $venueType = VenueType::where('short_name', $venue_type)->first();
                if (!$venueType) {
                    return response()->json(['error' => 'Invalid venue type'], 400);
                }

                $venueIndustry = VenueIndustry::where('short_name', $request->input('venue_industry'))->first();
                if (!$venueIndustry) {
                    return response()->json(['error' => 'Invalid venue industry'], 400);
                }

                // Create new restaurant
                $owner_user = User::where('email', $email)->first();
                if (!$owner_user) {
                    // generate random password for the user with 8 characters without a function
                    $generatePassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
                    $hashedPassword = Hash::make($generatePassword);
                    $newUserID = DB::table('users')->insertGetId([
                        'name' => $potentialVenueLead->representative_first_name . ' ' . $potentialVenueLead->representative_last_name,
                        'country_code' => Country::where('id', $request->input('country'))->first()->code,
                        'email' => $email,
                        'password' => $hashedPassword,
                    ]);

                    $owner_user = User::where('id', $newUserID)->first();
                }

                $restaurant = Restaurant::where('user_id', $owner_user->id)->first();

                if ($restaurant) {
                    // Update existing restaurant
                    $restaurant->update([
                        'name' => $request->input('restaurant_name'),
                        'venue_type' => $venueType->id,
                        'venue_industry' => $venueIndustry->id,
                        'years_in_business' => $request->input('years_in_business'),
                    ]);

                    $rest_address = DB::table('restaurant_addresses')->where('restaurants_id', $restaurant->id)->first();

                    if ($rest_address) {
                        // Update existing address
                        Address::where('id', $rest_address->address_id)->update($restaurantAddressData);
                    } else {
                        // Create new address if it doesn't exist
                        $address = Address::create($restaurantAddressData);
                        DB::table('restaurant_addresses')->insert([
                            'address_id' => $address->id,
                            'restaurants_id' => $restaurant->id
                        ]);
                    }
                } else {

                    $restaurant_name = $request->input('restaurant_name');

                    $restaurant = new Restaurant();
                    $restaurant->name = $restaurant_name;
                    $restaurant->email = $email;
                    $restaurant->logo = '';
                    $restaurant->cover = '';
                    $restaurant->short_code = generateStringShortCode($restaurant_name);
                    $restaurant->app_key = generateStringAppKey($restaurant_name);
                    $restaurant->venue_type = $venueType->id;
                    $restaurant->venue_industry = $venueIndustry->id;
                    $restaurant->is_main_venue = 1;
                    $restaurant->phone_number = '-';
                    $restaurant->website = "";
                    $restaurant->pricing = "";
                    $restaurant->capacity = $capacity ?? 0;
                    $restaurant->user_id = $owner_user->id;
                    $restaurant->years_in_business = $request->input('years_in_business');
                    $restaurant->status = 'completed';
                    $restaurant->save();

                    $address = Address::create($restaurantAddressData);

                    if ($address) {
                        DB::table('restaurant_addresses')->insert([
                            'address_id' => $address->id,
                            'restaurants_id' => $restaurant->id
                        ]);
                    }

                    DB::table('employees')->insert([
                        'name' => $owner_user->name,
                        'email' => $owner_user->email,
                        'role_id' => $venue_type === 'Hotel' ? 5 : ($venue_type === 'Golf Venue' ? 13 : 2),
                        'salary' => 0,
                        'salary_frequency' => 'monthly',
                        'restaurant_id' => $restaurant->id,
                        'user_id' => $owner_user->id
                    ]);
                }

                RestaurantConfiguration::updateOrCreate(
                    ['venue_id' => $restaurant->id],
                    ['allow_reservation_from' => $venueIndustry->short_name === 'Food' ? 1 : 0]
                );

                // update potential venue lead onboarding step and venue id
                $potentialVenueLead->current_onboarding_step = $request->input('step');
                $potentialVenueLead->venue_id = $restaurant->id;
                $potentialVenueLead->save();

                try {
                    $this->mondayAutomationService->onboardProcess($restaurant->id, 'business_details');
                } catch (\Exception $e) {
                    \Sentry\captureException($e);
                    // do nothing
                }

                // check if potential_venue id has affiliate
                $affiliate_id = $potentialVenueLead->affiliate_id;
                // Check if an affiliate is associated with this lead
                if (!is_null($affiliate_id)) {
                    // Update the affiliate_status to 'started'
                    $potentialVenueLead->affiliate_status = 'started';

                    // Create or update a record in the venue_affiliate table
                    DB::table('venue_affiliate')->updateOrInsert(
                        ['venue_id' => $restaurant->id],
                        [
                            'affiliate_id' => $affiliate_id,
                            'affiliate_code' => $potentialVenueLead->affiliate_code,
                            'potential_venue_lead_id' => $potentialVenueLead->id,
                        ]
                    );
                }

                // check if potential_venue id has referral
                $referer_id = $potentialVenueLead->referer_id;

                // Check if a referral is associated with this lead
                if (!is_null($referer_id)) {
                    // Update the referral status to 'started'
                    $potentialVenueLead->referral_status = 'started';

                    $referral = DB::table('restaurant_referrals')
                        ->updateOrInsert(
                            ['register_id' => $restaurant->id],
                            [
                                'restaurant_id' => $referer_id,
                                'referral_code' => $potentialVenueLead->referral_code,
                                'potential_venue_lead_id' => $potentialVenueLead->id,
                                'used_time' => Carbon::now(),
                                'is_used' => 1,
                            ]
                        );

                    if (!$restaurant->used_referral_id) {
                        $restaurant->used_referral_id = $referral->id ?? null;
                        $restaurant->save();
                    }
                }
            }

            if ($request->input('step') === 'subscription_plan_selection') {

                $userId = User::where('email', $email)->first()->id;
                $venue = Restaurant::where('user_id', $userId)->first();
                try {
                    $this->mondayAutomationService->onboardProcess($venue->id, 'subscription_plan_selection');
                } catch (\Exception $e) {
                    \Sentry\captureException($e);
                    // do nothing
                }
            }



            return response()->json(['message' => 'Business details saved successfully', 'potentialVenueLead' => $potentialVenueLead], 200);
        } catch (\Exception $e) {
            $this->logOnboardingError(
                $request->input('email'),
                'trackOnboarding',
                $e->getMessage(),
                $e->getTraceAsString()
            );
            \Sentry\captureException($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function completeSubscriptionChosenDuringOnboarding(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'freemium' => 'nullable|boolean',
            'requested_custom' => 'nullable|boolean',
            'mode'=> 'nullable|string'
        ]);

        if ($validator->fails()) {
            $this->logValidationError($request->input('email'), 'completeSubscriptionChosenDuringOnboarding', new ValidationException($validator));
            return response()->json(['error' => $validator->errors()], 400);
        }

        $userId = User::where('email', $request->input('email'))->first()->id;
        $venue = Restaurant::where('user_id', $userId)->first();
        $userExists = User::where('id', $userId)->first();

        if($request->input('freemium') === true) {

            $stripe = new \Stripe\StripeClient (
                config('services.stripe.key')
            );

            // find stripe product id in our database
            $freePlan = PricingPlan::where('name', 'Discover')
                ->where('active', 1)
                ->where('category', $venue->venueType->definition)->first();
            $freePlanStripeId = $freePlan->stripe_id;

            $product = $stripe->products->retrieve($freePlanStripeId, []);


            $stripeProductId = null;
            if ($product->id) {
                $stripeProductId = $product->id;
            }

            // $product = $stripe->products->retrieve($product_id, []);
            $prices = $stripe->prices->all([
                'product' => $stripeProductId,
                'active' => true, // Optional: Specify if you only want active prices.
                'limit' => 10 // Optional: Define how many results you want (up to 100).
            ]);

            $price_id = null;
            $mode = $request->input('mode') ?? 'monthly';
            try {
                foreach ($prices->data as $key => $price) {
                    if (($mode == 'monthly' && $price->recurring->interval == 'month') ||
                        ($mode == 'yearly' && $price->recurring->interval == 'year')
                    ) {
                        $price_id = $price->id;
                        break;
                    }
                }
                // create subscription
                if (!$venue->stripe_customer_id) {
                    $customer = $stripe->customers->create(
                        [
                            'email' => $venue->user->email,
                            'name' => $venue->user->name ?? $venue->user->first_name . ' ' . $venue->user->last_name,

                        ]
                    );
                    $venue->stripe_customer_id = $customer->id;
                    $venue->save();
                }

                $stripeSubscription = $stripe->subscriptions->create([
                    'customer' => $venue->stripe_customer_id,
                    'items' => [
                        [
                            'price' => $price_id,
                        ],
                    ],
                    // 'automatic_tax' => [
                        // 'enabled' => true,
                    // ],
                ]);

                if ($stripeSubscription->status == 'active') {
                    $pricingPlan = PricingPlan::where('stripe_id', $stripeSubscription['plan']['product'])->first();


                    $subscriptionData = [
                        'user_id' => $userId,
                        'venue_id' => $venue->id,
                        'pricing_plan_id' => $pricingPlan->id,
                        'pricing_plan_stripe_id' => $stripeSubscription['plan']['product'],
                        'stripe_subscription_id' => $stripeSubscription['id'],
                        'status' => $stripeSubscription['status'],
                        'trial_start' => $stripeSubscription['trial_start'] ? Carbon::createFromTimestamp($stripeSubscription['trial_start']) : null,
                        'trial_end' => $stripeSubscription['trial_end'] ? Carbon::createFromTimestamp($stripeSubscription['trial_end']) : null,
                        'trial_end_behavior' => $stripeSubscription['trial_settings']['end_behavior']['missing_payment_method'] ?? null,
                        'cancel_at_period_end' => $stripeSubscription['cancel_at_period_end'],
                        'automatic_tax_enabled' => $stripeSubscription['automatic_tax']['enabled'],
                        'billing_cycle_anchor' => $stripeSubscription['billing_cycle_anchor'],
                        'billing_thresholds' => json_encode($stripeSubscription['billing_thresholds']),
                        'cancel_at' => $stripeSubscription['cancel_at'] ? Carbon::createFromTimestamp($stripeSubscription['cancel_at']) : null,
                        'canceled_at' => $stripeSubscription['canceled_at'] ? Carbon::createFromTimestamp($stripeSubscription['canceled_at']) : null,
                        'cancellation_details' => json_encode($stripeSubscription['cancellation_details']),
                        'collection_method' => $stripeSubscription['collection_method'],
                        'currency' => $stripeSubscription['currency'],
                        'current_period_start' => Carbon::createFromTimestamp($stripeSubscription['current_period_start']),
                        'current_period_end' => Carbon::createFromTimestamp($stripeSubscription['current_period_end']),
                        'requested_custom' => $request->input('requested_custom') ?? false,
                        'pause_collection' => json_encode($stripeSubscription['pause_collection'] ?? null)
                    ];


                    // Insert subscription record
                    $subscription = Subscription::create($subscriptionData);


                    // Handle Subscription Items
                    foreach ($stripeSubscription['items']['data'] as $item) {
                        $pricingPlanPrice = PricingPlanPrice::where('stripe_id', $item['price']['id'])->first();

                        $subscriptionItemData = [
                            'subscription_id' => $subscription->id,
                            'item_id' => $pricingPlanPrice->id,
                            'stripe_subscription_id' => $item['subscription'],
                            'stripe_item_id' => $item['price']['id'],
                            'subscription_item_id' => $item['id'],
                        ];

                        SubscriptionItem::create($subscriptionItemData);
                    }

                    // credit FeatureUsageCredit with the usage credit about for the features of the plan
                    $planFeatures = DB::table('plan_features')
                        ->where('plan_features.plan_id', $pricingPlan->id)->get();

                    // each plan feature has  a usage credit, sum them up and add to FeatureUsageCredit
                    $featureUsageCredit = 0;

                    foreach ($planFeatures as $feature) {
                        $featureName = Feature::where('id', $feature->feature_id)->first()->name;

                        // Only add the usage credit if the feature is not one of the excluded ones
                        if ($featureName != 'Analytics & Reporting' && $featureName != 'Dashboard & Revenue') {
                            $featureUsageCredit += $feature->usage_credit;
                        }
                    }

                    $featureUsageCreditBalance = FeatureUsageCredit::create([
                        'venue_id' => $venue->id,
                        'balance' => $featureUsageCredit,
                    ]);

                    // add also history record
                    // credited_by_discovery_plan_monthly
                    FeatureUsageCreditHistory::create([
                        'feature_usage_credit_id' => $featureUsageCreditBalance->id,
                        'amount' => $featureUsageCredit,
                        'transaction_type' => 'increase',
                        'credited_by_discovery_plan_monthly' => true,
                        'used_at_feature' => 'none',
                    ]);
                }
            } catch (\Exception $e) {
                // do nothing
            }

            // update current_onboarding_step, onboarded set to true, and onboarded_completed_at set to now
            try {
                $this->mondayAutomationService->onboardProcess($venue->id, 'subscription_plan_selection');
            } catch (\Exception $e) {
                \Sentry\captureException($e);
                // do nothing
            }

        } else {
            try {
                $this->mondayAutomationService->onboardProcess($venue->id, 'subscription_plan_selection');
            } catch (\Exception $e) {
                \Sentry\captureException($e);
                // do nothing
            }
        }

        $potentialVenueLead = PotentialVenueLead::where('email', $request->input('email'))->first();
        $potentialVenueLead->current_onboarding_step = 'subscription_plan_selection';

        // complete also onboarding
        $potentialVenueLead->completed_onboarding = true;
        $potentialVenueLead->onboarded_completed_at = Carbon::now();

        $potentialVenueLead->save();


        // update referals and affilates logic if the user has a referal code or affiliate code, use try catch
        try {
            if ($potentialVenueLead->referral_code) {
                $potentialVenueLead->referral_status = 'registered';
                $potentialVenueLead->save();

                $restaurantReferral = RestaurantReferral::where('referral_code', $potentialVenueLead->referral_code)->first();
                $registerId = $restaurantReferral->register_id;
                $referrerId = $restaurantReferral->restaurant_id;

                // update wallet of respective users

                // update referrer wallet or credit usage based on use_referrals_for
                $reffererVenue = Restaurant::where('id', $referrerId)->first();
                $useReferralsFor = $reffererVenue->use_referrals_for;


                if ($useReferralsFor == 'wallet_balance') {
                    // credit the referrer wallet
                    $referrerWallet = VenueWallet::where('venue_id', $referrerId)->first();

                    // check if wallet exists
                    if (!$referrerWallet) {
                        $referrerWallet = VenueWallet::create([
                            'venue_id' => $referrerId,
                            'balance' => 5.00,
                        ]);

                        // add history record
                       WalletHistory::create([
                            'wallet_id' => $referrerWallet->id,
                            'amount' => 5.00,
                            'transaction_type' => 'increase',
                            'reason' => 'credited_by_referral',
                            'restaurant_referral_id' => $restaurantReferral->id,
                        ]);
                    }

                    else {
                        $referrerWallet->balance += 5.00;
                        $referrerWallet->save();

                        // add history record
                        WalletHistory::create([
                            'wallet_id' => $referrerWallet->id,
                            'amount' => 5.00,
                            'transaction_type' => 'increase',
                            'reason' => 'credited_by_referral',
                            'restaurant_referral_id' => $restaurantReferral->id,
                        ]);
                    }


                } else {

                    $checkIfFeatureUsageCreditExists = FeatureUsageCredit::where('venue_id', $referrerId)->first();
                    if($checkIfFeatureUsageCreditExists) {
                        $checkIfFeatureUsageCreditExists->balance += 5.00;
                        $checkIfFeatureUsageCreditExists->save();

                        // add also history record
                        FeatureUsageCreditHistory::create([
                            'feature_usage_credit_id' => $checkIfFeatureUsageCreditExists->id,
                            'amount' => 5.00,
                            'transaction_type' => 'increase',
                            'restaurant_referral_id' => $restaurantReferral->id,
                            'used_at_feature' => 'credited_by_referral',
                        ]);

                    } else {
                        $featureUsageCreditBalance = FeatureUsageCredit::create([
                            'venue_id' => $referrerId,
                            'balance' => 5.00,
                        ]);

                        // add also history record
                        FeatureUsageCreditHistory::create([
                            'feature_usage_credit_id' => $featureUsageCreditBalance->id,
                            'amount' => 5.00,
                            'transaction_type' => 'increase',
                            'restaurant_referral_id' => $restaurantReferral->id,
                            'used_at_feature' => 'credited_by_referral',
                        ]);
                    }
                }

                // create or update wallet for register id
                $checkIfWalletForRegisterExists = VenueWallet::where('venue_id', $registerId)->first();

                if($checkIfWalletForRegisterExists) {
                    $checkIfWalletForRegisterExists->balance += 5.00;
                    $checkIfWalletForRegisterExists->save();

                    // add also history record
                    WalletHistory::create([
                        'wallet_id' => $checkIfWalletForRegisterExists->id,
                        'amount' => 5.00,
                        'transaction_type' => 'increase',
                        'reason' => 'credited_by_referral',
                        'restaurant_referral_id' => $restaurantReferral->id,
                    ]);
                } else {
                    $walletForRegister = VenueWallet::create([
                        'venue_id' => $registerId,
                        'balance' => 5.00,
                    ]);

                    // add history record
                    WalletHistory::create([
                        'wallet_id' => $walletForRegister->id,
                        'amount' => 5.00,
                        'transaction_type' => 'increase',
                        'reason' => 'credited_by_referral',
                        'restaurant_referral_id' => $restaurantReferral->id,
                    ]);
                }

            }

            if ($potentialVenueLead->affiliate_code) {

                // get active subscription
                $potentialVenueLead->affiliate_status = 'registered';
                $potentialVenueLead->save();

                $venueFromACode = VenueAffiliate::where('affiliate_code', $potentialVenueLead->affiliate_code)->first();
                $affiliate = Affiliate::where('id', $venueFromACode->affiliate_id)->first();
                $affiliatePlan = AffiliatePlan::where('affiliate_id', $affiliate->id)->first();


                $activeSubscription = Subscription::with(['subscriptionItems.pricingPlanPrice', 'pricingPlan'])
                    ->where('venue_id', $venue->id)
                    ->where(function ($query) {
                        $query->where('status', 'active')
                            ->orWhere('status', 'trialing');
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();

                // check if affiliate plan has plan id or if plan id is null
                $creditAmount = 0;
                $planPrice = $activeSubscription ? $activeSubscription->subscriptionItems->first()->pricingPlanPrice->unit_amount : null;
                $subscriptionStatus = $activeSubscription->status;
                if ($subscriptionStatus !== 'trialing') {
                    if($affiliatePlan->plan_id !== $activeSubscription->pricingPlan->id && $affiliatePlan->plan_id !== null) {
                        $creditAmount = 0;
                    } else {

                        $creditAmount = 0;
                        // 'Fixed Percentage', 'Fixed Amount', 'Sliding Scale'
                        $affiliatePlanPreferredMethod = $affiliatePlan->preferred_method;
                        if ($affiliatePlanPreferredMethod === 'Fixed Percentage'){
                            $affiliatePlanPercentage = $affiliatePlan->percentage;
                            // credit for wallet define value
                            $creditAmount = $planPrice * $affiliatePlanPercentage / 100;

                        } else if ($affiliatePlanPreferredMethod === 'Fixed Amount') {
                            $affiliatePlanFixedAmount = $affiliatePlan->fixed_amount;
                            $creditAmount = $affiliatePlanFixedAmount;

                        } else if ($affiliatePlanPreferredMethod === 'Sliding Scale') {
                            // here compare number of users and credit accordingly
                            // count of affiliates
                            $affiliateFromAffiliatesCount = VenueAffiliate::where('affiliate_id', $affiliate->id)->get()->count();
                            // check customer interval start and customer interval end
                            // if it is between then credit based on percentage

                            $affiliatePlanSlidingScaleCStart = $affiliatePlan->customer_interval_start;
                            $affiliatePlanSlidingScaleCEnd = $affiliatePlan->customer_interval_end;

                            $affiliatePlanSlidingScalePercentage = $affiliatePlan->percentage;
                            // if it is between then credit based on percentage
                            $creditAmount = 0;
                            if ($affiliateFromAffiliatesCount >= $affiliatePlanSlidingScaleCStart && $affiliateFromAffiliatesCount <= $affiliatePlanSlidingScaleCEnd) {
                                $creditAmount = $planPrice * $affiliatePlanSlidingScalePercentage / 100;
                            }

                        }

                    }
                }



                $affiliateWallet = AffiliateWallet::where('affiliate_id', $affiliate->id)->first();
                // check if exists first, if yes then update balance if not then create
                if (!$affiliateWallet) {
                    $affiliateWallet = AffiliateWallet::create([
                        'affiliate_id' => $affiliate->id,
                        'balance' => $creditAmount,
                    ]);
                } else {
                    $affiliateWallet->balance += $creditAmount;
                    $affiliateWallet->save();
                }

                // add also history record
                AffiliateWalletHistory::create([
                    'affiliate_wallet_id' => $affiliateWallet->id,
                    'amount' => $creditAmount,
                    'transaction_type' => 'increase',
                    'affiliate_plan_id' => $affiliatePlan->id,
                    'registered_venue_id' => $venue->id,
                ]);



            }
        } catch (\Exception $e) {
            $this->logOnboardingError(
                $request->input('email'),
                'completeSubscriptionChosenDuringOnboarding',
                $e->getMessage(),
                $e->getTraceAsString()
            );
            \Sentry\captureException($e);
            // do nothing
        }

        // send completed onboarding email
        Mail::to($request->input('email'))->send(new CompletedPreOnboardingEmail(  $userExists->name ?? $userExists->first_name .' '. $userExists->last_name));

        // return success 200
        return response()->json(['message' => 'Subscription plan saved successfully'], 200);
    }

    public function convertToDiscover(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'venue_id' => 'required|exists:restaurants,id',
            'mode'=> 'nullable|string|in:monthly,yearly',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $venue = Restaurant::where('id', $request->input('venue_id'))->first();
        $userId = $venue->user_id;

        // check if the user has a stripe customer id

        $activeSubscription = Subscription::with(['subscriptionItems.pricingPlanPrice', 'pricingPlan'])
            ->where('venue_id', $venue->id)
            ->where(function ($query) {
                $query->where('status', 'active')
                    ->orWhere('status', 'trialing');
            })
            ->orderBy('created_at', 'desc')
            ->first();

        if ($activeSubscription) {
            return response()->json(['error' => 'You already have an active subscription'], 400);
        }

            $stripe = new \Stripe\StripeClient (
                config('services.stripe.key')
            );

            // find stripe product id in our database
            $freePlan = PricingPlan::where('name', 'Discover')
                ->where('active', 1)
                ->where('category', $venue->venueType->definition)->first();
            $freePlanStripeId = $freePlan->stripe_id;

            $product = $stripe->products->retrieve($freePlanStripeId, []);


            $stripeProductId = null;
            if ($product->id) {
                $stripeProductId = $product->id;
            }

            // $product = $stripe->products->retrieve($product_id, []);
            $prices = $stripe->prices->all([
                'product' => $stripeProductId,
                'active' => true, // Optional: Specify if you only want active prices.
                'limit' => 10 // Optional: Define how many results you want (up to 100).
            ]);

            $price_id = null;
            $mode = $request->input('mode') ?? 'monthly';
            try {
                foreach ($prices->data as $key => $price) {
                    if (($mode == 'monthly' && $price->recurring->interval == 'month') ||
                        ($mode == 'yearly' && $price->recurring->interval == 'year')
                    ) {
                        $price_id = $price->id;
                        break;
                    }
                }
                // create subscription
                if (!$venue->stripe_customer_id) {
                    $customer = $stripe->customers->create(
                        [
                            'email' => $venue->user->email,
                            'name' => $venue->user->name ?? $venue->user->first_name . ' ' . $venue->user->last_name,

                        ]
                    );
                    $venue->stripe_customer_id = $customer->id;
                    $venue->save();
                }

                $stripeSubscription = $stripe->subscriptions->create([
                    'customer' => $venue->stripe_customer_id,
                    'items' => [
                        [
                            'price' => $price_id,
                        ],
                    ],
                    // 'automatic_tax' => [
                        // 'enabled' => true,
                    // ],
                ]);

                if ($stripeSubscription->status == 'active') {
                    $pricingPlan = PricingPlan::where('stripe_id', $stripeSubscription['plan']['product'])->first();


                    $subscriptionData = [
                        'user_id' => $userId,
                        'venue_id' => $venue->id,
                        'pricing_plan_id' => $pricingPlan->id,
                        'pricing_plan_stripe_id' => $stripeSubscription['plan']['product'],
                        'stripe_subscription_id' => $stripeSubscription['id'],
                        'status' => $stripeSubscription['status'],
                        'trial_start' => $stripeSubscription['trial_start'] ? Carbon::createFromTimestamp($stripeSubscription['trial_start']) : null,
                        'trial_end' => $stripeSubscription['trial_end'] ? Carbon::createFromTimestamp($stripeSubscription['trial_end']) : null,
                        'trial_end_behavior' => $stripeSubscription['trial_settings']['end_behavior']['missing_payment_method'] ?? null,
                        'cancel_at_period_end' => $stripeSubscription['cancel_at_period_end'],
                        'automatic_tax_enabled' => $stripeSubscription['automatic_tax']['enabled'],
                        'billing_cycle_anchor' => $stripeSubscription['billing_cycle_anchor'],
                        'billing_thresholds' => json_encode($stripeSubscription['billing_thresholds']),
                        'cancel_at' => $stripeSubscription['cancel_at'] ? Carbon::createFromTimestamp($stripeSubscription['cancel_at']) : null,
                        'canceled_at' => $stripeSubscription['canceled_at'] ? Carbon::createFromTimestamp($stripeSubscription['canceled_at']) : null,
                        'cancellation_details' => json_encode($stripeSubscription['cancellation_details']),
                        'collection_method' => $stripeSubscription['collection_method'],
                        'currency' => $stripeSubscription['currency'],
                        'current_period_start' => Carbon::createFromTimestamp($stripeSubscription['current_period_start']),
                        'current_period_end' => Carbon::createFromTimestamp($stripeSubscription['current_period_end']),
                        'requested_custom' => $request->input('requested_custom') ?? false,
                        'pause_collection' => json_encode($stripeSubscription['pause_collection'] ?? null)
                    ];


                    // Insert subscription record
                    $subscription = Subscription::create($subscriptionData);


                    // Handle Subscription Items
                    foreach ($stripeSubscription['items']['data'] as $item) {
                        $pricingPlanPrice = PricingPlanPrice::where('stripe_id', $item['price']['id'])->first();

                        $subscriptionItemData = [
                            'subscription_id' => $subscription->id,
                            'item_id' => $pricingPlanPrice->id,
                            'stripe_subscription_id' => $item['subscription'],
                            'stripe_item_id' => $item['price']['id'],
                            'subscription_item_id' => $item['id'],
                        ];

                        SubscriptionItem::create($subscriptionItemData);
                    }

                    // credit FeatureUsageCredit with the usage credit about for the features of the plan
                    $planFeatures = DB::table('plan_features')
                        ->where('plan_features.plan_id', $pricingPlan->id)->get();

                    // each plan feature has  a usage credit, sum them up and add to FeatureUsageCredit
                    $featureUsageCredit = 0;

                    foreach ($planFeatures as $feature) {
                        $featureName = Feature::where('id', $feature->feature_id)->first()->name;

                        // Only add the usage credit if the feature is not one of the excluded ones
                        if ($featureName != 'Analytics & Reporting' && $featureName != 'Dashboard & Revenue') {
                            $featureUsageCredit += $feature->usage_credit;
                        }
                    }

                    $featureUsageCreditBalance = FeatureUsageCredit::create([
                        'venue_id' => $venue->id,
                        'balance' => $featureUsageCredit,
                    ]);

                    // add also history record
                    // credited_by_discovery_plan_monthly
                    FeatureUsageCreditHistory::create([
                        'feature_usage_credit_id' => $featureUsageCreditBalance->id,
                        'amount' => $featureUsageCredit,
                        'transaction_type' => 'increase',
                        'credited_by_discovery_plan_monthly' => true,
                        'used_at_feature' => 'none',
                    ]);
                }
            } catch (\Exception $e) {
                // do nothing
            }



        // return success 200
        return response()->json(['message' => 'Venue subscribed manually to saved successfully'], 200);
    }

    public function convertToNotDiscover(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'venue_id' => 'required|exists:restaurants,id',
            'mode'=> 'nullable|string|in:monthly,yearly',
            'plan' => 'required|string|in:Launch,Elevate,Optimize',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $venue = Restaurant::where('id', $request->input('venue_id'))->first();
        $userId = $venue->user_id;

        $activeSubscription = Subscription::with(['subscriptionItems.pricingPlanPrice', 'pricingPlan'])
            ->where('venue_id', $venue->id)
            ->where(function ($query) {
                $query->where('status', 'active')
                    ->orWhere('status', 'trialing');
            })
            ->orderBy('created_at', 'desc')
            ->first();

        if ($activeSubscription) {

            $planName = $activeSubscription->pricingPlan->name;

            if ($request->input('plan') === 'Launch' && $planName === 'Launch') {
                return response()->json(['error' => 'You already have an active subscription'], 400);
            }

            if ($request->input('plan') === 'Optimize' && $planName === 'Optimize') {
                return response()->json(['error' => 'You already have an active subscription'], 400);
            }

            if ($request->input('plan') === 'Elevate' && $planName === 'Elevate') {
                return response()->json(['error' => 'You already have an active subscription'], 400);
            }


            // Launch can be upgrade from Discover
            // Elevate can be upgrade from Launch
            // Optimize can be upgrade from Elevate
            if ($request->input('plan') === 'Launch' && $planName !== 'Discover') {
                return response()->json(['error' => 'You can only upgrade to Launch from Discover'], 400);
            }

            if ($request->input('plan') === 'Elevate' && $planName !== 'Launch') {
                return response()->json(['error' => 'You can only upgrade to Elevate from Launch'], 400);
            }

            if ($request->input('plan') === 'Optimize' && $planName !== 'Elevate') {
                return response()->json(['error' => 'You can only upgrade to Optimize from Elevate'], 400);
            }

            // deactivate the previous subscriptions
            // we need to iterate through all the subscriptions and deactivate them
            $subscriptions = Subscription::where('venue_id', $venue->id)
                ->where('status', 'active')
                ->orWhere('status', 'trialing')
                ->get();

            foreach ($subscriptions as $subscription) {
                $subscription->status = 'canceled';
                $subscription->save();
            }
        }


        $stripe = new \Stripe\StripeClient (
            config('services.stripe.key')
        );

        // find stripe product id in our database
        $freePlan = PricingPlan::where('name', $request->input('plan'))
            ->where('active', 1)
            ->where('category', $venue->venueType->definition)->first();
        $freePlanStripeId = $freePlan->stripe_id;


        $product = $stripe->products->retrieve($freePlanStripeId, []);


        $stripeProductId = null;
        if ($product->id) {
            $stripeProductId = $product->id;
        }

        // $product = $stripe->products->retrieve($product_id, []);
        $prices = $stripe->prices->all([
            'product' => $stripeProductId,
            'active' => true, // Optional: Specify if you only want active prices.
            'limit' => 10 // Optional: Define how many results you want (up to 100).
        ]);

        $price_id = null;
        $mode = $request->input('mode') ?? 'monthly';
        try {
            foreach ($prices->data as $key => $price) {
                if (($mode == 'monthly' && $price->recurring->interval == 'month') ||
                    ($mode == 'yearly' && $price->recurring->interval == 'year')
                ) {

                    $price_id = $price->id;
                    break;
                }
            }


            // create subscription
            if (!$venue->stripe_customer_id) {
                $customer = $stripe->customers->create(
                    [
                        'email' => $venue->user->email,
                        'name' => $venue->user->name ?? $venue->user->first_name . ' ' . $venue->user->last_name,

                    ]
                );
                $venue->stripe_customer_id = $customer->id;
                $venue->save();
            }

            $stripeSubscription = $stripe->subscriptions->create([
                'customer' => $venue->stripe_customer_id,
                'items' => [
                    [
                        'price' => $price_id,
                    ],
                ],
                // 'automatic_tax' => [
                // 'enabled' => true,
                // ],
                'collection_method' => 'send_invoice',
                'days_until_due'=> 90
            ]);


            if ($stripeSubscription->status == 'active') {
                $pricingPlan = PricingPlan::where('stripe_id', $stripeSubscription['plan']['product'])->first();


                $subscriptionData = [
                    'user_id' => $userId,
                    'venue_id' => $venue->id,
                    'pricing_plan_id' => $pricingPlan->id,
                    'pricing_plan_stripe_id' => $stripeSubscription['plan']['product'],
                    'stripe_subscription_id' => $stripeSubscription['id'],
                    'status' => $stripeSubscription['status'],
                    'trial_start' => $stripeSubscription['trial_start'] ? Carbon::createFromTimestamp($stripeSubscription['trial_start']) : null,
                    'trial_end' => $stripeSubscription['trial_end'] ? Carbon::createFromTimestamp($stripeSubscription['trial_end']) : null,
                    'trial_end_behavior' => $stripeSubscription['trial_settings']['end_behavior']['missing_payment_method'] ?? null,
                    'cancel_at_period_end' => $stripeSubscription['cancel_at_period_end'],
                    'automatic_tax_enabled' => $stripeSubscription['automatic_tax']['enabled'],
                    'billing_cycle_anchor' => $stripeSubscription['billing_cycle_anchor'],
                    'billing_thresholds' => json_encode($stripeSubscription['billing_thresholds']),
                    'cancel_at' => $stripeSubscription['cancel_at'] ? Carbon::createFromTimestamp($stripeSubscription['cancel_at']) : null,
                    'canceled_at' => $stripeSubscription['canceled_at'] ? Carbon::createFromTimestamp($stripeSubscription['canceled_at']) : null,
                    'cancellation_details' => json_encode($stripeSubscription['cancellation_details']),
                    'collection_method' => $stripeSubscription['collection_method'],
                    'currency' => $stripeSubscription['currency'],
                    'current_period_start' => Carbon::createFromTimestamp($stripeSubscription['current_period_start']),
                    'current_period_end' => Carbon::createFromTimestamp($stripeSubscription['current_period_end']),
                    'requested_custom' => $request->input('requested_custom') ?? false,
                    'pause_collection' => json_encode($stripeSubscription['pause_collection'] ?? null)
                ];


                // Insert subscription record
                $subscription = Subscription::create($subscriptionData);


                // Handle Subscription Items
                foreach ($stripeSubscription['items']['data'] as $item) {
                    $pricingPlanPrice = PricingPlanPrice::where('stripe_id', $item['price']['id'])->first();

                    $subscriptionItemData = [
                        'subscription_id' => $subscription->id,
                        'item_id' => $pricingPlanPrice->id,
                        'stripe_subscription_id' => $item['subscription'],
                        'stripe_item_id' => $item['price']['id'],
                        'subscription_item_id' => $item['id'],
                    ];

                    SubscriptionItem::create($subscriptionItemData);
                }

                // credit FeatureUsageCredit with the usage credit about for the features of the plan
                $planFeatures = DB::table('plan_features')
                    ->where('plan_features.plan_id', $pricingPlan->id)->get();

                // each plan feature has  a usage credit, sum them up and add to FeatureUsageCredit
                $featureUsageCredit = 0;

                foreach ($planFeatures as $feature) {
                    $featureName = Feature::where('id', $feature->feature_id)->first()->name;

                    // Only add the usage credit if the feature is not one of the excluded ones
                    if ($featureName != 'Analytics & Reporting' && $featureName != 'Dashboard & Revenue') {
                        $featureUsageCredit += $feature->usage_credit;
                    }
                }

                $featureUsageCreditBalance = FeatureUsageCredit::create([
                    'venue_id' => $venue->id,
                    'balance' => $featureUsageCredit,
                ]);

                // add also history record
                // credited_by_discovery_plan_monthly
                FeatureUsageCreditHistory::create([
                    'feature_usage_credit_id' => $featureUsageCreditBalance->id,
                    'amount' => $featureUsageCredit,
                    'transaction_type' => 'increase',
                    'credited_by_discovery_plan_monthly' => true,
                    'used_at_feature' => 'none',
                ]);
            }
        } catch (\Exception $e) {
//            dd($e->getMessage());
            // do nothing
        }



        // return success 200
        return response()->json(['message' => 'Venue subscribed manually to saved successfully'], 200);
    }

    public function convertWaitlistToLead(Request $request): \Illuminate\Http\JsonResponse
    {

        $validator = Validator::make($request->all(), [
           'waitlist_id' => 'required|exists:marketing_waitlists,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $waitlist = MarketingWaitlist::find($request->waitlist_id);

        // check if the waitlist is already converted

        $potentialVenueLeadExists = PotentialVenueLead::where('email', $waitlist->email)->first();

        if ($potentialVenueLeadExists) {
            return response()->json(['error' => 'Waitlist already converted'], 400);
        }

        $potentialVenueLead = new PotentialVenueLead();
        $potentialVenueLead->representative_first_name = explode(' ', $waitlist->full_name)[0]; // get first name (split by space
        // check if after exploding the name has more than one part
        if (count(explode(' ', $waitlist->full_name)) > 1) {
            $potentialVenueLead->representative_last_name = explode(' ', $waitlist->full_name)[1]; // get last name (split by space
        } else {
            $potentialVenueLead->representative_last_name = '-';
        }

        $potentialVenueLead->email = $waitlist->email;
        $potentialVenueLead->started_onboarding = true;
        $potentialVenueLead->current_onboarding_step = 'email_verified';
        $potentialVenueLead->email_verified = true;
        $potentialVenueLead->save();

        try {
            $this->mondayAutomationService->automateLeadCreation($potentialVenueLead);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            // do nothing
        }

        return response()->json(['message' => 'Waitlist converted to lead successfully']);

    }


    public function getStartedLeads(Request $request): \Illuminate\Http\JsonResponse
    {
        $excludedIds = [113, 114, 115, 116, 117, 118, 119, 120, 121, 122];
        $query = PotentialVenueLead::with([
            'venue',
            'venueCustomizedExperience',
            'venueLeadInfo',
            'venueAffiliate',
            'restaurantReferral',
            'affiliate',
            'promoCode',
            'referrer'
        ])->whereNotIn('id', $excludedIds)->where('from_september_new', true)
            ->orderBy('created_at', 'desc');

        // Add pagination
        $perPage = $request->input('per_page', 15); // Default to 15 items per page
        $potentialVenueLeads = $query->paginate($perPage);

        // Format created_at for each lead
        $formattedPotentialVenueLeads = $potentialVenueLeads->map(function ($potentialVenueLead) {
            $potentialVenueLead->created_at_formatted = $potentialVenueLead->created_at->format('Y-m-d H:i:s');
            $potentialVenueLead->venue_name = $potentialVenueLead->venue->name ?? null;
            return $potentialVenueLead;
        });

        return response()->json([
            'data' => $formattedPotentialVenueLeads,
            'pagination' => [
                'total' => $potentialVenueLeads->total(),
                'per_page' => $potentialVenueLeads->perPage(),
                'current_page' => $potentialVenueLeads->currentPage(),
                'last_page' => $potentialVenueLeads->lastPage(),
            ]
        ]);
    }

    // recommend pricing plan and return the pricing plan stripe ids specif of the food industry

    public function recommendPricingPlan(Request $request): \Illuminate\Http\JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $email = $request->input('email');


        try {
            $userId = User::where('email', $email)->first()->id;
            $venue = Restaurant::with(['addresses', 'venueCustomizedExperience'])->where('user_id', $userId)->first();

            $address = Address::with('country', 'state', 'city')->find(($venue->addresses[0]->id));

            $countryName = Country::where('id', $address->country_id)->first()->name;
            $stateName = State::where('id', $address->state_id)->first()->name;
            $cityName = City::where('id', $address->city_id)->first()->name;


            $potentialVenueLead = PotentialVenueLead::where('email', $email)->first();
            if (!$potentialVenueLead) {
                return response()->json(['message' => "No venue record found for the provided email. Please ensure you've entered the correct email address for the venue"], 404);
            }

            $venueIndustry = $venue->venueIndustry->name;

            $pricingPlans =
                PricingPlan::where('category', $venueIndustry === 'Sport & Entertainment' ? 'sport_entertainment' : $venueIndustry)
                    ->with('pricingPlanPrices')
                    ->where('is_custom', 0)
                    ->where('active', 1)
                    ->where('stripe_id', '!=', null)
                    ->get();

            $returnedPricingPlans = [];

            // create a combination of industry name and short name
            $venueIndustryCombinations = [
                'Food' => 'food',
                'Sport & Entertainment' => 'sport_entertainment',
                'Accommodation' => 'accommodation',
                'Retail' => 'retail',
            ];

            $industryShortName = $venueIndustryCombinations[$venueIndustry];

            // format returned pricing plans to return
            foreach ($pricingPlans as $plan) {

                $prices = $plan->pricingPlanPrices()->select('unit_amount', 'recurring', 'trial_period_days', 'stripe_id')->get();

                $planData = [
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'prices' => $prices
                ];

                $returnedPricingPlans[] = $planData;
            }

            $yearsInBusiness = $venue->years_in_business;
            $venueType =$venue->venueType->short_name;
            $venueIndustry = $venue->venueIndustry->name;
            $venueName = $venue->name;
            $venueCountry = $countryName;
            $venueCity = $stateName;
            $venueState = $cityName;
            $venueZipcode = $address->postcode;

            $venueCustomizeExperience = VenueCustomizedExperience::where('venue_id', $venue->id)->first();

            $venueEmployees = $venueCustomizeExperience->number_of_employees;
            $venueAnnualRevenue = $venueCustomizeExperience->annual_revenue;
            $venueHasWebsite = $venueCustomizeExperience->website === null ? $venueCustomizeExperience->website ? 'Yes' : 'No' : 'Not answered';
            $activeSocialMedia = $venueCustomizeExperience->social_media;
            $activHowdidYouhear = $venueCustomizeExperience->how_did_you_hear_about_us === 'Other' ? $venueCustomizeExperience->how_did_you_hear_about_us_other : $venueCustomizeExperience->how_did_you_hear_about_us;
            $activeBuinessChallenges = $venueCustomizeExperience->business_challenge === 'Other' ? $venueCustomizeExperience->other_business_challenge : $venueCustomizeExperience->business_challenge;
            $venueBiggestOperationChallenge = $venueCustomizeExperience->biggest_additional_change;
            $venueInterestedIn = $venueCustomizeExperience->contact_reason;


            $data = [
                "interestedVenueBusinessDetails" => [
                    "BusinessName" => $venueName,
                    "Industry" => $venueIndustry,
                    "BusinessType" => $venueType,
                    "YearsInBusiness" => $yearsInBusiness,
                    "Country" => $venueCountry,
                    "State" => $venueState,
                    "City" => $venueCity,
                    "ZipCode" => $venueZipcode,
                    "Employees" => $venueEmployees,
                    "AnnualRevenue" => $venueAnnualRevenue,
                    "HasWebsite" => $venueHasWebsite,
                    "ActiveSocialMedia" => $activeSocialMedia,
                    "HowDidYouHearAboutUs" => $activHowdidYouhear,
                    "BusinessChallenges" => $activeBuinessChallenges,
                    "InterestedIn" => $venueInterestedIn,
                    'BiggestOperationChallenge' => $venueBiggestOperationChallenge
                ],
                "venueBoostPricingDetails" => [
                    "Food & Beverage" => [
                        [
                            "PlanName" => "Discover",
                            "Pricing" => [
                                "Monthly" => "$0",
                                "Yearly" => "$0"
                            ],
                            "Features" => [
                                "Streamlined Reservations: Limited to 25 reservations per month.",
                                "Menu Management: Limited to 25 items per month.",
                                "Basic Inventory Management: Alerts for low stock levels, limited to 25 items",
                                "Basic Analytics & Reporting: Essential insights for small-scale operations.",
                                "Basic Guest Management: Manage up to 50 guest profiles",
                                "Basic Marketing Strategy: Limited to 1 campaign per month."
                            ]
                        ],
                        [
                            "PlanName" => "Launch",
                            "Pricing" => [
                                "Monthly" => "$49",
                                "Yearly" => "$490"
                            ],
                            "Features" => [
                                "Includes all from Discover plus:",
                                "Unlimited Streamlined Reservations",
                                "Full Inventory Management",
                                "Staff Management",
                                "Enhanced Marketing Strategy tools",
                                "Loyalty and Retention Program",
                                "Payment Links",
                                "Basic Guest Surveys and Ratings"
                            ]
                        ],
                        [
                            "PlanName" => "Elevate",
                            "Pricing" => [
                                "Monthly" => "$129",
                                "Yearly" => "$1290"
                            ],
                            "Features" => [
                                "Includes all from Launch plus:",
                                "Marketing Automation",
                                "Affiliate Partnerships",
                                "Advanced Analytics & Reporting",
                                "Delivery Orders management",
                                "Advanced Guest Behavior Analytics"
                            ]
                        ],
                        [
                            "PlanName" => "Optimize",
                            "Pricing" => [
                                "Monthly" => "$249",
                                "Yearly" => "$2490"
                            ],
                            "Features" => [
                                "Includes all from Elevate plus:",
                                "Premium Tables with Pricing / Bidding and Floorplan Visibility",
                                "Dining Guest Loyalty Program",
                                "Customizable Brand Profile",
                                "In-Person Payments",
                            ]
                        ],
                    ],
                    "Entertainment Venues" => [
                        [
                            "PlanName" => "Discover",
                            "Pricing" => [
                                "Monthly" => "$0",
                                "Yearly" => "$0"
                            ],
                            "Features" => [
                                "Bookings Management: Limit of 40 bookings per month.",
                                "Items Management: Limited to 40 items per month.",
                                "Inventory Management: Manage up to 40 inventory items.",
                                "Analytics & Reporting: Basic reports only.",
                                "Customer Management: Up to 80 customer profiles.",
                                "Marketing Tools: Limited to 1 campaign per month.",
                            ]
                        ],
                        [
                            "PlanName" => "Launch",
                            "Pricing" => [
                                "Monthly" => "$79",
                                "Yearly" => "$790"
                            ],
                            "Features" => [
                                "Includes all from Discover plus:",
                                "Unlimited Bookings Management",
                                "Full Inventory Management",
                                "Staff Management",
                                "Enhanced Marketing Tools",
                                "Basic Loyalty and Retention Program",
                                "Basic Payment Links",
                            ]
                        ],
                        [
                            "PlanName" => "Elevate",
                            "Pricing" => [
                                "Monthly" => "$169",
                                "Yearly" => "$1690"
                            ],
                            "Features" => [
                                "Includes all from Launch plus:",
                                "Marketing Automation",
                                "Entertainment Membership Program",
                                "Advanced Analytics & Reporting",
                                "Customer Surveys and Ratings",
                                "Basic Affiliate Partnerships",
                            ]
                        ],
                        [
                            "PlanName" => "Optimize",
                            "Pricing" => [
                                "Monthly" => "$329",
                                "Yearly" => "$3290"
                            ],
                            "Features" => [
                                "Includes all from Elevate plus:",
                                "Advanced Customer Behavior Analytics",
                                "Advanced Marketing Tools",
                                "Advanced Affiliate Partnerships",
                                "Customizable Brand Profile",
                                "In-Person Payments",
                            ]
                        ],
                    ],
                    "Accommodation" => [
                        [
                            "PlanName" => "Discover",
                            "Pricing" => [
                                "Monthly" => "$0",
                                "Yearly" => "$0"
                            ],
                            "Features" => [
                                "Bookings Management: Limit of 30 bookings per month.",
                                "Units/Rooms Management: Limited to 30 units/rooms per month.",
                                "Inventory Management: Manage up to 30 inventory items per month.",
                                "Items Management: Limited to 30 items per month.",
                                "Guest Management: Up to 75 guest profiles.",
                                "Marketing Tools: Limited to 1 campaign per month.",
                                "Analytics & Reporting: Basic reports only.",
                            ]
                        ],
                        [
                            "PlanName" => "Launch",
                            "Pricing" => [
                                "Monthly" => "$59",
                                "Yearly" => "$590"
                            ],
                            "Features" => [
                                "Includes all from Discover plus:",
                                "Unlimited Bookings Management",
                                "Unlimited Units/Rooms Management",
                                "Full Inventory Management",
                                "Staff Management",
                                "Enhanced Marketing Tools",
                                "Basic Loyalty and Retention Program",
                                "Payment Links",
                                "Basic Guest Surveys and Ratings"
                            ]
                        ],
                        [
                            "PlanName" => "Elevate",
                            "Pricing" => [
                                "Monthly" => "$149",
                                "Yearly" => "$1490"
                            ],
                            "Features" => [
                                "Includes all from Launch plus:",
                                "Marketing Automation including Guest Journey support",
                                "Advanced Analytics & Reporting",
                                "iCal Integration",
                                "Basic Affiliate Partnerships",
                                "Advanced Guest Behavior Analytics",
                            ]
                        ],
                        [
                            "PlanName" => "Optimize",
                            "Pricing" => [
                                "Monthly" => "$299",
                                "Yearly" => "$2990"
                            ],
                            "Features" => [
                                "All Elevate Plan features with higher limits",
                                "Accommodation Guest Loyalty Program",
                                "Housekeeping Management with Real-Time Updates\n",
                                "Advanced Affiliate Partnerships",
                                "Customizable Brand Profile",
                                "In-Person Payments",
                            ]
                        ],
                    ],
                    "Retail Management" => [
                        [
                            "PlanName" => "Discover",
                            "Pricing" => [
                                "Monthly" => "$0",
                                "Yearly" => "$0"
                            ],
                            "Features" => [
                                "Order Management: Limit of 50 orders per month.",
                                "Products Management: Up to 50 products.",
                                "Inventory Management: Manage up to 50 inventory items.",
                                "Dashboard & Revenue Analytics: Basic reports only.",
                                "Marketing Tools: Limited to 1 campaign per month.",
                                "Basic Store Management",
                            ]
                        ],
                        [
                            "PlanName" => "Launch",
                            "Pricing" => [
                                "Monthly" => "$69",
                                "Yearly" => "$690"
                            ],
                            "Features" => [
                                "Includes all from Discover plus",
                                "Unlimited Order Management",
                                "Full Inventory Management",
                                "Staff Management",
                                "Enhanced Marketing Tools",
                                "Basic Loyalty and Retention Program",
                            ]
                        ],
                        [
                            "PlanName" => "Elevate",
                            "Pricing" => [
                                "Monthly" => "$159",
                                "Yearly" => "$1590"
                            ],
                            "Features" => [
                                "Includes all from Launch plus:",
                                "Marketing Automation",
                                "Retail Customer Loyalty Program",
                                "Advanced Dashboard & Revenue Analytics",
                                "Consistent Inventory",
                                "Basic Affiliate Partnerships",
                                "Customer Surveys and Ratings"
                            ]
                        ],
                        [
                            "PlanName" => "Optimize",
                            "Pricing" => [
                                "Monthly" => "$319",
                                "Yearly" => "$3190"
                            ],
                            "Features" => [
                                "Includes all from Elevate plus:",
                                "Advanced Order Management",
                                "Advanced Customer Behavior Analytics",
                                "Centralized Analytics for Multi-Brand Retailers",
                                "Advanced Store Management",
                                "Customizable Brand Profile",
                                "In-Person Payments",
                            ]
                        ],

                    ],
                ]
            ];


            $conversation = [
                [
                    'role' => 'system',
                    'content' => 'Welcome! I will analyze the venue details and available pricing plans to suggest the most suitable pricing plan. Please provide the necessary information about the venue.',
                ],
                [
                    'role' => 'user',
                    'content' => 'Based on the information provided about the venue, including its industry, type, years in business, location, number of employees, annual revenue, website presence, active social media, and operational challenges, as well as the available pricing plans (Discover, Launch, Elevate, Optimize) for different industries (Food & Beverages, Entertainment Venues, Accommodation, Retail Management), could you suggest the most suitable pricing plan for this specific venue? Please provide the suggested plan in a single line. Format: Suggested plan: {name of plan}',
                ],
                [
                    'role' => 'assistant',
                    'content' => json_encode($data) // Convert the PHP array to JSON and include it as content
                ],
            ];

            // check if it has one reply with venue_id do not send request to openai but get the reply from db
            $venueLeadInfo = VenueLeadInfo::where('venue_id', $venue->id)->first();

            if ($venueLeadInfo) {
                return response()->json([
                    'recommended_pricing_plan' => 'Elevate',
                    // 'recommended_pricing_plan' => $venueLeadInfo->gpt_plan_suggested,
                    'onboarding_pricing_plans' => $returnedPricingPlans,
                    'industry' => $industryShortName
                ]);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4',
                'messages' => $conversation,
                'temperature' => 1,
                'max_tokens' => 256,
                'top_p'  => 1,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
            ]);
            if ($response->successful()) {
                $data = $response->json();
                $assistantReply = $data['choices'][0]['message']['content'];

                // save the response
                $venueLeadInfo = new VenueLeadInfo();
                $venueLeadInfo->venue_id = $venue->id;
                // parse from "Suggested plan: {name of plan}" and set it gpt_plan_suggested
                $venueLeadInfo->gpt_plan_suggested = explode(': ', $assistantReply)[1];
                $venueLeadInfo->assistant_reply = explode(': ', $assistantReply)[1];
                $venueLeadInfo->potential_venue_lead_id = $potentialVenueLead->id;
                $venueLeadInfo->industry = $venueIndustry;
                $venueLeadInfo->date_of_suggestion = Carbon::now();
                $venueLeadInfo->save();

                return response()->json([
                    'recommended_pricing_plan' => 'Elevate',
                    //'recommended_pricing_plan' => explode(': ', $assistantReply)[1],
                    //'recommended_pricing_plan' => $venueLeadInfo->gpt_plan_suggested,
                    'onboarding_pricing_plans' => $returnedPricingPlans,
                    'industry' => $industryShortName
                ]);
            } else {
                return response()->json([
                    'recommended_pricing_plan' => 'Elevate',
                    //'recommended_pricing_plan' => explode(': ', $assistantReply)[1],
                    //'recommended_pricing_plan' => $venueLeadInfo->gpt_plan_suggested,
                    'onboarding_pricing_plans' => $returnedPricingPlans,
                    'industry' => $industryShortName
                ]);
                // Handle the API request failure
                return response()->json(['error' => 'VB Assistant not responding. Try again in a bit.'], 500);
            }

        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    #[NoReturn] public static function sendPostOnboardingSurveyEmail($venueId){
        $venue = Restaurant::find($venueId);
        $user = User::where('id', $venue->user_id)->first();
        $venueExperience = VenueCustomizedExperience::where('venue_id', $venue->id)->first();

        $venueExperience->update([
            'post_onboarding_survey_email_sent_at' => Carbon::now()
        ]);

        Mail::to($user->email)->send(new PostOnboardingSurveyFeedbackEmail(  $user->name ?? $user->first_name .' '. $user->last_name));


    }

}

function generateStringShortCode($providerName) {
    $prefix = strtoupper(substr($providerName, 0, 3));
    $randomNumbers = sprintf('%04d', mt_rand(0, 9999));
    $suffix = 'SCD';
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomChar = $alphabet[rand(0, strlen($alphabet) - 1)];

    return $prefix . $randomNumbers . $suffix . $randomChar;
}

function generateStringAppKey($providerName) {
    $prefix = strtoupper(substr($providerName, 0, 3));
    $randomNumbers = sprintf('%04d', mt_rand(0, 9999));
    $suffix = 'APP';
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomChar = $alphabet[rand(0, strlen($alphabet) - 1)];

    return $prefix . $randomNumbers . $suffix . $randomChar;
}
