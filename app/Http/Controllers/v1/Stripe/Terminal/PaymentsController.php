<?php

namespace App\Http\Controllers\v1\Stripe\Terminal;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class PaymentsController extends Controller
{

    public function createPaymentIntent(Request $request): \Illuminate\Http\JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer',
            'payment_method_types' => 'required|array',
            'description' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        \Stripe\Stripe::setApiKey(config('services.stripe.key'));
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => floatval($request->input('amount')),
            'description' => floatval($request->input('description')),
            'currency' => 'usd',
            'payment_method_types' => [$request->input('payment_method_types')],
        ]);

        $response = [
            'success' => true,
            'data' => [
                'client_secret' => $paymentIntent->client_secret, // Extract the client_secret from the PaymentIntent object
            ],
        ];

        return response()->json($response, 200);

    }

}
