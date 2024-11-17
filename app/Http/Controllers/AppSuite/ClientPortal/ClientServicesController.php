<?php

namespace App\Http\Controllers\AppSuite\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\AppFeedback;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Services\ClientAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientServicesController extends Controller
{
    protected ClientAuthService $clientAuthService;

    public function __construct(ClientAuthService $clientAuthService)
    {
        $this->clientAuthService = $clientAuthService;
    }

    public function index(): JsonResponse
    {
        if ($response = $this->clientAuthService->validateClientAccess()) {
            return $response;
        }

        $client = $this->clientAuthService->getAuthenticatedClient();

        // Retrieve all services for the authenticated client
        $services = Service::where('venue_id', $client->venue_id)->get();

        // Initialize an array to store all services with their categorized requests
        $serviceList = [];

        foreach ($services as $service) {
            // Retrieve all requests for this service, categorized by status
            $requests = ServiceRequest::where('client_id', $client->id)
                ->where('service_id', $service->id)
                ->orderBy('created_at', 'desc')
                ->get();

            $categorizedRequests = [
                'active' => [],
                'pending' => [],
                'completed' => [],
                'declined' => []
            ];

            foreach ($requests as $request) {
                $requestData = [
                    'id' => $request->id,
                    'reference' => $request->reference,
                    'status' => $request->status,
                    'priority' => $request->priority,
                    'requested_date' => $request->requested_date,
                    'scheduled_date' => $request->scheduled_date,
                    'description' => $request->description,
                    'progress_updates' => $request->activities->map(function ($activity) {
                        return [
                            'id' => $activity->id,
                            'type' => $activity->activity_type,
                            'description' => $activity->description,
                            'date' => $activity->created_at,
                            'performed_by' => $activity->performer?->name ?? 'System'
                        ];
                    })
                ];

                // Categorize each request by its status
                switch ($request->status) {
                    case 'Scheduled':
                    case 'In Progress':
                        $categorizedRequests['active'][] = $requestData;
                        break;
                    case 'Pending':
                        $categorizedRequests['pending'][] = $requestData;
                        break;
                    case 'Completed':
                        $categorizedRequests['completed'][] = $requestData;
                        break;
                    case 'Cancelled':
                    case 'Declined':
                        $categorizedRequests['declined'][] = $requestData;
                        break;
                }
            }

            // Add service details along with categorized requests
            $serviceList[] = [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'description' => $service->description,
                'type' => $service->type ?? $service->category?->name ?? 'General',
                'price' => [
                    'type' => $service->price_type,
                    'base_amount' => $service->base_price,
                    'currency' => 'USD'
                ],
                'requests' => $categorizedRequests
            ];
        }

        return response()->json($serviceList);
    }

