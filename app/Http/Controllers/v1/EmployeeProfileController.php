<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\EmployeeLocation;
use App\Models\EmployeePreference;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class EmployeeProfileController extends Controller
{
    public function tasks()
    {
        $employee = auth()->user()->employee;
        $tasks = $employee->assignedTasks()->get();
        return response()->json($tasks);
    }

    public function assigned_projects()
    {
        $employee = auth()->user()->employee;
        $projects = $employee->projects()->get();
        return response()->json($projects);
    }

    public function update_profile(Request $request)
    {
        $employee = auth()->user()->employee;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'personal_phone' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Update employee
        $employee->update($request->only(['name', 'personal_phone']));

        // Find and update user
        $user = User::find($employee->user_id);
        if ($user) {
            $user->update(['name' => $request->name]);
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'employee' => $employee
        ]);
    }

    public function update_communication_preferences(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_notifications' => 'required|boolean',
            'sms_notifications' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $employee = auth()->user()->employee;

        // Create or update preferences
        $preferences = EmployeePreference::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'email_notifications' => $request->email_notifications,
                'sms_notifications' => $request->sms_notifications
            ]
        );

        return response()->json([
            'message' => 'Communication preferences updated successfully',
            'preferences' => $preferences
        ]);
    }

    public function update_tracking_preferences(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location_tracking_enabled' => 'required|boolean',
            'background_tracking_enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $employee = auth()->user()->employee;

        $preferences = EmployeePreference::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'location_tracking_enabled' => $request->location_tracking_enabled,
                'background_tracking_enabled' => $request->background_tracking_enabled,
                'tracking_enabled_at' => $request->location_tracking_enabled ? now() : null,
                'tracking_disabled_at' => !$request->location_tracking_enabled ? now() : null,
            ]
        );

        return response()->json([
            'message' => 'Tracking preferences updated successfully',
            'preferences' => $preferences
        ]);
    }

    public function update_location(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'provider' => ['required', 'string', 'in:gps,network,passive'],
            'accuracy' => 'nullable|numeric',
            'device_platform' => ['nullable', 'string', 'in:ios,android'],
            'device_os_version' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $employee = auth()->user()->employee;

        // Check if location tracking is enabled
        if (!$employee->isLocationTrackingEnabled()) {
            return response()->json(['error' => 'Location tracking is not enabled'], 403);
        }

        $location = EmployeeLocation::create([
            'employee_id' => $employee->id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'provider' => $request->provider,
            'accuracy' => $request->accuracy,
            'device_platform' => $request->device_platform,
            'device_os_version' => $request->device_os_version,
            'recorded_at' => now(),
            'is_within_geofence' => false, // This should be calculated based on venue coordinates
        ]);

        return response()->json([
            'message' => 'Location updated successfully',
            'location' => $location
        ]);
    }

    public function get_tracking_status()
    {
        $employee = auth()->user()->employee;
        $preferences = $employee->preferences;
        $lastLocation = $employee->locations()->latest('recorded_at')->first();

        return response()->json([
            'tracking_enabled' => $preferences?->location_tracking_enabled ?? false,
            'background_tracking_enabled' => $preferences?->background_tracking_enabled ?? false,
            'last_location' => $lastLocation,
            'tracking_enabled_at' => $preferences?->tracking_enabled_at,
            'preferences' => $preferences
        ]);
    }

    public function time_entries()
    {
        $employee = auth()->user()->employee;
        $timeEntries = $employee->timeEntries()->get();
        return response()->json($timeEntries);
    }

    public function save_firebase_token(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'device_id' => 'required|string',
            'device_type' => ['required', 'string', 'in:ios,android'],
            'device_model' => 'nullable|string',
            'os_version' => 'nullable|string',
            'app_version' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user = auth()->user();

        // Deactivate old token for this device if exists
        $user->firebaseTokens()
            ->where('device_id', $request->device_id)
            ->update(['is_active' => false]);

        // Save new token
        $token = $user->firebaseTokens()->create([
            'firebase_token' => $request->token,
            'device_id' => $request->device_id,
            'device_type' => $request->device_type,
            'device_model' => $request->device_model,
            'os_version' => $request->os_version,
            'app_version' => $request->app_version,
            'is_active' => true,
            'last_used_at' => now()
        ]);

        return response()->json([
            'message' => 'Token saved successfully',
            'token' => $token
        ]);
    }

    public function get_profile(Request $request)
    {
        $user = auth()->user();
        $employee = $user->employee()
            ->with([
                'role:id,name',
                'preferences' // Include communication preferences
            ])
            ->first();

        // Handle profile picture
        $profilePicture = null;
        if ($employee->profile_picture) {
            try {
                $profilePicture = Storage::disk('s3')->temporaryUrl($employee->profile_picture, now()->addMinutes(5));
            } catch (\Exception $e) {
                \Log::error('Error generating profile picture URL: ' . $e->getMessage());
                $profilePicture = $this->getInitials($employee->name);
            }
        } else {
            $profilePicture = $this->getInitials($employee->name);
        }

        // Build response
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'personal_phone' => $employee->personal_phone,
                    'personal_email' => $employee->personal_email,
                    'company_email' => $employee->company_email,
                    'company_phone' => $employee->company_phone,
                    'department' => $employee->department?->name ?? 'N/A',
                    'profile_picture' => $profilePicture,
                    'hire_date' => $employee->hire_date,
                    'schedule' => 'N/A',
                    'employee_custom_id' => '#34023342',
                    'role' => $employee->role,
                    'communication_preferences' => [
                        'email_notifications' => $employee->preferences?->email_notifications ?? false,
                        'sms_notifications' => $employee->preferences?->sms_notifications ?? false,
                    ],
                    'tracking_preferences' => [
                        'location_tracking_enabled' => $employee->preferences?->location_tracking_enabled ?? false,
                        'background_tracking_enabled' => $employee->preferences?->background_tracking_enabled ?? false
                    ]
                ],
                'allow_clockinout' => true
            ]
        ]);
    }

    /**
     * Generate initials from name
     */
    /**
     * Generate initials from name with UTF-8 support
     */
    private function getInitials(string $name): string
    {
        // Convert to UTF-8 if not already
        $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');

        // Split on spaces and handle UTF-8 characters
        $words = preg_split('/\s+/u', trim($name));
        $initials = '';

        foreach ($words as $word) {
            // Get first character with UTF-8 support
            $initial = mb_substr($word, 0, 1, 'UTF-8');
            $initials .= mb_strtoupper($initial, 'UTF-8');
        }

        // Limit to 2 characters
        return mb_strlen($initials, 'UTF-8') > 2 ? mb_substr($initials, 0, 2, 'UTF-8') : $initials;
    }
}
