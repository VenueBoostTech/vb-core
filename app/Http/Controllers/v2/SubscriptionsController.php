<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\FeatureUsageCredit;
use App\Models\FeatureUsageCreditHistory;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\VenueCustomPricingContact;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use stdClass;

class SubscriptionsController extends Controller
{

    public function subscriptionPlansWithNewFeatureRelationships(Request $request): \Illuminate\Http\JsonResponse
    {

        $freemiumPlans = DB::table('pricing_plans')
            ->join('plan_features', 'pricing_plans.id', '=', 'plan_features.plan_id')
            ->join('features', 'plan_features.feature_id', '=', 'features.id')
            ->whereNotNull('pricing_plans.category') // Filter out entries with empty category
            ->whereNotNull('features.feature_category')
            ->select(
                'pricing_plans.name as plan_name',
                'pricing_plans.category as plan_category',
                'pricing_plans.short_code as plan_short_code',
                'features.name as feature_name',
                'features.link as feature_link',
                'plan_features.usage_credit',
                'plan_features.whitelabel_access',
                'plan_features.allow_vr_ar'
            )
            ->get();

        $formattedData = [];

        foreach ($freemiumPlans as $row) {
            $key = $row->plan_name . '_' . $row->plan_category . '_' . $row->plan_short_code;
            if (!isset($formattedData[$key])) {
                $formattedData[$key] = [
                    'plan_name' => $row->plan_name,
                    'plan_category' => $row->plan_category,
                    'plan_short_code' => $row->plan_short_code,
                    'features' => [],
                ];
            }

            $formattedData[$key]['features'][] = [
                'feature_name' => $row->feature_name,
                'feature_link' => $row->feature_link,
                'usage_credit' => $row->usage_credit,
                'whitelabel_access' => $row->whitelabel_access,
                'allow_vr_ar' => $row->allow_vr_ar,
            ];
        }


        return response()->json([
            'message' => 'success',
            'data' => $formattedData,
        ], 201);
    }

    public function getSubscription(Request $request): \Illuminate\Http\JsonResponse
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

        $activeSubscription = Subscription::with(['subscriptionItems.pricingPlanPrice', 'pricingPlan'])
            ->where('venue_id', $venue->id)
            ->where(function ($query) {
                $query->where('status', 'active')
                    ->orWhere('status', 'trialing');
            })
            ->orderBy('created_at', 'desc')
            ->first();
        $planName = $activeSubscription?->pricingPlan->name;
        $planCycle = $activeSubscription ? $activeSubscription->subscriptionItems->first()->pricingPlanPrice->recurring['interval'] : null;
        $planPrice = $activeSubscription ? $activeSubscription->subscriptionItems->first()->pricingPlanPrice->unit_amount : null;
        $planCurrency = $activeSubscription ? $activeSubscription->subscriptionItems->first()->pricingPlanPrice->currency : null;

        $subscriptionPlan = new stdClass;
        $subscriptionPlan->name = $planName;
        $subscriptionPlan->price = $planPrice;
        $subscriptionPlan->currency = $planCurrency;
        $subscriptionPlan->recurring = $planCycle ? $planCycle === 'month' ? 'Monthly' : 'Yearly' : null;

        // check if the subscription is in trial mode, now is in between the trial period trial_start and trial_end
        $now = Carbon::now();
        $trialStart = $activeSubscription?->trial_start;
        $trialEnd = $activeSubscription?->trial_end;
        $isTrialMode = $now->between($trialStart, $trialEnd);

        $featuresWithCreditUsedAndRemaining = [];
        $planFeatures = PlanFeature::where('plan_id', $activeSubscription->pricing_plan_id)->get();

        // do for each but without the feature with name = Store Management
        foreach ($planFeatures as $planFeature) {

            $feature = Feature::where('id', $planFeature->feature_id)->first();

            // Skip the loop iteration if feature name is "Store Management"
            if ($feature && $feature->name == "Store Management") {
                continue;
            }

            $returnFeature = new stdClass();
            $featureName = Feature::where('id', $planFeature->feature_id)->first()->name;
            $returnFeature->name = $featureName;
            $returnFeature->usage_credit = $planFeature->usage_credit;
            $featureUsageCreditHistoryCount = FeatureUsageCreditHistory::where('feature_id', $planFeature->feature_id)->get()->count();
            $returnFeature->credit_used = $featureUsageCreditHistoryCount;
            $featuresWithCreditUsedAndRemaining[] = $returnFeature;
        }
        $subscription = new stdClass;
        $subscription->is_trial_mode = $isTrialMode;
        $subscription->is_active = (bool)$activeSubscription;
        $subscription->features = $featuresWithCreditUsedAndRemaining;
        $subscription->trial_end = isset($trialEnd) ? (new \DateTime($trialEnd))->format('F d, Y') : null;
        $subscription->cancel_at = isset($activeSubscription->cancel_at) ? (new \DateTime($activeSubscription->cancel_at))->format('F d, Y') : null;
        $subscription->next_billing_date = $planName !== 'Discover' && isset($activeSubscription->current_period_end) ? (new \DateTime($activeSubscription->current_period_end))->format('F d, Y') : null;

        $subscription->plan =  $activeSubscription ? $subscriptionPlan : null;

        return response()->json([
            'message' => 'Subscription retrieved successfully',
            'data' => $subscription
        ], 200);
    }

    public function customPricingContactSalesAdminRequest(Request $request): \Illuminate\Http\JsonResponse
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

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'contact_email' => 'required|email',
            'phone' => 'nullable|string',
            'how_can_help_you' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $contact = new VenueCustomPricingContact();
        $contact->venue_id = $venue->id;
        $contact->first_name = $request->get('first_name');
        $contact->last_name = $request->get('last_name');
        $contact->email = $request->get('contact_email');
        $contact->phone = $request->get('phone');
        $contact->how_can_help_you = $request->get('how_can_help_you');
        $contact->save();

        return response()->json([
            'message' => 'success',
            'data' => $contact
        ], 201);

    }

}
