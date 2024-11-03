<?php

namespace App\Http\Controllers\AppSuite\Staff;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticationController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function getConnection(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'supabase_id' => 'required|string'
        ]);

        // Find the user with the given email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Check if the combination of supabase_id and user_id exists
        $connection = DB::table('venue_user_supabase_connections')
            ->where('user_id', $user->id)
            ->where('supabase_id', $request->supabase_id)
            ->first();

        if (!$connection) {
            return response()->json(['message' => 'Connection not found'], 404);
        }

        // Fetch venue data
        $venue = Restaurant::find($connection->venue_id);

        if (!$venue) {
            return response()->json(['message' => 'Venue not found'], 404);
        }

        // Generate JWT token for the user
        $token = JWTAuth::fromUser($user);

        // Generate refresh token with longer expiry
        $refreshToken = JWTAuth::customClaims([
            'refresh' => true,
            'exp' => now()->addDays(30)->timestamp // 30 days expiry for refresh token
        ])->fromUser($user);

        $employee = Employee::where('user_id', $user->id)->first();

        // Return user data, venue data, and the token
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee' => $employee,
                // Add other user fields as needed
            ],
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'short_code' => $venue->short_code,
                // Add other venue fields as needed
            ],
            'supabase_id' => $connection->supabase_id,
            'token' => $token,
            'refresh_token' => $refreshToken
        ]);
    }
}
