<?php

namespace App\Http\Controllers\TrackMaster;

use App\Http\Controllers\Controller;
use App\Models\PotentialVenueLead;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingAnalyticsController extends Controller
{
    private function getDateFilteredQuery(Request $request)
    {
        $query = PotentialVenueLead::query();
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        return $query;
    }

    public function getOverview(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = $this->getDateFilteredQuery($request);

        $totalLeads = $query->count();
        $completedOnboarding = $query->where('completed_onboarding', true)->count();

        return response()->json([
            'total_counts' => [
                'total_leads' => $totalLeads,
                'total_venues' => $query->whereNotNull('venue_id')->count(),
                'completed_onboarding' => $completedOnboarding,
            ],
            'conversion_rate' => $totalLeads > 0 ? ($completedOnboarding / $totalLeads) * 100 : 0,
            'chatbot_and_september_leads' => [
                'chatbot_leads' => $query->where('from_chatbot', true)->count(),
                'september_new_leads' => $query->where('from_september_new', true)->count(),
            ],
        ]);
    }

    public function getStepAnalysis(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = $this->getDateFilteredQuery($request);

        $stepCounts = $query->groupBy('current_onboarding_step')
            ->selectRaw('current_onboarding_step, count(*) as count')
            ->pluck('count', 'current_onboarding_step');

        $averageTimePerStep = $query->selectRaw('
        current_onboarding_step,
        AVG(TIMESTAMPDIFF(HOUR, created_at,
            CASE
                WHEN completed_onboarding = 1 THEN onboarded_completed_at
                ELSE NOW()
            END
        )) as avg_hours
    ')
            ->groupBy('current_onboarding_step')
            ->get()
            ->mapWithKeys(function ($item) {
                $days = $item->avg_hours / 24;
                return [
                    $item->current_onboarding_step => [
                        'value' => number_format($days, 2, '.', ''),
                        'unit' => 'days'
                    ]
                ];
            });

        $dropoutRates = $this->calculateDropoutRates($stepCounts, $query->count());

        return response()->json([
            'step_counts' => $stepCounts,
            'average_time_per_step' => $averageTimePerStep,
            'dropout_rates' => $dropoutRates,
        ]);
    }

    public function getAcquisitionAnalysis(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = $this->getDateFilteredQuery($request);

        return response()->json([
            'source_analysis' => $query->groupBy('source')
                ->selectRaw('source, count(*) as count')
                ->pluck('count', 'source'),
            'affiliate_analysis' => $query->whereNotNull('affiliate_id')
                ->groupBy('affiliate_id')
                ->selectRaw('affiliate_id, count(*) as count')
                ->pluck('count', 'affiliate_id'),
            'referral_analysis' => $query->whereNotNull('referer_id')
                ->groupBy('referer_id')
                ->selectRaw('referer_id, count(*) as count')
                ->pluck('count', 'referer_id'),
            'promo_code_analysis' => $query->whereNotNull('promo_code_id')
                ->groupBy('promo_code_id')
                ->selectRaw('promo_code_id, count(*) as count')
                ->pluck('count', 'promo_code_id'),
        ]);
    }

    private function calculateDropoutRates($stepCounts, $totalLeads): array
    {
        $dropoutRates = [];
        $steps = [
            'initial_form_submitted',
            'email_verified',
            'business_details',
            'interest_engagement',
            'subscription_plan_selection'
        ];

        $previousCount = $totalLeads;
        foreach ($steps as $step) {
            $currentCount = $stepCounts[$step] ?? 0;
            $dropoutRate = $previousCount > 0 ? (($previousCount - $currentCount) / $previousCount) * 100 : 0;
            $dropoutRates[$step] = [
                'value' => round($dropoutRate, 2),
                'unit' => 'percent',
                'description' => "Percentage of leads that dropped out before reaching this step",
                'total_reached' => $currentCount,
                'total_from_previous' => $previousCount
            ];
            $previousCount = $currentCount;
        }
        return $dropoutRates;
    }


    public function getOnboardingSteps(): \Illuminate\Http\JsonResponse
    {
        $steps = [
            'initial_form_submitted',
            'email_verified',
            'business_details',
            'interest_engagement',
            'subscription_plan_selection'
        ];

        return response()->json(['steps' => $steps]);
    }

    public function getIndustryAnalysis(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = PotentialVenueLead::query();

        $industryAnalysis = $query->join('restaurants', 'potential_venue_leads.venue_id', '=', 'restaurants.id')
            ->join('venue_industries', 'restaurants.venue_industry', '=', 'venue_industries.id')
            ->groupBy('venue_industries.name')
            ->selectRaw('venue_industries.name, count(*) as count')
            ->when($request->input('start_date') && $request->input('end_date'), function ($query) use ($request) {
                return $query->whereBetween('potential_venue_leads.created_at', [$request->input('start_date'), $request->input('end_date')]);
            })
            ->pluck('count', 'name');

        return response()->json(['industry_analysis' => $industryAnalysis]);
    }

    public function getConversionTimeline(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = $this->getDateFilteredQuery($request);

        $conversionTimeline = $query->where('completed_onboarding', true)
            ->groupBy(DB::raw('DATE(onboarded_completed_at)'))
            ->selectRaw('DATE(onboarded_completed_at) as date, count(*) as count')
            ->orderBy('date')
            ->pluck('count', 'date');

        return response()->json(['conversion_timeline' => $conversionTimeline]);
    }

    public function getSubscriptionAnalysis(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = $this->getDateFilteredQuery($request);

        $subscriptionData = $query->join('restaurants', 'potential_venue_leads.venue_id', '=', 'restaurants.id')
            ->join('subscriptions', 'restaurants.id', '=', 'subscriptions.venue_id')
            ->join('pricing_plans', 'subscriptions.pricing_plan_id', '=', 'pricing_plans.id')
            ->select(
                'pricing_plans.name as plan_name',
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, potential_venue_leads.created_at, subscriptions.created_at)) as avg_time_to_subscribe')
            )
            ->groupBy('pricing_plans.name')
            ->get();

        return response()->json(['subscription_analysis' => $subscriptionData]);
    }

    public function getErrorAnalysis(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = $this->getDateFilteredQuery($request);

        $errorData = DB::table('onboarding_errors')
            ->leftJoin('potential_venue_leads', 'onboarding_errors.potential_venue_lead_id', '=', 'potential_venue_leads.id')
            ->select(
                'onboarding_errors.step',
                'onboarding_errors.error_type',
                DB::raw('COUNT(*) as error_count'),
                DB::raw('COUNT(DISTINCT COALESCE(onboarding_errors.potential_venue_lead_id, onboarding_errors.email)) as affected_leads')
            )
            ->groupBy('onboarding_errors.step', 'onboarding_errors.error_type')
            ->get();

        $validationErrorData = DB::table('onboarding_errors')
            ->where('error_type', 'validation')
            ->select(
                'step',
                DB::raw('COUNT(*) as error_count'),
                DB::raw('JSON_EXTRACT(validation_errors, "$") as fields')
            )
            ->groupBy('step', 'fields')
            ->get()
            ->map(function ($item) {
                $item->fields = json_decode($item->fields);
                return $item;
            });

        $leadsWithErrors = $query->whereHas('onboardingErrors')->count();
        $conversionRateWithErrors = $leadsWithErrors > 0
            ? ($query->whereHas('onboardingErrors')->where('completed_onboarding', true)->count() / $leadsWithErrors) * 100
            : 0;

        return response()->json([
            'error_analysis' => $errorData,
            'validation_error_analysis' => $validationErrorData,
            'conversion_rate_with_errors' => $conversionRateWithErrors,
            'total_leads_with_errors' => $leadsWithErrors
        ]);
    }


    public function getUserEngagementRate(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Restaurant::query();
        $totalVenues = $query->count();
        $activeVenues = $query->whereHas('apiUsages', function ($query) {
            $query->where('created_at', '>=', now()->subDays(30));
        })->count();

        $engagementRate = $totalVenues > 0 ? ($activeVenues / $totalVenues) * 100 : 0;

        return response()->json([
            'engagement_rate' => $engagementRate,
            'active_venues' => $activeVenues,
            'total_venues' => $totalVenues
        ]);
    }

    public function getFeatureAdoptionRate(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Restaurant::query();
        $totalVenues = $query->count();

        $featureAdoption = $query->withCount(['apiUsages as feature_count' => function ($query) {
            $query->select(DB::raw('COUNT(DISTINCT feature_id)'));
        }])->get()->pluck('feature_count', 'id');

        $adoptionRates = [
            'high' => $featureAdoption->filter(function ($count) { return $count > 5; })->count(),
            'medium' => $featureAdoption->filter(function ($count) { return $count > 2 && $count <= 5; })->count(),
            'low' => $featureAdoption->filter(function ($count) { return $count <= 2; })->count(),
        ];

        return response()->json([
            'adoption_rates' => $adoptionRates,
            'total_venues' => $totalVenues
        ]);
    }

    public function getRevenueGrowth(Request $request): \Illuminate\Http\JsonResponse
    {
        $revenueGrowth = Restaurant::join('subscriptions', 'restaurants.id', '=', 'subscriptions.venue_id')
            ->join('subscription_items', 'subscriptions.id', '=', 'subscription_items.subscription_id')
            ->join('pricing_plans_prices', 'subscription_items.item_id', '=', 'pricing_plans_prices.id')
            ->select(
                DB::raw('DATE_FORMAT(subscriptions.created_at, "%Y-%m") as month'),
                DB::raw('SUM(pricing_plans_prices.unit_amount) as revenue')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json(['revenue_growth' => $revenueGrowth]);
    }

    public function getChurnPrediction(Request $request): \Illuminate\Http\JsonResponse
    {
        $totalVenues = Restaurant::count();
        $atRiskVenues = Restaurant::whereDoesntHave('apiUsages', function ($query) {
            $query->where('created_at', '>=', now()->subDays(30));
        })->count();

        $churnRate = $totalVenues > 0 ? ($atRiskVenues / $totalVenues) * 100 : 0;

        return response()->json([
            'at_risk_venues' => $atRiskVenues,
            'total_venues' => $totalVenues,
            'churn_rate' => $churnRate
        ]);
    }

    public function getIndustryComparison(Request $request): \Illuminate\Http\JsonResponse
    {
        $industryComparison = Restaurant::join('venue_industries', 'restaurants.venue_industry', '=', 'venue_industries.id')
            ->select('venue_industries.name',
                DB::raw('COUNT(restaurants.id) as venue_count'),
                DB::raw('AVG(venue_customized_experience.number_of_employees) as avg_employees'),
                DB::raw('AVG(venue_customized_experience.annual_revenue) as avg_revenue'))
            ->join('venue_customized_experience', 'restaurants.id', '=', 'venue_customized_experience.venue_id')
            ->groupBy('venue_industries.name')
            ->get();

        return response()->json(['industry_comparison' => $industryComparison]);
    }

    public function getVenuePerformance(Request $request): \Illuminate\Http\JsonResponse
    {
        $venuePerformance = Restaurant::select('id', 'name')
            ->withCount('apiUsages')
            ->with(['venueCustomizedExperience:venue_id,number_of_employees,annual_revenue', 'venueConfiguration:venue_id,onboarding_completed'])
            ->get()
            ->map(function ($venue) {
                return [
                    'id' => $venue->id,
                    'name' => $venue->name,
                    'api_usage_count' => $venue->api_usages_count,
                    'unique_features_used' => $venue->apiUsages()->distinct('feature_id')->count(),
                    'employees' => $venue->venueCustomizedExperience->number_of_employees ?? 0,
                    'annual_revenue' => $venue->venueCustomizedExperience->annual_revenue ?? 0,
                    'onboarding_completed' => $venue->venueConfiguration->onboarding_completed ?? false,
                ];
            });

        return response()->json(['venue_performance' => $venuePerformance]);
    }
}
