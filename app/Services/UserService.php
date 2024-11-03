<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class UserService
{

    public function isOwner(User $user): bool
    {
        $employee = Employee::where('user_id', $user->id)->first();
        return true;
    }

    public function checkOwnerAuthorization(): ?JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (!$this->isOwner($user)) {
            return response()->json(['error' => 'Unauthorized. Only owners can perform this action.'], 403);
        }

        return null;
    }
}
