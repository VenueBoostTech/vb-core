<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\BusinessConfiguration;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BusinessSettingController extends Controller
{
    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function showEndOfDay(): JsonResponse
    {

        $settings = BusinessConfiguration::latest()->first();

        if (!$settings) {
            return response()->json([
                'error' => 'No business settings found.',
                'show_end_of_day_automatically' => false,
                'end_of_day_time' => null
            ], 404);
        }

        return response()->json([
            'message' => 'Business settings retrieved successfully.',
            'settings' => [
                'show_end_of_day_automatically' => $settings->show_end_of_day_automatically,
                'end_of_day_time' => $settings->end_of_day_time,
            ]
        ]);
    }

    public function storeEndOfDay(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $userID = auth()->user()->id;

        $validator = Validator::make($request->all(), [
            'show_end_of_day_automatically' => 'required|boolean',
            'end_of_day_time' => 'required|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $settings = BusinessConfiguration::latest()->first();
        if (!$settings) {
            $settings = new BusinessConfiguration();
        }

        $settings->user_id = $userID;
        $settings->show_end_of_day_automatically = $validator['show_end_of_day_automatically'];
        $settings->end_of_day_time = $validator['end_of_day_time'];

        $settings->save();

        return response()->json([
            'message' => ' saved successfully.',
            'settings' => $settings
        ]);
    }
}
