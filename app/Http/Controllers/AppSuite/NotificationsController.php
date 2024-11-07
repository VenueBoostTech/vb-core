<?php

namespace App\Http\Controllers\AppSuite;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;

        $perPage = $request->input('per_page', 20);
        $filter = $request->input('filter'); // unread, read, all

        $query = Notification::where('employee_id', $employee->id)
            ->with(['notificationType:id,name,icon']) // Include type info
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
}
