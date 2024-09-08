<?php

namespace App\Http\Controllers\v1\Stripe\Terminal;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReadersController extends Controller
{

    public function createReader(Request $request): \Illuminate\Http\JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'registration_code' => 'required|string',
            'label' => 'required|string',
            'location' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        \Stripe\Stripe::setApiKey(config('services.stripe.key'));

        $reader = \Stripe\Terminal\Reader::create([
            'registration_code' => $request->input('registration_code'),
            'label' => $request->input('label'),
            'location' => $request->input('location'),
        ]);

        $response = [
            'success' => true,
            'data' => $reader
        ];

        return response()->json($response, 200);

    }

    public function readers(): \Illuminate\Http\JsonResponse
    {

        \Stripe\Stripe::setApiKey(config('services.stripe.key'));

        $readers = \Stripe\Terminal\Reader::all();

        $response = [
            'success' => true,
            'data' => $readers
        ];

        return response()->json($response, 200);

    }

}
