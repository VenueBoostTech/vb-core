<?php

namespace App\Http\Controllers\AppSuite\Staff;
use App\Helpers\UserActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LoginActivity;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\VenueIndustry;
use App\Models\VenueType;
use App\Services\VenueService;
use Google\Cloud\Storage\Connection\Rest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Validation\ValidationException;

class AuthenticationController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function getConnection(Request $request): JsonResponse
    {
        try {
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
                $allConnections = DB::table('venue_user_supabase_connections')
                    ->select('user_id', 'venue_id', 'supabase_id')
                    ->get();

                return response()->json([
                    'message' => 'Connection not found',
                    'debug' => [
                        'searched_for' => [
                            'email' => $request->email,
                            'user_id' => $user->id,
                            'supabase_id' => $request->supabase_id
                        ],
                        'all_connections_in_db' => $allConnections
                    ]
                ], 404);
            }

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
                'exp' => now()->addDays(7)->timestamp // 7 days expiry for refresh token
            ])->fromUser($user);

            $employee = Employee::where('user_id', $user->id)->first();

            // Save Login Activity
            LoginActivity::create([
                'user_id' => $user->id,
                'app_source' => 'none',
                'venue_id' => $venue->id,
            ]);

            UserActivityLogger::log($user->id, 'Login');

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
                'account_type' => 'business',
                'refresh_token' => $refreshToken,
                'has_changed_password' => true
            ]);

        } catch (ValidationException $e) {
            // Return validation error response
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);

        } catch (JWTException $e) {
            // Return JWT generation error response
            return response()->json(['message' => 'Token generation failed'], 500);

        } catch (\Exception $e) {
            // Log the exception message for debugging
            Log::error('Error in getConnection: ' . $e->getMessage());

            // Return a general error response
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    /**
     * Register a venue and user with data from NestJS API
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createVenueAndUserForStaffluent(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'first_name' => 'required|string',
                'last_name' => 'required|string',
                'email' => 'required|email',
                'password' => 'required|string',
                'business_name' => 'required|string',
                'supabase_id' => 'required|string',
                'omnistack_user_id' => 'required|string'
            ]);

            // 1. Create the user
            $user = new User();
            $user->name = $request->first_name . ' ' . $request->last_name;
            $user->country_code = 'US';
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->email = $request->email;
            $user->password = bcrypt($request->password);


            // Set external IDs for the user
            $userExternalIds = [];
            $userExternalIds['omniStackGateway'] = $request->omnistack_user_id;
            $user->external_ids = json_encode($userExternalIds);

            $user->save();

            $venueType = VenueType::where('short_name', 'restaurant')->first();
            $venueIndustry = VenueIndustry::where('name', 'food')->first();

            // 2. Create the venue/restaurant
            $restaurant = new Restaurant();
            $restaurant->name = $request->business_name;
            $restaurant->email = $request->email;
            $restaurant->phone_number = $request->phone_number ?? '';
            $restaurant->user_id = $user->id;
            $restaurant->status = 'active';
            $restaurant->venue_type = $venueType->id;
            $restaurant->venue_industry = $venueIndustry->id;
            $restaurant->short_code = $this->generateStringShortCode($request->business_name);
            $restaurant->app_key = $this->generateStringAppKey($request->business_name);;
            $restaurant->save();

            // 3. Create employee record
            $employee = new Employee();
            $employee->name = $user->name;
            $employee->email = $user->email;
            $employee->role_id = 2; // Assuming 2 is Owner role
            $employee->restaurant_id = $restaurant->id;
            $employee->user_id = $user->id;
            $employee->save();


            // 4. Create the connection between user, venue, and Supabase
            DB::table('venue_user_supabase_connections')->insert([
                'user_id' => $user->id,
                'venue_id' => $restaurant->id,
                'supabase_id' => $request->supabase_id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Return only the necessary IDs as requested
            return response()->json([
                'success' => true,
                'user_id' => $user->id,
                'venue_id' => $restaurant->id
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error in createVenueAndUserForStaffluent: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    private function generateStringShortCode($providerName) {
        $prefix = strtoupper(substr($providerName, 0, 3));
        $randomNumbers = sprintf('%04d', mt_rand(0, 9999));
        $suffix = 'SCD';
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomChar = $alphabet[rand(0, strlen($alphabet) - 1)];

        return $prefix . $randomNumbers . $suffix . $randomChar;
    }

    private function generateStringAppKey($providerName) {
        $prefix = strtoupper(substr($providerName, 0, 3));
        $randomNumbers = sprintf('%04d', mt_rand(0, 9999));
        $suffix = 'APP';
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomChar = $alphabet[rand(0, strlen($alphabet) - 1)];

        return $prefix . $randomNumbers . $suffix . $randomChar;
    }


    /**
     * Verify a user's email based on VenueBoost user ID
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyUserEmail(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'venueboost_user_id' => 'required|numeric'
            ]);



                // Try to find by email as fallback
                $user = User::where('email', $request->email)->first();

                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User not found'
                    ], 404);
                }


            // Get the venue associated with this user through connection table
            $connection = DB::table('venue_user_supabase_connections')
                ->where('user_id', $request->venueboost_user_id)
                ->first();

            if (!$connection) {
                // Try to look up by other methods
                $connection = DB::table('venue_user_supabase_connections')
                    ->where('user_id', $user->id)
                    ->first();

                if (!$connection) {
                    Log::warning('No connection found for user when verifying email', [
                        'venueboost_user_id' => $request->venueboost_user_id,
                        'email' => $request->email,
                        'omnistack_user_id' => $request->omnistack_user_id
                    ]);
                }
            }

            // Mark user as verified in the system
            $user->email_verified_at = now();
            $user->save();

            // Log the verification
            UserActivityLogger::log($user->id, 'Email Verified via OmniStack');

            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully',
                'user_id' => $user->id,
                'venue_id' => $connection ? $connection->venue_id : null
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in verifyUserEmail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change a user's password
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'venueboost_user_id' => 'required|numeric',
                'new_password' => 'required|string|min:8'
            ]);

            // Find user by VenueBoost ID
            $user = User::where('id', $request->venueboost_user_id)->first();

            if (!$user) {
                // Try to find by email as fallback
                $user = User::where('email', $request->email)->first();

                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User not found'
                    ], 404);
                }
            }

            // Update the user's password
            $user->password = bcrypt($request->new_password);
            $user->save();

            // Log the password change
            UserActivityLogger::log($user->id, 'Password Changed via OmniStack');

            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
                'user_id' => $user->id
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in changePassword: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get staff connection for OmniStack API
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStaffConnection(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email'
            ]);

            // Find the user with the given email
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            // Get employee info
            $employee = $user->employee()->with('role:id,name')->first();



            // Calculate token expiration
            $ttl = auth()->guard('api')->factory()->getTTL() * 600;
            $refreshTtl = $ttl * 8; // Refresh token TTL (8x longer)

            // Calculate expiration timestamps
            $expiresAt = now()->addSeconds($ttl)->timestamp;
            $refreshExpiresAt = now()->addSeconds($refreshTtl)->timestamp;

            // Generate token
            $token = JWTAuth::fromUser($user);

            // Generate refresh token
            $refreshToken = JWTAuth::customClaims([
                'refresh' => true,
                'exp' => $refreshExpiresAt
            ])->fromUser($user);

            $venue = null;
            if ($user->is_app_client) {
                $accountType = 'client';
            } else {
                $accountType = $this->getStaffAccountType($employee);
                // Fetch venue data
                $venue =  Restaurant::where('id', $employee->restaurant_id)->first();
            }

            // Save Login Activity
            LoginActivity::create([
                'user_id' => $user->id,
                'app_source' => 'staffluent',
                'venue_id' => $venue?->id,
            ]);

            UserActivityLogger::log($user->id, 'Login via Staffluent');

            // Return simplified JSON response without subscription/vision_track logic
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'name' => $user->name,
                    'email' => $user->email,
                    'employee' => $employee,
                    'has_app_access' => true,
                ],
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'token_type' => 'bearer',
                'expires_in' => $ttl,
                'expires_at' => $expiresAt,
                'account_type' => $accountType,
                'is_app_client' => $user->is_app_client,
                'refresh_expires_in' => $refreshTtl,
                'refresh_expires_at' => $refreshExpiresAt
            ]);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token generation failed'], 500);
        } catch (\Exception $e) {
            Log::error('Error in getStaffConnection: ' . $e->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    /**
     * Determine account type based on employee role
     *
     * @param Employee $employee
     * @return string
     */
    private function getStaffAccountType(Employee $employee): string
    {
        if (!$employee->role) {
            return 'staff';
        }

        $roleName = $employee->role->name;

        if ($roleName === 'Team Leader') {
            return 'staff_team_leader';
        }

        if ($roleName === 'Operations Manager') {
            return 'staff_operations_manager';
        }

        if ($roleName === 'Manager') {
            return 'staff_manager';
        }

        if ($roleName === 'Owner') {
            return 'staff_owner';
        }

        return 'staff';
    }



}
