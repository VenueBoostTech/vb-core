<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\WcIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StoreIntegrationsController extends Controller
{

    public function wcConnection(Request $request): \Illuminate\Http\JsonResponse
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

        // Get WC id to ignore that row
        $wc_id = $venue->wcIntegration ? $venue->wcIntegration->id : 0;

        $validator = Validator::make($request->all(), [
            'consumer_key' =>  'required|string|unique:wc_integrations,consumer_key,'.$wc_id,
            'consumer_secret' => 'required|string',
            'consumer_wc_website' => 'required|string|unique:wc_integrations,consumer_wc_website,'.$wc_id
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Data to be inserted or updated
        $data = [
            'consumer_key' => $request->consumer_key,
            'consumer_secret' => $request->consumer_secret,
            'consumer_wc_website' => $request->consumer_wc_website,
        ];

        // Create or update the WC Integration
        WcIntegration::updateOrCreate(
            ['venue_id' => $venue->id],  // Condition: match a record with venue_id
            $data  // Columns to be updated or created
        );

        return response()->json(['message' => 'WC Integration successfully created or updated']);
    }

    // Method to get a single WC Integration
    public function getWcConnection(Request $request): \Illuminate\Http\JsonResponse
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

        $wcIntegration = WcIntegration::where('venue_id', $venue->id)->first();

        if (!$wcIntegration) {
            return response()->json(['error' => 'WC Integration not found'], 404);
        }

        return response()->json(['message' => 'WC Integration fetched successfully', 'data' => $wcIntegration]);
    }

    // Method to soft delete a WC Integration
    public function deleteWcConnection(Request $request): \Illuminate\Http\JsonResponse
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

        $wcIntegration = WcIntegration::where('venue_id', $venue->id)->first();

        if (!$wcIntegration) {
            return response()->json(['error' => 'WC Integration not found'], 404);
        }

        $wcIntegration->delete();  // Soft delete

        return response()->json(['message' => 'WC Integration successfully deleted']);
    }

}
