<?php

namespace App\Services;

use App\Models\AppClient;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ClientAuthService
{
    public function getAuthenticatedClient(): AppClient|JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->is_app_client) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $client = $user->appClient;
        if (!$client) {
            return response()->json(['error' => 'Client profile not found'], 404);
        }

        return $client;
    }

    public function validateClientAccess(): ?JsonResponse
    {
        $result = $this->getAuthenticatedClient();
        return ($result instanceof JsonResponse) ? $result : null;
    }
}
