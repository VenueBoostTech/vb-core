<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;

class SurveysController extends Controller
{

    public function getTemplates(): JsonResponse
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


        $client = new Client();
        $baseUrl = env('SURVEYMONKEY_BASE_URL');
        $bearerToken = env('SURVEYMONKEY_BEARER_TOKEN');

        try {
            $response = $client->get($baseUrl . 'survey_templates', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $bearerToken,
                ],
            ]);

            $templates = json_decode($response->getBody(), true);

            return response()->json(['message' => 'Templates fetched successfully', 'templates' => $templates], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch templates', 'details' => $e->getMessage()], 400);
        }
    }
}
