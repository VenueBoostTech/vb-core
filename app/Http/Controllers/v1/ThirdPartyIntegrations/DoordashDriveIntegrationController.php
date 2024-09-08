<?php

namespace App\Http\Controllers\v1\ThirdPartyIntegrations;
use App\Http\Controllers\Controller;
use App\Models\DeliveryProviderRestaurant;
use App\Models\DoordashIntegration;
use App\Models\DoordashJwt;
use App\Models\Order;
use App\Models\OrderDelivery;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Info(
 *   title="DoorDash Drive Integrations API",
 *   version="1.0",
 *   description="This API allows use of DoorDash Integrations Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="DoorDashDriveIntegration",
 *   description="Operations related to DoordashIntegration"
 * )
 */
class DoordashDriveIntegrationController extends Controller
{
    /**
     * @OA\Post(
     *     path="/third-party-integrations/doordash/add-integration",
     *     operationId="addDoorDashDriveIntegration",
     *     tags={"DoorDashDriveIntegration"},
     *     summary="Integrate VenueBoost Restaurant with DoorDash Drive",
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="developer_id", type="string"),
     *             @OA\Property(property="key_id", type="string"),
     *             @OA\Property(property="signing_secret", type="string"),
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
            'developer_id' => 'required|string|min:5',
            'key_id' => 'required|string|min:5',
            'signing_secret' => 'required|string|min:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Check if there is an active integration already
        $existingIntegration = DoordashIntegration::where('restaurant_id', $venue->id)
            ->where('developer_id', $request->input('developer_id'))
            ->where('key_id', $request->input('key_id'))
            ->where('signing_secret', $request->input('signing_secret'))
            ->whereNull('disconnected_at')
            ->first();

        if ($existingIntegration) {
            return response()->json(['error' => 'An active integration already exists for this restaurant'], 400);
        }

        $jwt = $this->generateJwt($request->input('key_id'), $request->input('developer_id'), $request->input('signing_secret'));

        // Create the integration
        $integration = new DoordashIntegration();
        $integration->restaurant_id = $venue->id;
        $integration->developer_id = $request->input('developer_id');
        $integration->key_id = $request->input('key_id');
        $integration->signing_secret = $request->input('signing_secret');
        $integration->save();

        // Create the JWT record in the pivot table
        $expiryTime = Carbon::now()->addMinutes(5);
        $jwtRecord = new DoordashJwt();
        $jwtRecord->doordash_integration_id = $integration->id;
        $jwtRecord->jwt = $jwt;
        $jwtRecord->expiry_time = $expiryTime;
        $jwtRecord->save();

        // Create the integration
        return response()->json(['message' => 'Integration added successfully']);
    }

    /**
     * @OA\Delete(
     *     path="/third-party-integrations/doordash/delete-integration/{id}",
     *     operationId="disconnectDoorDashIntegration",
     *     tags={"DoorDashDriveIntegration"},
     *     summary="Disconnect VenueBoost Restaurant Integration DoorDash Drive",
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

        $integration = DoordashIntegration::where('restaurant_id', $venue->id)->find($id);

        if (!$integration) {
            return response()->json(['error' => 'Integration not found'], 404);
        }

        $integration->update(['disconnected_at' => now()]);

        return response()->json(['message' => 'Integration disconnected successfully']);
    }

    function base64UrlEncode(string $data): string
    {
        $base64Url = strtr(base64_encode($data), '+/', '-_');

        return rtrim($base64Url, '=');
    }

    function base64UrlDecode(string $base64Url): string
    {
        return base64_decode(strtr($base64Url, '-_', '+/'));
    }

    function generateJWT(string $keyId, string $developerId, string $signingSecret ): string
    {
        $header = json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
            'dd-ver' => 'DD-JWT-V1'
        ]);

        $payload = json_encode([
            'aud' => 'doordash',
            'iss' => $developerId,
            'kid' => $keyId,
            'exp' => time() + 300,
            'iat' => time()
        ]);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->base64UrlDecode($signingSecret), true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * @OA\Post(
     *     path="/third-party-integrations/doordash/create-delivery-request",
     *     summary="Create a delivery request",
     *     tags={"DoorDashDriveIntegration"},
     *     @OA\RequestBody(
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *     )
     * )
     */
    public function createDelivery(Request $request)
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

        $hasDoordashDeliveryProvider = DeliveryProviderRestaurant::with('deliveryProvider')->where('restaurant_id', $venue->id)->whereHas('deliveryProvider', function ($query) {
            $query->where('code', 'VB-DP-DOORDS');
        })->first();

