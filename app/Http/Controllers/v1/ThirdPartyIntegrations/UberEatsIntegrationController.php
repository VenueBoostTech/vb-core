<?php

namespace App\Http\Controllers\v1\ThirdPartyIntegrations;
use App\Http\Controllers\Controller;
use App\Models\UberEatsIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Info(
 *   title="UberEats Integrations API",
 *   version="1.0",
 *   description="This API allows use of UberEats Integrations Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="UberEatsIntegration",
 *   description="Operations related to UberEatsIntegration"
 * )
 */
class UberEatsIntegrationController extends Controller
{
    /**
     * @OA\Post(
     *     path="/third-party-integrations/ubereats/add-integration",
     *     operationId="addUberEatsIntegration",
     *     tags={"UberEatsIntegration"},
     *     summary="Integrate VenueBoost Restaurant with Ubereats Restaurant",
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="customer_id", type="string"),
     *             @OA\Property(property="client_id", type="string"),
     *             @OA\Property(property="client_secret", type="string"),
     *         )
     *     ),
     *     @OA\Response(response="200", description="Success"),
     *     @OA\Response(response="400", description="Bad Request")
     * )
     */
    public function addIntegration(Request $request): \Illuminate\Http\JsonResponse
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
            'customer_id' => 'required|string|min:5',
            'client_id' => 'required|string|min:5',
            'client_secret' => 'required|string|min:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Check if there is an active integration already
        $existingIntegration = UberEatsIntegration::where('restaurant_id', $venue->id)
            ->where('customer_id', $request->input('customer_id'))
            ->where('client_id', $request->input('client_id'))
            ->where('client_secret', $request->input('client_secret'))
            ->whereNull('disconnected_at')
            ->first();

        if ($existingIntegration) {
            return response()->json(['error' => 'An active integration already exists for this restaurant'], 400);
        }

        // Prepare the request parameters
        $params = [
            'client_id' => $request->input('client_id'),
            'client_secret' => $request->input('client_secret'),
            'grant_type' => 'client_credentials',
            'scope' => 'eats.deliveries',
            // TODO: after v1 testing change the scope maybe to something else when we have production access from UberEats
        ];

        // Send the POST request to obtain the access token
        $response = Http::asForm()->post('https://login.uber.com/oauth/v2/token', $params);

        // TODO: after v1 testing we don't need access token for now, but maybe we will need it later
        if ($response->failed()) {
            return response()->json(['error' => 'Failed to connect to UberEats API'], 500);
        }

        // Create the integration
        $integration = new UberEatsIntegration();
        $integration->restaurant_id = $venue->id;
        $integration->customer_id = $request->input('customer_id');
        $integration->client_id = $request->input('client_id');
        $integration->client_secret = $request->input('client_secret');
        $integration->save();

        // Create the integration

        return response()->json(['message' => 'Integration added successfully']);
    }

    /**
     * @OA\Delete(
     *     path="/third-party-integrations/ubereats/delete-integration/{id}",
     *     operationId="disconnectUberEatsIntegration",
     *     tags={"UberEatsIntegration"},
     *     summary="Disconnect VenueBoost Restaurant Integration Ubereats Restaurant",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Integration ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response="200", description="Integration disconnected successfully"),
     *     @OA\Response(response="404", description="Integration not found"),
     *     @OA\Response(response="500", description="Internal server error")
     * )
     */
    public function disconnectIntegration($id): \Illuminate\Http\JsonResponse
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

        $integration = UberEatsIntegration::where('restaurant_id', $venue->id)->find($id);

        if (!$integration) {
            return response()->json(['error' => 'Integration not found'], 404);
        }

        $integration->update(['disconnected_at' => now()]);

        return response()->json(['message' => 'Integration disconnected successfully']);
    }



}
