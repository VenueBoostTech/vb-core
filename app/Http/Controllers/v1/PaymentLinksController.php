<?php

namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Models\PaymentLink;
use Carbon\Carbon;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;
use function response;

/**
 * @OA\Info(
 *   title="Payment Links API",
 *   version="1.0",
 *   description="This API allows use Payment Links Related API for VenueBoost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Payment Links",
 *   description="Operations related to Payment Links"
 * )
 */
class PaymentLinksController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
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

        $paymentLinks = PaymentLink::where('venue_id', $venue->id)->orderBy('created_at', 'desc')->get();

        // Formatting start_time and end_time using Carbon
        $paymentLinksFormatted = $paymentLinks->map(function ($paymentLink) {
            $paymentLink->start_time = Carbon::parse($paymentLink->start_time)->format('d M, Y H:i A');
            $paymentLink->end_time = Carbon::parse($paymentLink->end_time)->format('d M, Y H:i A');
            return $paymentLink;
        });

        return response()->json(['message' => 'Payment Links retrieved successfully', 'data' => $paymentLinksFormatted], 200);
    }

    public function show($id): \Illuminate\Http\JsonResponse
    {
        $paymentLink = new \stdClass();

        return response()->json([
            'paymentLink' => $paymentLink,
            'message' => 'Payment Link retrieved successfully'
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
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
            'payment_type' => 'required|string',
            'payment_structure' => 'required|string',
            'party_size' => 'integer',
            'amount' => 'required|numeric',
            'price_per_person' => 'required_if:payment_structure,per_person',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Calculate the amount if payment structure is "Per Person"
        if ($request->payment_structure === 'per_person') {
            if($request->amount != $request->price_per_person * $request->party_size) {
                return response()->json(['error' => 'Amount does not match with the price per person and party size'], 400);
            }
        }

        // Generate UUID
        $uuid = Uuid::uuid4()->toString();

        $queryParams = [
            'p_link' => $uuid,
            'amount' => $request->input('amount'),
        ];

        // Add other query parameters as needed, such as payment method and party size
        if ($request->input('payment_method')) {
            $queryParams['p_method'] = $request->input('payment_method');
        }

        if ($request->input('party_size')) {
            $queryParams['party_size'] = $request->input('party_size');
        }


        $url = 'https://venueboost.io/venue/'.$venue->venueType->short_name.'/'.$venue->app_key . '?' . http_build_query($queryParams);

        $paymentLink = new PaymentLink([
            'payment_type' => $request->payment_type,
            'payment_structure' => $request->payment_structure,
            'party_size' => $request->party_size,
            'total' => $request->amount,
            'price_per_person' => $request->price_per_person,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'venue_id' => $venue->id,
            'link' => $url,
        ]);
        $paymentLink->save();


        return response()->json(['message' => 'Payment link generated successfully', 'data' => $paymentLink ], 201);
    }

}