    public function available(): JsonResponse
    {
        if ($response = $this->clientAuthService->validateClientAccess()) {
            return $response;
        }

        $client = $this->clientAuthService->getAuthenticatedClient();

        // Get only active services
        $services = Service::where('venue_id', $client->venue_id)
            ->where('status', 'Active')
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'type' => $service->category?->name ?? 'General',
                    'price_type' => $service->price_type,
                    'base_price' => $service->base_price,
                    'duration' => $service->duration
                ];
            });

        return response()->json($services);
    }




    public function show($id): JsonResponse
    {
        if ($response = $this->clientAuthService->validateClientAccess()) {
            return $response;
        }

        $client = $this->clientAuthService->getAuthenticatedClient();

        // Find the service with the given ID that belongs to the client's venue
        $service = Service::where('venue_id', $client->venue_id)->find($id);

        if (!$service) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        // Retrieve the latest request and completed request count for this service
        $latestRequest = ServiceRequest::with(['activities' => function ($q) {
            $q->latest();
        }])
            ->where('client_id', $client->id)
            ->where('service_id', $id)
            ->latest()
            ->first();

        $completedRequestsCount = ServiceRequest::where('client_id', $client->id)
            ->where('service_id', $id)
            ->where('status', 'Completed')
            ->count();

        // Build the service details response
        return response()->json([
            'service_info' => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'type' => $service->type ?? $service->category?->name ?? 'General',
                'start_date' => $service->created_at,
                'price' => [
                    'type' => $service->price_type,
                    'base_amount' => $service->base_price,
                    'currency' => 'USD'
                ],
            ],
            'current_status' => [
                'status' => $latestRequest ? $latestRequest->status : 'Available',
                'next_scheduled_date' => $latestRequest?->scheduled_date,
                'latest_request_id' => $latestRequest?->id,
            ],
            'service_history' => [
                'total_completions' => $completedRequestsCount,
                'first_request_date' => ServiceRequest::where('client_id', $client->id)
                    ->where('service_id', $id)
                    ->oldest()
                    ->first()?->created_at,
                'latest_completion_date' => ServiceRequest::where('client_id', $client->id)
                    ->where('service_id', $id)
                    ->where('status', 'Completed')
                    ->latest()
                    ->first()?->completed_at,
            ],
            'current_request' => $latestRequest ? [
                'id' => $latestRequest->id,
                'reference' => $latestRequest->reference,
                'status' => $latestRequest->status,
                'priority' => $latestRequest->priority,
                'requested_date' => $latestRequest->requested_date,
                'preferred_date' => $latestRequest->preferred_date,
                'scheduled_date' => $latestRequest->scheduled_date,
                'description' => $latestRequest->description,
                'decline_reason' => in_array($latestRequest->status, ['Cancelled', 'Declined'])
                    ? $latestRequest->cancellation_reason
                    : null,
                'progress_updates' => $latestRequest->activities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'type' => $activity->activity_type,
                        'description' => $activity->description,
                        'date' => $activity->created_at,
                        'performed_by' => $activity->performer?->name ?? 'System'
                    ];
                }),
                'has_feedback' => $latestRequest->feedback !== null,
                'feedback_details' => $latestRequest->feedback ? [
                    'rating' => $latestRequest->feedback->rating,
                    'comment' => $latestRequest->feedback->comment,
                    'admin_response' => $latestRequest->feedback->admin_response,
                ] : null
            ] : null
        ]);
    }

    private function formatService(Service $service, $request = null, $isDeclined = false): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'type' => $service->category?->name ?? 'General',
            'status' => $request ? $request->status : 'Available',
            'nextDate' => $request?->scheduled_date,
            'decline_reason' => $isDeclined ? ($request->cancellation_reason ?? 'Request was declined') : null,
            'created_at' => $service->created_at,
            'updated_at' => $service->updated_at
        ];
    }


    public function submitFeedback(Request $request, $id): JsonResponse
    {
        if ($response = $this->clientAuthService->validateClientAccess()) {
            return $response;
        }

        $client = $this->clientAuthService->getAuthenticatedClient();
        $serviceRequest = ServiceRequest::where('client_id', $client->id)->findOrFail($id);

        // Validate request can receive feedback
        if ($serviceRequest->status !== 'Completed') {
            return response()->json(['error' => 'Can only submit feedback for completed requests'], 422);
        }

        if ($serviceRequest->feedback) {
            return response()->json(['error' => 'Feedback already submitted'], 422);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|between:1,5',
            'comment' => 'required|string|min:10|max:500',
        ]);

        $feedback = AppFeedback::create([
            'venue_id' => $client->venue_id,
            'client_id' => $client->id,
            'project_id' => $serviceRequest->project_id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'type' => 'equipment_service',
        ]);

        $serviceRequest->feedback()->associate($feedback);
        $serviceRequest->save();

        return response()->json($feedback);
    }

    public function getFeedback($id): JsonResponse
    {
        if ($response = $this->clientAuthService->validateClientAccess()) {
            return $response;
        }

        $client = $this->clientAuthService->getAuthenticatedClient();
        $serviceRequest = ServiceRequest::where('client_id', $client->id)
            ->with('feedback')
            ->findOrFail($id);

        return response()->json($serviceRequest->feedback);
    }

}
