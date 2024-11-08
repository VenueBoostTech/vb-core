<?php

namespace App\Http\Controllers\AppSuite;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationSetting;
use App\Models\NotificationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;

        $perPage = $request->input('per_page', 20);
        $filter = $request->input('filter'); // unread, read, all

        $query = Notification::where('employee_id', $employee->id)
            ->with(['notificationType:id,name']) // Include type info
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        }

        $notifications = $query->paginate($perPage);

        // Get unread count
        $unreadCount = Notification::where('employee_id', $employee->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'notifications' => $notifications->items(),
            'unread_count' => $unreadCount,
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage()
            ]
        ]);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        $employee = auth()->user()->employee;

        try {
            DB::beginTransaction();

            $notification = Notification::where('id', $id)
                ->where('employee_id', $employee->id)
                ->whereNull('read_at')
                ->firstOrFail();

            $notification->update(['read_at' => now()]);

            DB::commit();

            return response()->json([
                'message' => 'Notification marked as read',
                'unread_count' => Notification::where('employee_id', $employee->id)
                    ->whereNull('read_at')
                    ->count()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Notification not found or already read'
            ], 404);
        }
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;

        try {
            DB::beginTransaction();

            $count = Notification::where('employee_id', $employee->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            DB::commit();

            return response()->json([
                'message' => "{$count} notifications marked as read",
                'unread_count' => 0
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to mark notifications as read'
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        $employee = auth()->user()->employee;

        try {
            DB::beginTransaction();

            $notification = Notification::where('id', $id)
                ->where('employee_id', $employee->id)
                ->firstOrFail();

            $notification->delete();

            DB::commit();

            return response()->json([
                'message' => 'Notification deleted successfully',
                'unread_count' => Notification::where('employee_id', $employee->id)
                    ->whereNull('read_at')
                    ->count()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Notification not found'
            ], 404);
        }
    }

    // New method to get notification counts
    public function getCounts(): JsonResponse
    {
        $employee = auth()->user()->employee;

        $counts = [
            'total' => Notification::where('employee_id', $employee->id)->count(),
            'unread' => Notification::where('employee_id', $employee->id)
                ->whereNull('read_at')
                ->count(),
            'read' => Notification::where('employee_id', $employee->id)
                ->whereNotNull('read_at')
                ->count()
        ];

        return response()->json($counts);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $employee = $this->venueService->employee();
        if ($employee instanceof JsonResponse) return $employee;

        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*.notification_type_id' => 'required|exists:notification_types,id',
            'settings.*.is_enabled' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            DB::beginTransaction();

            foreach ($request->settings as $setting) {
                NotificationSetting::updateOrCreate(
                    [
                        'user_id' => $employee->user_id,
                        'notification_type_id' => $setting['notification_type_id']
                    ],
                    [
                        'is_enabled' => $setting['is_enabled']
                    ]
                );
            }

            DB::commit();

            // Get updated settings
            $allSettings = $this->getFormattedSettings($employee->user_id);

            return response()->json([
                'message' => 'Notification settings updated successfully',
                'settings' => $allSettings
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update notification settings'], 500);
        }
    }

    public function getSettings(): JsonResponse
    {
        $employee = $this->venueService->employee();
        if ($employee instanceof JsonResponse) return $employee;

        $settings = $this->getFormattedSettings($employee->user_id);

        return response()->json(['settings' => $settings]);
    }

    protected function getFormattedSettings($userId)
    {
        return NotificationType::select('id', 'name', 'description')
            ->get()
            ->map(function ($type) use ($userId) {
                $setting = NotificationSetting::where('user_id', $userId)
                    ->where('notification_type_id', $type->id)
                    ->first();

                return [
                    'notification_type_id' => $type->id,
                    'name' => $type->name,
                    'description' => $type->description,
                    'is_enabled' => $setting ? $setting->is_enabled : true // default enabled
                ];
            });
    }
}
