<?php
namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Models\PlanFeature;
use App\Models\PotentialVenueLead;
use App\Models\PricingPlan;
use App\Models\PricingPlanPrice;
use App\Models\Restaurant;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Stripe\Plan;
use function response;

/**
 * @OA\Info(
 *   title="Pricing Plans API",
 *   version="1.0",
 *   description="This API allows use Pricing Plans Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Pricing Plans",
 *   description="Operations related to Pricing Plans"
 * )
 */


class PricingPlansController extends Controller
{
    /**
     * @OA\Get(
     *     path="/pricing-plans",
     *     tags={"Pricing Plans"},
     *     summary="Retrieves a list of all pricing plans",
     *     @OA\Response(response="200", description="Successful operation"),
     * )
     */
    public function index()
    {
        return PricingPlan::all();
    }

    /**
     * @OA\Get(
     *     path="/pricing-plans/{id}",
     *     tags={"Pricing Plans"},
     *     summary="Retrieves a specific pricing plan by ID",
     *     @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response="200", description="Successful operation"),
     *     @OA\Response(response="404", description="Pricing plan not found"),
     * )
     */
    public function show($id)
    {
        $plan = PricingPlan::find($id);
        if (!$plan) {
            return response()->json(['error' => 'Pricing plan not found'], 404);
        }
        return $plan;
    }

    /**
     * @OA\Post(
     *     path="/pricing-plans",
     *     tags={"Pricing Plans"},
     *     summary="Allows staff to create a new pricing plan",
     *     @OA\RequestBody(
     *          required=true,
     *          description="Pricing plan details",
     *     @OA\JsonContent(
     *     required={"name", "monthly_cost", "yearly_cost", "currency", "features"},
     *     @OA\Property(property="name", type="string", example="Basic"),
     *     @OA\Property(property="monthly_cost", type="number", example=10),
     *     @OA\Property(property="yearly_cost", type="number", example=100),
     *     @OA\Property(property="currency", type="string", example="USD"),
     *     @OA\Property(property="features", type="array", @OA\Items(type="string"), example={"feature1", "feature2"}),
     *     ),
     *     ),
     *     @OA\Response(response="201", description="Successfully created"),
     *     @OA\Response(response="422", description="Validation error"),
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:pricing_plans',
            'monthly_cost' => 'required|numeric',
            'yearly_cost' => 'required|numeric',
            'currency' => 'required',
            'features' => 'required|array'
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        $plan = PricingPlan::create($request->all());
        return response()->json($plan, 201);
    }

    /**
     * @OA\Put(
     *   path="/pricing-plans/{id}",
     *   tags={"Pricing Plans"},
     *   summary="Update an existing pricing plan",
     *   @OA\RequestBody(
     *       required=true,
     *       description="Pricing plan data",
     *   @OA\MediaType(
     *       mediaType="application/json",
     *   @OA\Schema(
     *      type="object",
     *      required={
     *          "name", "monthly_cost", "yearly_cost", "currency", "features"
     *      },
     *   @OA\Property(
     *     property="name",
     *     type="string",
     *    description="Name of the pricing plan"
     *   ),
     *   @OA\Property(
     *       property="monthly_cost",
     *       type="number",
     *       description="Cost of the pricing plan per month"
     *   ),
     *   @OA\Property(
     *       property="yearly_cost",
     *       type="number",
     *       description="Cost of the pricing plan per year"
     *   ),
     * @OA\Property(
     *   property="currency",
     *   type="string",
     *   description="Currency of the pricing plan"
     *  ),
     * @OA\Property(
     *   property="features",
     *   type="array",
     *   @OA\Items(
     *       type="string",
     *       description="Features of the pricing plan"
     *   )
     *   )
     *   )
     *   )
     *   ),
     * @OA\Parameter(
     *   name="id",
     *   in="path",
     *   required=true,
     *   description="ID of the pricing plan",
     *   @OA\Schema(
     *       type="integer"
     *   )
     *   ),
     *   @OA\Response(
     *       response=200,
     *       description="Successfully updated pricing plan"
     *   ),
     *   @OA\Response(
     *   response=404,
     *   description="Pricing plan not found"
     *   ),
     *  @OA\Response(
     *  response=422,
     *   description="Validation error"
     * )
     * )
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'monthly_cost' => 'numeric',
            'yearly_cost' => 'numeric',
            'currency' => 'string|max:3',
            'features' => 'array'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $pricingPlan = PricingPlan::find($id);
        if (!$pricingPlan) {
            return response()->json(['error' => 'Pricing plan not found'], 404);
        }

        $pricingPlan->name = $request->input('name', $pricingPlan->name);
        $pricingPlan->monthly_cost = $request->input('monthly_cost', $pricingPlan->monthly_cost);
        $pricingPlan->yearly_cost = $request->input('yearly_cost', $pricingPlan->yearly_cost);
        $pricingPlan->currency = $request->input('currency', $pricingPlan->currency);
        $pricingPlan->features = $request->input('features', $pricingPlan->features);
        $pricingPlan->save();

        return response()->json($pricingPlan, 200);
    }

    /**
     * @OA\Delete(
     *     path="/pricing-plans/{id}",
     *     summary="Delete a specific pricing plan",
     *     tags={"Pricing Plans"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Pricing plan not found"
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Invalid HTTP Method"
     *     )
     * )
     */
    public function destroy($id)
    {
        $pricingPlan = PricingPlan::find($id);
        if (!$pricingPlan) {
            return response()->json([
                'error' => 'Pricing plan not found'
            ], 404);
        }
        $pricingPlan->delete();
        return response()->json(null, 204);
    }