       if (!$hasDoordashDeliveryProvider) {
            return response()->json(['error' => 'Restaurant does not have Doordash as a delivery provider'], 400);
        }

        $integration = DoordashIntegration::where('restaurant_id', $venue->id)->whereNull('disconnected_at')->first();

        if (!$integration) { return response()->json(['error' => 'Integration not found'], 404);}

        $jwtRecord = DoordashJwt::where('doordash_integration_id', $integration->id)->where('expiry_time', '>', Carbon::now())->first();
        if (!$jwtRecord) {
            $newGeneratedJwt = $this->generateJWT($integration->key_id, $integration->developer_id, $integration->signing_secret);
            $newDoordashJWT = new DoordashJwt();
            $newDoordashJWT->doordash_integration_id = $integration->id;
            $newDoordashJWT->jwt = $newGeneratedJwt;
            $newDoordashJWT->expiry_time = Carbon::now()->addMinutes(5);
            $newDoordashJWT->save();
            $useJWT = $newGeneratedJwt;
        }
        else { $useJWT = $jwtRecord->jwt;}


        $url = 'https://openapi.doordash.com/drive/v2/deliveries';

        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',
            'external_delivery_id' => 'required|string',
            'pickup_address' => 'required|string',
            'pickup_business_name' => 'required|string',
            'pickup_phone_number' => 'required|string',
            'pickup_instructions' => 'nullable|string',
            'pickup_reference_tag' => 'nullable|string',
            'dropoff_address' => 'required|string',
            'dropoff_business_name' => 'required|string',
            'dropoff_phone_number' => 'required|string',
            'dropoff_instructions' => 'nullable|string',
            'dropoff_contact_given_name' => 'required|string',
            'dropoff_contact_family_name' => 'required|string',
            'dropoff_contact_send_notifications' => 'nullable|boolean',
            'scheduling_model' => [
                'required',
                Rule::in(['asap', 'scheduled']),
            ],
            'order_value' => 'required|numeric',
            'tip' => 'required|numeric',
            'currency' => 'required|string',
            'contactless_dropoff' => 'nullable|boolean',
            'action_if_undeliverable' => [
                'required',
                Rule::in(['return_to_pickup', 'cancel_order']),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $external_delivery_id = $request->input('external_delivery_id');

        $checkExternalDeliveryId = OrderDelivery::where('external_delivery_id', $external_delivery_id)->first();

        if (!$checkExternalDeliveryId || $checkExternalDeliveryId->order_id != $request->input('order_id')) {
            return response()->json([
                'error' => 'Order not found for the external_delivery_id provided'
            ], 400);
        }

        $payload = $request->only([
            'external_delivery_id',
            'pickup_address',
            'pickup_business_name',
            'pickup_phone_number',
            'pickup_instructions',
            'pickup_reference_tag',
            'dropoff_address',
            'dropoff_business_name',
            'dropoff_phone_number',
            'dropoff_instructions',
            'dropoff_contact_given_name',
            'dropoff_contact_family_name',
            'dropoff_contact_send_notifications',
            'scheduling_model',
            'order_value',
            'tip',
            'currency',
            'contactless_dropoff',
            'action_if_undeliverable',
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $useJWT,
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to connect to Doordash Drive API'], 500);
        }

        $orderDelivery = OrderDelivery::where('external_delivery_id', $external_delivery_id)->first();
        $orderDelivery->delivery_status = 'created';
        $orderDelivery->external_tracking_url = $response->json()['tracking_url'] ?? null;
        $orderDelivery->doordash_order_id = explode('/', $response->json()['tracking_url'])[4] ?? null;
        $orderDelivery->save();

        return $response->json();
    }


    /**
     * @OA\Post(
     *     path="/third-party-integrations/doordash/get-delivery-update",
     *     summary="Get delivery update",
     *     tags={"DoorDashDriveIntegration"},
     *     @OA\RequestBody(
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *     )
     * )
     */
    public function getDeliveryUpdate(Request $request): \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Builder|\Illuminate\Http\JsonResponse|null
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
        $order = Order::where('restaurant_id', $venue->id)->where('id', $request->input('order_id'))->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found for the restaurant making the API call'], 400);
        }

        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',
            'external_delivery_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $external_delivery_id = $request->input('external_delivery_id');

        $checkExternalDeliveryId = OrderDelivery::with('deliveryProvider')->where('external_delivery_id', $external_delivery_id)->first();

        if (!$checkExternalDeliveryId || $checkExternalDeliveryId->order_id != $request->input('order_id') || $checkExternalDeliveryId->deliveryProvider->code != 'VB-DP-DOORDS') {
            return response()->json([
                'error' => 'Order not found for the external_delivery_id provided'
            ], 400);
        }

