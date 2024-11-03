<?php

namespace App\Http\Controllers\AppSuite;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function index(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $employee = $this->venueService->employee();

        if ($venue->id !== $employee->restaurant_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $notifications = Notification::where('employee_id', $employee->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($notifications);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $employee = $this->venueService->employee();

        if ($venue->id !== $employee->restaurant_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification = Notification::where('id', $id)->where('employee_id', $employee->id)->first();

        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $employee = $this->venueService->employee();

        if ($venue->id !== $employee->restaurant_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        Notification::where('employee_id', $employee->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $employee = $this->venueService->employee();

        if ($venue->id !== $employee->restaurant_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification = Notification::where('id', $id)->where('employee_id', $employee->id)->first();

        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->delete();

        return response()->json(['message' => 'Notification deleted successfully']);
    }
}
