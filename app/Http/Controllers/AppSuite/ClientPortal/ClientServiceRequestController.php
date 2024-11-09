<?php

namespace App\Http\Controllers\AppSuite\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\Service;
use App\Models\AppClient;
use App\Models\ServiceRequestActivity;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ClientServiceRequestController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function requestService(Request $request): JsonResponse
    {
        // Get authenticated user's client profile
        $user = auth()->user();
        if (!$user->is_app_client) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $client = $user->appClient;
        if (!$client) {
            return response()->json(['error' => 'Client profile not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'preferred_date' => 'required|date|after:today',
            'description' => 'required|string|max:1000',
            'priority' => 'required|in:Low,Normal,High,Urgent'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Get service and verify it belongs to client's venue
            $service = Service::where('venue_id', $client->venue_id)
                ->findOrFail($request->service_id);

            // Generate unique reference number
            $reference = 'SR-' . date('Y') . str_pad((ServiceRequest::count() + 1), 4, '0', STR_PAD_LEFT);

            // Create service request
            $serviceRequest = ServiceRequest::create([
                'reference' => $reference,
                'client_id' => $client->id,
                'venue_id' => $client->venue_id,
                'service_id' => $service->id,
                'status' => ServiceRequest::STATUS_PENDING,
                'priority' => $request->priority,
                'requested_date' => now(),
                'preferred_date' => Carbon::parse($request->preferred_date),
                'description' => $request->description
            ]);

            // Create initial activity record
            ServiceRequestActivity::create([
                'service_request_id' => $serviceRequest->id,
                'activity_type' => ServiceRequestActivity::TYPE_STATUS_CHANGE,
                'description' => 'Service request created by client',
                'performed_by' => $user->id,
                'new_value' => ServiceRequest::STATUS_PENDING
            ]);


            DB::commit();

            return response()->json([
                'message' => 'Service request created successfully',
                'service_request' => [
                    'id' => $serviceRequest->id,
                    'reference' => $serviceRequest->reference,
                    'service' => $service->name,
                    'status' => $serviceRequest->status,
                    'preferred_date' => $serviceRequest->preferred_date->format('Y-m-d'),
                    'priority' => $serviceRequest->priority
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create service request: ' . $e->getMessage()], 500);
        }
    }

    public function listMyRequests(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user->is_app_client) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $client = $user->appClient;
        if (!$client) {
            return response()->json(['error' => 'Client profile not found'], 404);
        }

        // Get pagination and filter parameters
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');
        $search = $request->input('search');

        // Build query with relationships
        $query = ServiceRequest::with(['service'])
            ->where('client_id', $client->id);

        // Apply status filter if provided
        if ($status) {
            $query->where('status', $status);
        }

        // Apply search if provided
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('service', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Get paginated results
        $requests = $query->latest()->paginate($perPage);

        // Calculate stats
        $stats = [
            'pending' => [
                'count' => $client->serviceRequests()->where('status', 'Pending')->count(),
                'label' => 'Awaiting processing'
            ],
            'scheduled' => [
                'count' => $client->serviceRequests()->where('status', 'Scheduled')->count(),
                'label' => 'Confirmed appointments'
            ],
            'completed' => [
                'count' => $client->serviceRequests()
                    ->where('status', 'Completed')
                    ->whereMonth('completed_at', Carbon::now()->month)
                    ->count(),
                'label' => 'This month'
            ]
        ];

        // Format the response to match the frontend view
        return response()->json([
            'stats' => $stats,
            'requests' => [
                'data' => $requests->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'reference' => $request->reference,
                        'service_type' => $request->service->name,
                        'requested_date' => Carbon::parse($request->requested_date)->format('M d, Y'),
                        'preferred_date' => Carbon::parse($request->preferred_date)->format('M d, Y'),
                        'status' => $request->status,
                        'priority' => $request->priority,
                        'description' => $request->description,
                        // Add these for badge styling
                        'status_variant' => $this->getStatusVariant($request->status),
                        'priority_variant' => $this->getPriorityVariant($request->priority)
                    ];
                }),
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
                'total_pages' => $requests->lastPage(),
            ]
        ]);
    }

    private function getStatusVariant(string $status): string
    {
        return match ($status) {
            'Pending' => 'warning',
            'Scheduled' => 'default',
            'Completed' => 'success',
            'Cancelled' => 'destructive',
            default => 'default'
        };
    }

    private function getPriorityVariant(string $priority): string
    {
        return match ($priority) {
            'High', 'Urgent' => 'destructive',
            'Normal' => 'default',
            'Low' => 'secondary',
            default => 'default'
        };
    }

    public function getRequestDetails($id): JsonResponse
    {
        $user = auth()->user();
        if (!$user->is_app_client) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $client = $user->appClient;
        if (!$client) {
            return response()->json(['error' => 'Client profile not found'], 404);
        }

        $serviceRequest = ServiceRequest::with(['service', 'activities.performer'])
            ->where('client_id', $client->id)
            ->findOrFail($id);

        return response()->json([
            'request' => [
                'id' => $serviceRequest->id,
                'reference' => $serviceRequest->reference,
                'service' => [
                    'name' => $serviceRequest->service->name,
                    'description' => $serviceRequest->service->description,
                    'price_type' => $serviceRequest->service->price_type,
                    'base_price' => $serviceRequest->service->base_price
                ],
                'status' => $serviceRequest->status,
                'priority' => $serviceRequest->priority,
                'requested_date' => $serviceRequest->requested_date->format('Y-m-d H:i:s'),
                'preferred_date' => $serviceRequest->preferred_date->format('Y-m-d H:i:s'),
                'scheduled_date' => $serviceRequest->scheduled_date?->format('Y-m-d H:i:s'),
                'description' => $serviceRequest->description,
                'activities' => $serviceRequest->activities->map(function ($activity) {
                    return [
                        'type' => $activity->activity_type,
                        'description' => $activity->description,
                        'performed_by' => $activity->performer->name,
                        'performed_at' => $activity->created_at->diffForHumans()
                    ];
                })
            ]
        ]);
    }
}
