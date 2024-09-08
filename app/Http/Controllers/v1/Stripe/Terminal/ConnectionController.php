<?php

namespace App\Http\Controllers\v1\Stripe\Terminal;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe;


class ConnectionController extends Controller
{
    public function connect(Request $request): \Illuminate\Http\JsonResponse
    {


        \Stripe\Stripe::setApiKey(config('services.stripe.key'));

        $connectionToken = Stripe\Terminal\ConnectionToken::create();

        $response = [
            'success' => true,
            'data' => $connectionToken
        ];

        return response()->json($response, 200);


    }

    public function locations(): \Illuminate\Http\JsonResponse
    {

            \Stripe\Stripe::setApiKey(config('services.stripe.key'));

            $locations = \Stripe\Terminal\Location::all();

            $response = [
                'success' => true,
                'data' => $locations
            ];

            return response()->json($response, 200);

    }
}
