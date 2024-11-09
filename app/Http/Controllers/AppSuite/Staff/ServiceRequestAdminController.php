<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\AppProject;
use App\Models\Service;
use App\Models\ServiceRequestActivity;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ServiceRequestAdminController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function index(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        // Get last 24 hours stats
        $last24Hours = Carbon::now()->subHours(24);
        $newRequests = ServiceRequest::where('venue_id', $venue->id)
            ->where('requested_date', '>=', $last24Hours)
            ->count();

        // Get other stats
        $inProgressCount = ServiceRequest::where('venue_id', $venue->id)
            ->where('status', ServiceRequest::STATUS_IN_PROGRESS)
            ->count();

        $completedToday = ServiceRequest::where('venue_id', $venue->id)
            ->where('status', ServiceRequest::STATUS_COMPLETED)
            ->whereDate('completed_at', Carbon::today())
            ->count();

        // Calculate average response time
        $avgResponseTime = ServiceRequest::where('venue_id', $venue->id)
            ->whereNotNull('scheduled_date')
            ->whereDate('scheduled_date', '>=', Carbon::now()->subDays(30))
            ->get()
            ->avg(function ($request) {
                return Carbon::parse($request->requested_date)
                    ->diffInHours($request->scheduled_date);
            });

        // Get requests with pagination and filters
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');

        $requests = ServiceRequest::with(['client', 'service', 'assignedStaff'])
            ->where('venue_id', $venue->id)
            ->when($status, function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->latest()
            ->paginate($perPage);

        // Get recent activities
        $recentActivities = ServiceRequestActivity::with(['serviceRequest', 'performer'])
            ->whereHas('serviceRequest', function ($query) use ($venue) {
                $query->where('venue_id', $venue->id);
            })
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($activity) {
                return [
                    'description' => $activity->description,
                    'time_ago' => Carbon::parse($activity->created_at)->diffForHumans()
                ];
            });

        return response()->json([
            'stats' => [
                'new_requests' => $newRequests,
                'in_progress' => $inProgressCount,
                'completed_today' => $completedToday,
                'avg_response_time' => round($avgResponseTime, 1) . 'h'
            ],
            'requests' => [
                'data' => $requests->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'reference' => $request->reference,
                        'client' => $request->client->name,
                        'service' => $request->service->name,
                        'requested_date' => $request->requested_date->format('Y-m-d'),
                        'scheduled_date' => optional($request->scheduled_date)->format('Y-m-d'),
                        'status' => $request->status,
                        'priority' => $request->priority
                    ];
                }),
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
                'total_pages' => $requests->lastPage(),
            ],
            'recent_activities' => $recentActivities
        ]);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'scheduled_date' => 'required|date|after:now'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            DB::beginTransaction();

            $serviceRequest = ServiceRequest::where('venue_id', $venue->id)
                ->with(['client', 'service'])
                ->findOrFail($id);

            if ($serviceRequest->status !== ServiceRequest::STATUS_PENDING) {
                return response()->json(['error' => 'Only pending requests can be approved'], 400);
            }

            // Create project for the service request
            $project = AppProject::create([
                'name' => "Service: {$serviceRequest->service->name} - {$serviceRequest->client->name}",
                'description' => $serviceRequest->description,
                'start_date' => $request->scheduled_date,
                'status' => AppProject::STATUS_PLANNING,
                'venue_id' => $venue->id,
                'project_type' => 'service',
                'project_category' => 'client',
                'client_id' => $serviceRequest->client_id,
                'address_id' => $serviceRequest->client->address_id,
                'project_source' => 'service_request',
                'service_id' => $serviceRequest->service_id,
                'quoted_price' => $serviceRequest->service->base_price,
                'estimated_hours' => $serviceRequest->service->duration / 60
            ]);

            // Update service request
            $serviceRequest->update([
                'status' => ServiceRequest::STATUS_SCHEDULED,
                'scheduled_date' => $request->scheduled_date,
                'app_project_id' => $project->id
            ]);

            // Record activity
            ServiceRequestActivity::create([
                'service_request_id' => $serviceRequest->id,
                'activity_type' => ServiceRequestActivity::TYPE_STATUS_CHANGE,
                'description' => 'Request approved and scheduled',
                'performed_by' => auth()->id(),
                'old_value' => ServiceRequest::STATUS_PENDING,
                'new_value' => ServiceRequest::STATUS_SCHEDULED
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Service request approved successfully',
                'project_id' => $project->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function decline(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            DB::beginTransaction();

            $serviceRequest = ServiceRequest::where('venue_id', $venue->id)->findOrFail($id);

            if ($serviceRequest->status !== ServiceRequest::STATUS_PENDING) {
                return response()->json(['error' => 'Only pending requests can be declined'], 400);
            }

            $serviceRequest->update([
                'status' => ServiceRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $request->reason
            ]);

            ServiceRequestActivity::create([
                'service_request_id' => $serviceRequest->id,
                'activity_type' => ServiceRequestActivity::TYPE_STATUS_CHANGE,
                'description' => 'Request declined: ' . $request->reason,
                'performed_by' => auth()->id(),
                'old_value' => ServiceRequest::STATUS_PENDING,
                'new_value' => ServiceRequest::STATUS_CANCELLED
            ]);

            DB::commit();

            return response()->json(['message' => 'Service request declined successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