        $url = 'https://openapi.doordash.com/drive/v2/deliveries/' . $external_delivery_id;

        $integration = DoordashIntegration::where('restaurant_id', $venue->id)->whereNull('disconnected_at')->first();

        if (!$integration) { return response()->json(['error' => 'Integration not found'], 404);}

        $jwtRecord = DoordashJwt::where('doordash_integration_id', $integration->id)->where('expiry_time', '>', Carbon::now())->first();
        if (!$jwtRecord) {
            $newGeneratedJwt = $this->generateJWT($integration->key_id, $integration->developer_id, $integration->signing_secret);
            $newDoordashJWT = new DoordashJwt();
            $newDoordashJWT->doordash_integration_id = $integration->id;
            $newDoordashJWT->jwt = $newGeneratedJwt;
            $newDoordashJWT->expiry_time = Carbon::now()->addMinutes(5);
            $newDoordashJWT->save();
            $useJWT = $newGeneratedJwt;
        }
        else { $useJWT = $jwtRecord->jwt;}

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $useJWT,
            'Content-Type' => 'application/json',
        ])->get($url);

        if ($response->failed()) {
            return response()->json(['error' => $response['message']], 500);
        }


        $orderDelivery = OrderDelivery::where('external_delivery_id', $external_delivery_id)->first();
        $order = Order::with('orderProducts', 'orderDelivery', 'orderDelivery.deliveryProvider')->where('id', $orderDelivery->order_id)->first();

        if($orderDelivery->delivery_status != $response->json()['delivery_status']) {
            $orderDelivery->delivery_status = $response->json()['delivery_status'];
            $orderDelivery->save();
        }

        if($response->json()['delivery_status'] !== 'created') {
            $order->status = $response->json()['delivery_status'];
            $order->save();
        }

        return $order;

    }

    /**
     * @OA\Post(
     *     path="/third-party-integrations/doordash/cancel-delivery-request",
     *     summary="Cancel delivery request",
     *     tags={"DoorDashDriveIntegration"},
     *     @OA\RequestBody(
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *     )
     * )
     */
    public function cancelDeliveryRequest(Request $request): \Illuminate\Http\JsonResponse
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
        $order = Order::where('restaurant_id', $venue->id)->where('id', $request->input('order_id'))->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found for the restaurant making the API call'], 400);
        }

        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',
            'external_delivery_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $external_delivery_id = $request->input('external_delivery_id');

        $checkExternalDeliveryId = OrderDelivery::with('deliveryProvider')->where('external_delivery_id', $external_delivery_id)->first();

        if (!$checkExternalDeliveryId || $checkExternalDeliveryId->order_id != $request->input('order_id') || $checkExternalDeliveryId->deliveryProvider->code != 'VB-DP-DOORDS') {
            return response()->json([
                'error' => 'Order not found for the external_delivery_id provided'
            ], 400);
        }

        $url = 'https://openapi.doordash.com/drive/v2/deliveries/' . $external_delivery_id . '/cancel';

        $integration = DoordashIntegration::where('restaurant_id', $venue->id)->whereNull('disconnected_at')->first();

        if (!$integration) { return response()->json(['error' => 'Integration not found'], 404);}

        $jwtRecord = DoordashJwt::where('doordash_integration_id', $integration->id)->where('expiry_time', '>', Carbon::now())->first();
        if (!$jwtRecord) {
            $newGeneratedJwt = $this->generateJWT($integration->key_id, $integration->developer_id, $integration->signing_secret);
            $newDoordashJWT = new DoordashJwt();
            $newDoordashJWT->doordash_integration_id = $integration->id;
            $newDoordashJWT->jwt = $newGeneratedJwt;
            $newDoordashJWT->expiry_time = Carbon::now()->addMinutes(5);
            $newDoordashJWT->save();
            $useJWT = $newGeneratedJwt;
        }
        else { $useJWT = $jwtRecord->jwt;}

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $useJWT,
            'Content-Type' => 'application/json',
        ])->put($url);

        if ($response->failed()) {
            return response()->json(['error' => $response['message']], 500);
        }

        $orderDelivery = OrderDelivery::where('external_delivery_id', $external_delivery_id)->first();
        $order = Order::with('orderProducts', 'orderDelivery', 'orderDelivery.deliveryProvider')->where('id', $orderDelivery->order_id)->first();
        $orderDelivery->delivery_status = $response->json()['delivery_status'];
        $orderDelivery->save();
        $order->status = $response->json()['delivery_status'];
        $order->save();

        return response()->json(
            [
                'message' => 'Delivery request cancelled successfully',
                'order' => $order
            ]);

    }
}
