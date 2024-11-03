<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class EndUserService
{
    public function endUserAuthCheck(): User|JsonResponse
    {
        if (!auth()->user()) {
            return response()->json(['error' => 'No authenticated user to make this API call'], 401);
        }

        $userId = auth()->user()->id;

        $user = User::where('id', $userId)->first();

        if (!$user?->enduser) {
            return response()->json(['error' => 'User not eligible to make this API call'], 401);
        }

        $guest = Guest::where('user_id', $userId)->first();
        $customer = Customer::where('user_id', $userId)->first();
        if (!$guest && !$customer) {
            return response()->json(['error' => 'User not eligible to make this API call'], 401);
        }

        return $user;
    }
}
