<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// Data behaviour and event tracking
class DBETrackingController extends Controller
{

    public function getMixpanelEvents(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date',
            'to_date' => 'required|date',
            'distinct_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $baseUrl = env('MIXPANEL_BASE_URL');
        $projectId = env('MIXPANEL_PROJECT_ID');
        $distinctIds = json_encode([$request->distinct_id]);
        $fromDate = $request->from_date;
        $toDate = $request->to_date;
        $authorization = 'Basic ' . base64_encode(env('MIXPANEL_USERNAME') . ':' . env('MIXPANEL_SECRET'));

        $apiUrl = $baseUrl . 'stream/query';

        $client = new Client();

        try {
            $response = $client->get($apiUrl, [
                'query' => [
                    'project_id' => $projectId,
                    'distinct_ids' => $distinctIds,
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $authorization,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            return response()->json(['message' => 'Data fetched successfully', 'data' => $data], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch data', 'details' => $e->getMessage()], 400);
        }
    }
}
