<?php

namespace App\Http\Controllers\v2;

use App\Enums\FeatureNaming;
use App\Http\Controllers\Controller;
use App\Mail\HygieneCheckAssignEmail;
use App\Models\ApiUsageHistory;
use App\Models\Feature;
use App\Models\HygieneCheck;
use App\Models\Restaurant;
use App\Services\ApiUsageLogger;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;


class AnalyticsController extends Controller
{
    public function topFeaturesByVenue($venueId, Request $request): \Illuminate\Http\JsonResponse
    {
        $limit = $request->input('limit', 10); // Default to 10 if not provided
        $type = $request->input('type', 'features'); // Default to 'features' if not provided

        if ($type === 'features') {
            $topFeatures = ApiUsageHistory::where('venue_id', $venueId)
                ->join('features', 'api_usage_history.feature_id', '=', 'features.id')
                ->select('features.id as feature_id', 'features.name', DB::raw('count(api_usage_history.feature_id) as total'))
                ->groupBy('features.id', 'features.name')
                ->orderBy('total', 'desc')
                ->take($limit)
                ->get();

            foreach ($topFeatures as $feature) {
                $subFeatures = ApiUsageHistory::where('venue_id', $venueId)
                    ->where('api_usage_history.feature_id', $feature->feature_id)
                    ->join('sub_features', 'api_usage_history.sub_feature_id', '=', 'sub_features.id')
                    ->select('sub_features.name', DB::raw('count(api_usage_history.sub_feature_id) as total'))
                    ->groupBy('sub_features.name')
                    ->orderBy('total', 'desc')
                    ->get();

                $feature->sub_features = $subFeatures;
            }

            return response()->json(['data' => $topFeatures]);
        } elseif ($type === 'sub_features') {
            $topSubFeatures = ApiUsageHistory::where('venue_id', $venueId)
                ->join('sub_features', 'api_usage_history.sub_feature_id', '=', 'sub_features.id')
                ->join('features', 'sub_features.feature_id', '=', 'features.id')
                ->select('sub_features.id as sub_feature_id', 'sub_features.name', 'features.name as parent_name', DB::raw('count(api_usage_history.sub_feature_id) as total'))
                ->groupBy('sub_features.id', 'sub_features.name', 'features.name')
                ->orderBy('total', 'desc')
                ->take($limit)
                ->get();

            return response()->json(['data' => $topSubFeatures]);
        } else {
            return response()->json(['error' => 'Invalid type parameter'], 400);
        }
    }

    public function topFeatures(Request $request): \Illuminate\Http\JsonResponse
    {
        $limit = $request->input('limit', 10);
        $type = $request->input('type', 'features');
        $industry = $request->input('industry', null); // New parameter for industry

        if ($type === 'features') {
            $query = ApiUsageHistory::join('features', 'api_usage_history.feature_id', '=', 'features.id')
                ->select('features.id as feature_id', 'features.name', 'features.feature_category', DB::raw('count(api_usage_history.feature_id) as total'))
                ->groupBy('features.id', 'features.name', 'features.feature_category')
                ->orderBy('total', 'desc');

            if ($industry) {
                $query->where('features.feature_category', $industry);
            }

            $topFeatures = $query->take($limit)->get();

            foreach ($topFeatures as $feature) {
                $subFeaturesQuery = ApiUsageHistory::where('api_usage_history.feature_id', $feature->feature_id)
                    ->join('sub_features', 'api_usage_history.sub_feature_id', '=', 'sub_features.id')
                    ->join('features as f', 'sub_features.feature_id', '=', 'f.id') // Additional join to access feature_category
                    ->select('sub_features.name', DB::raw('count(api_usage_history.sub_feature_id) as total'))
                    ->groupBy('sub_features.name');

                if ($industry) {
                    $subFeaturesQuery->where('f.feature_category', $industry); // Use 'f' alias for feature_category
                }

                $subFeaturesQuery->orderBy('total', 'desc');
                $feature->sub_features = $subFeaturesQuery->get();
            }

            return response()->json(['data' => $topFeatures]);
        } elseif ($type === 'sub_features') {
            $topSubFeatures = ApiUsageHistory::join('sub_features', 'api_usage_history.sub_feature_id', '=', 'sub_features.id')
                ->join('features', 'sub_features.feature_id', '=', 'features.id')
                ->select('sub_features.id as sub_feature_id', 'sub_features.name', 'features.name as parent_name', 'features.feature_category', DB::raw('count(api_usage_history.sub_feature_id) as total'))
                ->groupBy('sub_features.id', 'sub_features.name', 'features.id', 'features.name', 'features.feature_category')
                ->orderBy('total', 'desc');

            if ($industry) {
                $topSubFeatures->where('features.feature_category', $industry);
            }

            $topSubFeatures = $topSubFeatures->take($limit)->get();

            return response()->json(['data' => $topSubFeatures]);
        } else {
            return response()->json(['error' => 'Invalid type parameter'], 400);
        }
    }



    public function topVenues(Request $request): \Illuminate\Http\JsonResponse
    {
        $limit = $request->input('limit', 10); // Default to 10 if not provided

        $topVenues = ApiUsageHistory::with('restaurant')
            ->select('venue_id', DB::raw('count(*) as total'))
            ->groupBy('venue_id')
            ->orderBy('total', 'desc')
            ->take($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'venue_id' => $item->venue_id,
                    'total' => $item->total,
                    'venue_name' => $item->restaurant ? $item->restaurant->name : null
                ];
            });

        return response()->json([
            'data' => $topVenues
        ]);
    }


}
