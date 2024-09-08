<?php

namespace App\Http\Controllers\v1\Stripe\WhiteLabel;
use App\Http\Controllers\Controller;

use App\Models\Restaurant;
use App\Models\VenueConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class PaymentsController extends Controller
{

    public function createPaymentIntent(Request $request): \Illuminate\Http\JsonResponse
    {


        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer',
            'description' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }



        \Stripe\Stripe::setApiKey(config('services.stripe.key'));
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => floatval($request->input('amount')),
            'currency' => 'usd', // todo replace this with vendor currency
            'payment_method_types' => ["card"],
        ]);

        // todo: maybe we need a transaction payments here to save the payment intent id

        $response = [
            'success' => true,
            'data' => [
                'client_secret' => $paymentIntent->client_secret, // Extract the client_secret from the PaymentIntent object
            ],
        ];

        return response()->json($response, 200);

    }

    public function createDestinationCharge(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer',
            'description' => 'required|string',
            'currency' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $apiCallVenueAppKey = request()->get('venue_app_key');

        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $venueConfiguration = VenueConfiguration::where('venue_id', $venue->id)->first();

        if (!$venueConfiguration) {
            $venueConfiguration = VenueConfiguration::create([
                'venue_id' => $venue->id,
                'email_language' => 'English',
                'stripe_connected_acc_id' => $apiCallVenueAppKey = 'BY 6547APPF' ? 'acct_1PTA2W2cH7TxyigH' : null,
                'onboarding_completed' => true,
                'connected_account_created_at' => null,
                'connected_account_updated_at' => null,
                'more_information_required' => false
            ]);
        } else {
            // update only stripe connected account id if  $apiCallVenueAppKey = 'BY 6547APPF' and only if null

            if ($apiCallVenueAppKey = 'BY 6547APPF' && $venueConfiguration->stripe_connected_acc_id == null) {
                $venueConfiguration->update([
                    'stripe_connected_acc_id' => 'acct_1PTA2W2cH7TxyigH'
                ]);
            }
        }


        \Stripe\Stripe::setApiKey(config('services.stripe.key'));

        $amount = intval($request->input('amount'));
        $platformFee = intval($amount * 0.05);

        try {
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $amount,
                'currency' => $request->input('currency'),
                'payment_method_types' => ["card"],
                'description' => $request->input('description'),
                'transfer_data' => [
                    'destination' => $venueConfiguration->stripe_connected_acc_id, // assuming 'stripe_account_id' is stored in the 'Restaurant' model
                ],
                'application_fee_amount' => $platformFee,
            ]);

            $response = [
                'success' => true,
                'data' => [
                    'client_secret' => $paymentIntent->client_secret, // Extract the client_secret from the PaymentIntent object
                ],
            ];

            return response()->json($response, 200);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


}
