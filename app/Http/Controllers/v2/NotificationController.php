<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\FirebaseUserToken;
use App\Models\NotificationConfiguration;
use App\Models\NotificationConfigurationType;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{

    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function createConfiguration(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'notification_type' => 'required|string|max:255',
            'trigger_value' => 'required|numeric',
            'is_active' => 'nullable|default:true|boolean',
            'blog_id' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Role check for super admin
        $user = auth()->user();
        if ($user->role->name !== 'Superadmin') {
            return response()->json(['error' => 'Unauthorized: Only super admins can perform this action'], 403);
        }

        $configurationData = $validator->validated();
        unset($configurationData['blog_id']);

        $configurationData['user_id'] = $user->id;

        $configuration = NotificationConfiguration::create($configurationData);

        $configurationTypeData = [
            'config_id' => $configuration->id,
            'blog_id' => $request->input('blog_id', null)
        ];

        NotificationConfigurationType::create($configurationTypeData);

        return response()->json([
            'configuration' => $configuration,
            'message' => 'Configuration created successfully'
        ], 200);
    }

    public function updateConfiguration(Request $request, $id): \Illuminate\Http\JsonResponse
    {

        // Validation
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'notification_type' => 'sometimes|string|max:255',
            'trigger_value' => 'sometimes|numeric',
            'is_active' => 'sometimes|boolean',
            'blog_id' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Role check for super admin
        $user = auth()->user();
        if ($user->role->name !== 'Superadmin') {
            return response()->json(['error' => 'Unauthorized: Only super admins can perform this action'], 403);
        }

        $configuration = NotificationConfiguration::where('id', $id)->where('user_id', $user->id)->first();

        if (!$configuration) {
            return response()->json(['error' => 'Notification configuration not found or access denied'], 404);
        }

        $configurationData = $validator->validated();
        unset($configurationData['blog_id']);
        $configuration->fill($configurationData)->save();

        $configurationType = NotificationConfigurationType::where('config_id', $id)->first();
        if ($configurationType) {
            $configurationType->update(['blog_id' => $request->input('blog_id')]);
        }

        return response()->json([
            'configuration' => $configuration,
            'message' => 'Configuration updated successfully'
        ], 200);
    }

    public function storeFirebaseToken(Request $request): \Illuminate\Http\JsonResponse
    {
        // Role check for super admin
        if (auth()->user()->role->name !== 'Superadmin') {
            return response()->json(['error' => 'Unauthorized: Only super admins can access this information'], 403);
        }

        $userId = auth()->user()->id;

        $validator = Validator::make($request->all(), [
            'firebase_token' => 'required|string',
            'browser_name' => 'nullable|string',
            'browser_os' => 'nullable|string',
            'browser_type' => 'nullable|string',
            'browser_version' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $subscribed = FirebaseUserToken::where('user_id', $userId)->first();

        if($subscribed) {
            return response()->json(['is_subscribed' => true]);
        } else {
            $validatedData = $validator->validated();

            FirebaseUserToken::create([
                'user_id' => $userId,
                'firebase_token' => $validatedData['firebase_token'],
                'browser_name' => $validatedData['browser_name'] ?? null,
                'browser_os' => $validatedData['browser_os'] ?? null,
                'browser_type' => $validatedData['browser_type'] ?? null,
                'browser_version' => $validatedData['browser_version'] ?? null,
            ]);
            return response()->json(['message' => 'Firebase token stored successfully']);
        }

    }

    public function refreshUserToken(Request $request): \Illuminate\Http\JsonResponse
    {
        $userId = auth()->id();

        $newToken = $request->input('firebase_token');

        $recentToken = FirebaseUserToken::where('user_id', $userId)->first();

        if ($recentToken) {
            $recentToken->update(['firebase_token' => $newToken]);
        }

        return response()->json(['message' => 'success'], 200);
    }

    public function listConfigurations(): \Illuminate\Http\JsonResponse
    {
        // Role check for super admin
        if (auth()->user()->role->name !== 'Superadmin') {
            return response()->json(['error' => 'Unauthorized: Only super admins can access this information'], 403);
        }

        $userId = auth()->user()->id;

        $configurations = NotificationConfiguration::with(['types.blog:id,title'])->where('user_id', $userId)->get();

        $configurations->each(function ($configuration) {
            $typeWithBlog = $configuration->types->first(function ($type) {
                return !is_null($type->blog);
            });

            $configuration->blog = $typeWithBlog ? $typeWithBlog->blog : null;
            $configuration->types = null;
        });

        return response()->json([
            'configurations' => $configurations
        ]);
    }

    public function deleteConfiguration($id): \Illuminate\Http\JsonResponse
    {
        // Role check for super admin
        $user = auth()->user();
        if ($user->role->name !== 'Superadmin') {
            return response()->json(['error' => 'Unauthorized: Only super admins can perform this action'], 403);
        }

        $configuration = NotificationConfiguration::where('id', $id)->where('user_id', $user->id)->first();

        if (!$configuration) {
            return response()->json(['error' => 'Notification configuration not found or access denied'], 404);
        }

        $configuration->delete();

        return response()->json([
            'message' => 'Configuration deleted successfully'
        ]);
    }

    // Firebase test method !! working
    public function sendTestNotification(Request $request): \Illuminate\Http\JsonResponse
    {
        $token = $request->input('firebase_token');

        $title = 'Test Notification!!!';
        $body = 'This is a test notification sent from the backend!!!';
        $data = ['key' => 'value'];

        try {
            $response = $this->firebaseService->sendNotification($title, $body, $token, $data);
            return response()->json(['message' => 'Notification sent successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function tryTokenDummyNotification(Request $request): \Illuminate\Http\JsonResponse
    {
        $token = $request->firebase_token;

        try {
            $result = $this->firebaseService->validateToken($token);

            if ($result['valid']) {
                return response()->json(['message' => 'Token is valid']);
            } else {
                return response()->json(['error' => 'Invalid token', 'details' => $result['error']], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error validating token: ' . $e->getMessage()], 500);
        }
    }
}