    public function createCheckoutSession(Request $request): \Illuminate\Http\JsonResponse
    {

        try {
            $product_id = $request->input('plan_id');
            $mode = $request->input('mode');
            $VenueShortCode = $request->input('venue_short_code');

            if (!$VenueShortCode) {
                return response()->json(['error' => 'Venue short code is required'], 400);
            }

            $venue = auth()->user()->restaurants->where('short_code', $VenueShortCode)->first();
            if (!$venue) {
                return response()->json(['error' => 'Venue not found'], 404);
            }

            $stripe = new \Stripe\StripeClient (
                config('services.stripe.key')
            );

            // $product = $stripe->products->retrieve($product_id, []);
            $prices = $stripe->prices->all([
                'product' => $product_id,
                'active' => true, // Optional: Specify if you only want active prices.
                'limit' => 10 // Optional: Define how many results you want (up to 100).
            ]);

            $price_id = null;
            foreach ($prices->data as $key => $price) {
                if (($mode == 'month' && $price->recurring->interval == 'month') ||
                    ($mode == 'year' && $price->recurring->interval == 'year')
                ) {
                    $price_id = $price->id;
                    break;
                }
            }

            if (!$venue->stripe_customer_id) {
                $customer = $stripe->customers->create();
                $venue->stripe_customer_id = $customer->id;
                $venue->save();
            }

            $checkout_session = $stripe->checkout->sessions->create([
              'line_items' => [[
                'price' => $price_id,
                'quantity' => 1,
              ]],
              'customer' => $venue->stripe_customer_id,
              'mode' => 'subscription',
              'automatic_tax' => [
                'enabled' => true,
              ],
              'customer_update[address]' => 'auto',
              'success_url' =>  config('services.stripe.admin_redirect_url').'/'.$VenueShortCode. '/admin/settings/subscription?success=true&session_id={CHECKOUT_SESSION_ID}',
              'cancel_url' =>  config('services.stripe.admin_redirect_url').'/'.$VenueShortCode. '/admin/settings/subscription?canceled=true',
            ]);

            return response()->json([
                'url' => $checkout_session->url,
            ], 200);

          } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
          }
    }


    public function createCheckoutSessionForOnboarding(Request $request): \Illuminate\Http\JsonResponse
    {

        try {
            $priceId = $request->input('price_id');
            $email = $request->input('email');

            if (!$priceId) {
                return response()->json(['error' => 'Price id is required'], 400);
            }

            $venue = null;
            $userRetrieved = User::where('email', $email)->first();
            if ($userRetrieved) {
                $venue =  Restaurant::where('user_id',  $userRetrieved->id)->first();
            }

            if (!$venue) {
                return response()->json(['error' => 'Venue not found'], 404);
            }


            $pricingPlanPrice = PricingPlanPrice::where('stripe_id', $priceId)->first();
            if (!$pricingPlanPrice) {
                return response()->json(['error' => 'Pricing plan not found'], 404);
            }

            $trial_period_days = $pricingPlanPrice->trial_period_days;


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

            $stripe = new \Stripe\StripeClient (
                config('services.stripe.key')
            );


            if (!$venue->stripe_customer_id) {
                $customer = $stripe->customers->create(
                [
                        'email' => $venue->user->email,
                        'name' =>  $venue->user->name ?? $venue->user->first_name . ' ' . $venue->user->last_name

                    ]
                );
                $venue->stripe_customer_id = $customer->id;
                $venue->save();
            }

            $checkout_session = $stripe->checkout->sessions->create([
              'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
              ]],
              'customer' => $venue->stripe_customer_id,
              'mode' => 'subscription',
              'automatic_tax' => [
                'enabled' => true,
              ],
                'subscription_data' => [
                    'trial_settings' => ['end_behavior' => ['missing_payment_method' => 'cancel']],
                    'trial_period_days' => $trial_period_days,
                ],
              'allow_promotion_codes' => true,
              'payment_method_collection' => 'always',
              'customer_update[address]' => 'auto',
              'billing_address_collection' => 'auto',
              'success_url' =>  config('services.stripe.web_redirect_url').'/onboarding/'.$jwt_token. '?success=true&session_id={CHECKOUT_SESSION_ID}',
              'cancel_url' =>  config('services.stripe.web_redirect_url').'/onboarding/'.$jwt_token. '?canceled=true',
            ]);

            return response()->json([
                'url' => $checkout_session->url,
            ], 200);

          } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
          }
    }

    // populate food features
    public function populateFoodFeatures(Request $request): \Illuminate\Http\JsonResponse
    {
        $foodCategory = 'food';
        $features = DB::table('features')
            ->where('feature_category', $foodCategory)
            ->where('active', 1)
            ->where('plan_restriction', 1)
            ->get();


        $pricingPlans =
            PricingPlan::where('category', $foodCategory)
                ->with('pricingPlanPrices')
                ->where('is_custom', 0)
                ->where('active', 1)
                ->where('stripe_id', '!=', null)
                ->get();

        foreach ($features as $feature) {
            foreach ($pricingPlans as $plan) {
                if ($plan->name == 'Discover' && $plan->active == 1) {
                    if ($feature->name == 'Reservations') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 25,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 25,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Menu Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 25,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Guest Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 50,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Analytics & Reporting') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }
                }

                if ($plan->name == 'Launch' && $plan->active == 1) {
                    if ($feature->name == 'Reservations') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Menu Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Guest Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'enhanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Analytics & Reporting') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Staff Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Loyalty and Retention') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Payment Links') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Guest Surveys and Ratings') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }
                }

                if ($plan->name == 'Elevate' && $plan->active == 1) {
                    if ($feature->name == 'Reservations') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Menu Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Guest Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'enhanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Analytics & Reporting') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'advanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Staff Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Loyalty and Retention') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Payment Links') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Guest Surveys and Ratings') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Affiliate Partnerships') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Delivery Orders Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Advanced Guest Behavior Analytics') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Automation') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }
                }

                if ($plan->name == 'Optimize' && $plan->active == 1) {
                    if ($feature->name == 'Reservations') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Menu Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Guest Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'enhanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Analytics & Reporting') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'advanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Staff Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Loyalty and Retention') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Payment Links') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Guest Surveys and Ratings') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Affiliate Partnerships') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Delivery Orders Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Advanced Guest Behavior Analytics') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Automation') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Customizable Brand Profile') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Premium Tables with Bidding / Pricing') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Dining Guest Loyalty Program') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'In-Person Payments') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }
                }
            }
        };


        $plansWithFeatures = [];

        foreach ($pricingPlans as $plan) {
            $planData = [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                // Add other plan attributes as needed
                'features' => []
            ];

            foreach ($features as $feature) {
                $planFeature = PlanFeature::where('plan_id', $plan->id)
                    ->where('feature_id', $feature->id)
                    ->first();

                if ($planFeature) {
                    $planData['features'][] = [
                        'feature_id' => $feature->id,
                        'feature_name' => $feature->name,
                        'usage_credit' => $planFeature->usage_credit,
                        'used_in_plan' => $planFeature->used_in_plan,
                        'unlimited_usage_credit' => $planFeature->unlimited_usage_credit,
                        'feature_level' => $planFeature->feature_level,
                        // Add other feature attributes as needed
                    ];
                }
            }

            $plansWithFeatures[] = $planData;
        }


        return response()->json([
            'message' => 'Food features populated',
            'plans' => $plansWithFeatures
        ], 200);


    }

    // populate accommodation features
    public function populateAccommodationFeatures(Request $request): \Illuminate\Http\JsonResponse
    {
        $accommodationCategory = 'accommodation';
        $features = DB::table('features')
            ->where('feature_category', $accommodationCategory)
            ->where('active', 1)
            ->where('plan_restriction', 1)
            ->get();


        $pricingPlans =
            PricingPlan::where('category', $accommodationCategory)
                ->with('pricingPlanPrices')
                ->where('is_custom', 0)
                ->where('active', 1)
                ->where('stripe_id', '!=', null)
                ->get();


        foreach ($features as $feature) {
            foreach ($pricingPlans as $plan) {
                if ($plan->name == 'Discover' && $plan->active == 1) {
                    if ($feature->name == 'Bookings Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 30,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Units/Rooms Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 30,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 30,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Items Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 30,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Guests Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 75,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Analytics & Reporting') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }
                }

                if ($plan->name == 'Launch' && $plan->active == 1) {
                    if ($feature->name == 'Bookings Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Units/Rooms Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Items Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Guests Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'enhanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Analytics & Reporting') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Staff Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Loyalty and Retention') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Payment Links') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Guest Surveys and Ratings') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }
                }

                if ($plan->name == 'Elevate' && $plan->active == 1) {
                    if ($feature->name == 'Bookings Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Units/Rooms Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Items Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Guests Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'enhanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Analytics & Reporting') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'advanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Staff Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Loyalty and Retention') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Payment Links') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Guest Surveys and Ratings') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Affiliates Partnerships') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'iCal Integration') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Advanced Customer Behavior Analytics') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Automation') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }
                }

                if ($plan->name == 'Optimize' && $plan->active == 1) {
                    if ($feature->name == 'Bookings Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Units/Rooms Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Items Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Guests Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'enhanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Analytics & Reporting') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'advanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Staff Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Loyalty and Retention') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Payment Links') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Guest Surveys and Ratings') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Affiliates Partnerships') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'advanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'iCal Integration') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Advanced Customer Behavior Analytics') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Automation') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Customizable Brand Profile') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Housekeeping') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Accommodation Guest Loyalty Program') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'In Person Payments') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }
                }
            }
        };


        $plansWithFeatures = [];

        foreach ($pricingPlans as $plan) {
            $planData = [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                // Add other plan attributes as needed
                'features' => []
            ];

            foreach ($features as $feature) {
                $planFeature = PlanFeature::where('plan_id', $plan->id)
                    ->where('feature_id', $feature->id)
                    ->first();

                if ($planFeature) {
                    $planData['features'][] = [
                        'feature_id' => $feature->id,
                        'feature_name' => $feature->name,
                        'usage_credit' => $planFeature->usage_credit,
                        'used_in_plan' => $planFeature->used_in_plan,
                        'unlimited_usage_credit' => $planFeature->unlimited_usage_credit,
                        'feature_level' => $planFeature->feature_level,
                        // Add other feature attributes as needed
                    ];
                }
            }

            $plansWithFeatures[] = $planData;
        }


        return response()->json([
            'message' => 'Accommodation features populated',
            'plans' => $plansWithFeatures
        ], 200);


    }

    // populate retail features
    public function populateRetailFeatures(Request $request): \Illuminate\Http\JsonResponse
    {
        $retailCategory = 'retail';
        $features = DB::table('features')
            ->where('feature_category', $retailCategory)
            ->where('active', 1)
            ->where('plan_restriction', 1)
            ->get();


        $pricingPlans =
            PricingPlan::where('category', $retailCategory)
                ->with('pricingPlanPrices')
                ->where('is_custom', 0)
                ->where('active', 1)
                ->where('stripe_id', '!=', null)
                ->get();

        foreach ($features as $feature) {
            foreach ($pricingPlans as $plan) {
                if ($plan->name == 'Discover' && $plan->active == 1) {
                    if ($feature->name == 'Orders Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 50,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 50,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Products Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 50,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Store Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Dashboard & Revenue') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }
                }

                if ($plan->name == 'Launch' && $plan->active == 1) {
                    if ($feature->name == 'Orders Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Products Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Store Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'enhanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Dashboard & Revenue') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Staff Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Loyalty and Retention') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                }

                if ($plan->name == 'Elevate' && $plan->active == 1) {

                    if ($feature->name == 'Orders Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Products Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Store Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'enhanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Dashboard & Revenue') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'advanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Staff Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Loyalty and Retention') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Customer Surveys and Ratings') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Affiliates Partnerships') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Consistent Inventory') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Automation') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Retail Customer Loyalty Program') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }
                }

                if ($plan->name == 'Optimize' && $plan->active == 1) {

                    if ($feature->name == 'Orders Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'advanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Products Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Store Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'advanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'enhanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Dashboard & Revenue') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'advanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Staff Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Loyalty and Retention') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Customer Surveys and Ratings') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Affiliates Partnerships') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Consistent Inventory') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Automation') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Retail Customer Loyalty Program') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Advanced Customer Behavior Analytics') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Customizable Brand Profile') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Centralized Analytics for Multi-Brand Retailers') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'In Person Payments') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }
                }
            }
        };


        $plansWithFeatures = [];

        foreach ($pricingPlans as $plan) {
            $planData = [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                // Add other plan attributes as needed
                'features' => []
            ];

            foreach ($features as $feature) {
                $planFeature = PlanFeature::where('plan_id', $plan->id)
                    ->where('feature_id', $feature->id)
                    ->first();

                if ($planFeature) {
                    $planData['features'][] = [
                        'feature_id' => $feature->id,
                        'feature_name' => $feature->name,
                        'usage_credit' => $planFeature->usage_credit,
                        'used_in_plan' => $planFeature->used_in_plan,
                        'unlimited_usage_credit' => $planFeature->unlimited_usage_credit,
                        'feature_level' => $planFeature->feature_level,
                        // Add other feature attributes as needed
                    ];
                }
            }

            $plansWithFeatures[] = $planData;
        }


        return response()->json([
            'message' => 'Retail features populated',
            'plans' => $plansWithFeatures
        ], 200);


    }

    // populate sport_entertainment features
    public function populateSportEntertainmentFeatures(Request $request): \Illuminate\Http\JsonResponse
    {
        $sportEntertainmentCategory = 'sport_entertainment';
        $features = DB::table('features')
            ->where('feature_category', $sportEntertainmentCategory)
            ->where('active', 1)
            ->where('plan_restriction', 1)
            ->get();


        $pricingPlans =
            PricingPlan::where('category', $sportEntertainmentCategory)
                ->with('pricingPlanPrices')
                ->where('is_custom', 0)
                ->where('active', 1)
                ->where('stripe_id', '!=', null)
                ->get();

        foreach ($features as $feature) {
            foreach ($pricingPlans as $plan) {
                if ($plan->name == 'Discover' && $plan->active == 1) {
                    if ($feature->name == 'Bookings Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 40,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 40,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Items Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 40,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Customers Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 80,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Analytics & Reporting') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }
                }

                if ($plan->name == 'Launch' && $plan->active == 1) {
                    if ($feature->name == 'Bookings Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 40,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 40,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Items Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 40,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Customers Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 80,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'enhanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Analytics & Reporting') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Staff Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Loyalty and Retention') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Payment Links') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }


                }

                if ($plan->name == 'Elevate' && $plan->active == 1) {

                    if ($feature->name == 'Bookings Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 40,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 40,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Items Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 40,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Customers Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 80,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'enhanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Analytics & Reporting') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'advanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Staff Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Loyalty and Retention') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Payment Links') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Guest Surveys and Ratings') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'advanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Affiliate Partnerships') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Automation') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Entertainment Membership Program') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }
                }

                if ($plan->name == 'Optimize' && $plan->active == 1) {

                    if ($feature->name == 'Bookings Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 40,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Inventory Management') {

                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 40,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Items Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 40,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                            ]);
                        }
                    }

                    if ($feature->name == 'Customers Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 80,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Strategy') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'advanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Analytics & Reporting') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 1,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 0,
                                'feature_level' => 'advanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Staff Management') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Loyalty and Retention') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Payment Links') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'basic'
                            ]);
                        }
                    }

                    if ($feature->name == 'Guest Surveys and Ratings') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'advanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Affiliate Partnerships') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                                'feature_level' => 'advanced'
                            ]);
                        }
                    }

                    if ($feature->name == 'Marketing Automation') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Entertainment Membership Program') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Advanced Customer Behavior Analytics') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'Customizable Brand Profile') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }

                    if ($feature->name == 'In Person Payment') {
                        // check first if the feature is already in the plan
                        $planFeature = PlanFeature::where('plan_id', $plan->id)
                            ->where('feature_id', $feature->id)
                            ->first();

                        if (!$planFeature) {
                            DB::table('plan_features')->insert([
                                'plan_id' => $plan->id,
                                'feature_id' => $feature->id,
                                'usage_credit' => 0,
                                'used_in_plan' => 1,
                                'unlimited_usage_credit' => 1,
                            ]);
                        }
                    }
                }
            }
        };


        $plansWithFeatures = [];

        foreach ($pricingPlans as $plan) {
            $planData = [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                // Add other plan attributes as needed
                'features' => []
            ];

            foreach ($features as $feature) {
                $planFeature = PlanFeature::where('plan_id', $plan->id)
                    ->where('feature_id', $feature->id)
                    ->first();

                if ($planFeature) {
                    $planData['features'][] = [
                        'feature_id' => $feature->id,
                        'feature_name' => $feature->name,
                        'usage_credit' => $planFeature->usage_credit,
                        'used_in_plan' => $planFeature->used_in_plan,
                        'unlimited_usage_credit' => $planFeature->unlimited_usage_credit,
                        'feature_level' => $planFeature->feature_level,
                        // Add other feature attributes as needed
                    ];
                }
            }

            $plansWithFeatures[] = $planData;
        }


        return response()->json([
            'message' => 'Entertainment Venues features populated',
            'plans' => $plansWithFeatures
        ], 200);


    }


    public function confirmSubscription(Request $request): \Illuminate\Http\JsonResponse
    {

        try {
            $session_id = $request->input('session_id');
            $VenueShortCode = $request->input('venue_short_code');

            if (!$VenueShortCode) {
                return response()->json(['error' => 'Venue short code is required'], 400);
            }

            $venue = auth()->user()->restaurants->where('short_code', $VenueShortCode)->first();
            if (!$venue) {
                return response()->json(['error' => 'Venue not found'], 404);
            }

            $stripe = new \Stripe\StripeClient (
                config('services.stripe.key')
            );

            $session = $stripe->checkout->sessions->retrieve($session_id, []);
            $subscription = $stripe->subscriptions->retrieve(
                $session->subscription,
                []
            );

            $venue->subscription_id = $subscription->id;
            $venue->plan_type = $subscription->plan->interval;
            $venue->plan_id = $subscription->plan->product;
            $venue->save();

            return response()->json([
                'success' => true,
                'subscription_id' => $venue->subscription_id,
                'plan_type' => $venue->plan_type,
                'plan_id' => $venue->plan_id,
            ], 200);
          } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
          }
    }

    public function confirmSubscriptionForOnboarding(Request $request): \Illuminate\Http\JsonResponse
    {

        try {
            $session_id = $request->input('session_id');

            $stripe = new \Stripe\StripeClient (
                config('services.stripe.key')
            );

            $session = $stripe->checkout->sessions->retrieve($session_id, []);
            $stripeSubscription = $stripe->subscriptions->retrieve(
                $session->subscription,
                []
            );

            $customer = $stripe->customers->retrieve($stripeSubscription->customer, []);

            $userId = User::where('email', $customer->email)->first()->id;

            $venue = Restaurant::where('user_id', $userId)->first();



            if ($stripeSubscription->status == 'trialing') {
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
            }

            $potentialVenueLead = PotentialVenueLead::where('email', $customer->email)->first();
            $potentialVenueLead->current_onboarding_step = 'subscription_plan_selection';

            // complete also onboarding
            $potentialVenueLead->completed_onboarding = true;
            $potentialVenueLead->onboarded_completed_at = Carbon::now();

            $potentialVenueLead->save();

            return response()->json([
                'success' => true,
                'email' => $customer->email,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public static function syncStripeProducts() {

        // Define trial periods for each plan and category combination
        $trialPeriods = [
            'food' => [
                'Launch' => 14,
                'Elevate' => 14,
                'Optimize' => 30,
            ],
            'sport_entertainment' => [
                'Launch' => 14,
                'Elevate' => 14,
                'Optimize' => 30,
            ],
            'retail' => [
                'Launch' => 14,
                'Elevate' => 14,
                'Optimize' => 30,
            ],
            'accommodation' => [
                'Launch' => 14,
                'Elevate' => 14,
                'Optimize' => 30,
            ],
        ];

        $stripe = new \Stripe\StripeClient (
            config('services.stripe.key')
        );

        $products = $stripe->products->all([
            'limit' => 100
        ]);

        foreach ($products->data as $stripeProduct) {

            // Check if the product has the 'industry' metadata
            if ($stripeProduct->metadata['industry'] || isset($stripeProduct->metadata['is_freemium']) !== null) {
                // Check if the product exists in our database
                $localProduct = PricingPlan::where('stripe_id', $stripeProduct->id)->first();

                if (!$localProduct) {
                    // Product doesn't exist locally, create a new record
                    $localProduct = new PricingPlan();
                    $localProduct->stripe_id = $stripeProduct->id;
                }

                // Update local product attributes based on Stripe data
                $localProduct->name = $stripeProduct->name;
                $localProduct->active = $stripeProduct->active; // Update other attributes as needed
                $localProduct->description = $stripeProduct->description;
                $localProduct->unit_label = $stripeProduct->unit_label;
                $localProduct->monthly_cost = 0;
                $localProduct->yearly_cost = 0;
                $localProduct->currency = 'USD';

                // generate short code based on name and make it unique even though product name can be the same
                $localProduct->short_code = Str::slug($stripeProduct->name, '-').'-'.Str::random(5);



                // Assign the 'industry' metadata value to the 'category' field
                $localProduct->category = $stripeProduct->metadata['industry'] === 'Sport & Entertainment' ? 'sport_entertainment' : $stripeProduct->metadata['industry'];
                $localProduct->is_freemium = $stripeProduct->metadata['is_freemium'] === 'true';

                $localProduct->save();

                // $product = $stripe->products->retrieve($product_id, []);
                $stripePrices = $stripe->prices->all([
                    'product' => $localProduct->stripe_id,
                    'active' => true, // Optional: Specify if you only want active prices.
                    'limit' => 10 // Optional: Define how many results you want (up to 100).
                ]);

                // Handle prices for this product
                foreach ($stripePrices as $stripePrice) {
                    // Check if the price exists in your local database
                    $localPrice = PricingPlanPrice::where('stripe_id', $stripePrice->id)->first();

                    if (!$localPrice) {
                        // Price doesn't exist locally, create a new record
                        $localPrice = new PricingPlanPrice();
                        $localPrice->stripe_id = $stripePrice->id;
                    }

                    // Update local price attributes based on Stripe data
                    $localPrice->active = $stripePrice->active;
                    $localPrice->billing_scheme = $stripePrice->billing_scheme;
                    $localPrice->currency = $stripePrice->currency;
                    $localPrice->custom_unit_amount = $stripePrice->custom_unit_amount;

                    $localPrice->pricing_plan_id = $localProduct->id;
                    $localPrice->stripe_product_id = $localProduct->stripe_id;
                    $localPrice->recurring = $stripePrice->recurring;

                    $localPrice->tax_behavior = $stripePrice->tax_behavior;
                    $localPrice->type = $stripePrice->type;
                    $localPrice->unit_amount = $stripePrice->unit_amount;
                    $localPrice->unit_amount_decimal = $stripePrice->unit_amount_decimal;

                    // Determine the trial period based on category and plan name
                    $category = strtolower($localProduct->category);
                    $planName = $localProduct->name;

                    if (isset($trialPeriods[$category][$planName])) {
                        $trialPeriod = $trialPeriods[$category][$planName];
                        $localPrice->trial_period_days = $trialPeriod;
                    } else {
                        $localPrice->trial_period_days = null; // default value if no specific trial period is set
                    }

                    $localPrice->save();
                }
            }
        }

        // Mark deleted products and prices as inactive
        PricingPlan::whereNotIn('stripe_id', collect($products->data)->pluck('id'))->update(['active' => false]);
        PricingPlan::whereNotIn('stripe_id', collect($products->data)->pluck('prices.data.*.id'))->update(['active' => false]);

        // log
        Log::info('Stripe data sync completed.');
    }
}

