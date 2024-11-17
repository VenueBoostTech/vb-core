<?php

namespace App\Http\Controllers\AppSuite\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\AppInvoice;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestActivity;
use App\Services\ClientAuthService;
use Illuminate\Http\JsonResponse;

class ClientDashboardController extends Controller
{
    protected ClientAuthService $clientAuthService;

    public function __construct(ClientAuthService $clientAuthService)
    {
        $this->clientAuthService = $clientAuthService;
    }

    public function getDashboardData(): JsonResponse
    {
        if ($response = $this->clientAuthService->validateClientAccess()) {
            return $response;
        }

        $client = $this->clientAuthService->getAuthenticatedClient();

        // Get active services
        $activeServices = Service::where('venue_id', $client->venue_id)
            ->whereHas('serviceRequests', function($q) use ($client) {
                $q->where('client_id', $client->id)
                    ->whereIn('status', ['Scheduled', 'In Progress']);
            })
            ->get()
            ->map(function($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'status' => $service->status
                ];
            });

        // Get invoices
        $recentInvoices = AppInvoice::where('client_id', $client->id)
            ->latest('issue_date')
            ->take(3)
            ->get()
            ->map(function($invoice) {
                return [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'date' => $invoice->issue_date,
                    'amount' => $invoice->total_amount,
                    'status' => $invoice->status
                ];
            });

        // Get activities
        $activities = ServiceRequestActivity::whereHas('serviceRequest', function($q) use ($client) {
            $q->where('client_id', $client->id);
        })
            ->with(['serviceRequest', 'performer'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function($activity) {
                return [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'date' => $activity->created_at,
                    'type' => $activity->activity_type
                ];
            });

        // Get next service date
        $nextService = ServiceRequest::where('client_id', $client->id)
            ->where('status', 'Scheduled')
            ->where('scheduled_date', '>', now())
            ->orderBy('scheduled_date')
            ->first();

        // Calculate stats
        $stats = [
            'active_services' => $activeServices->count(),
            'pending_payments' => AppInvoice::where('client_id', $client->id)
                ->whereIn('status', ['pending', 'overdue'])
                ->sum('total_amount'),
            'total_invoices' => AppInvoice::where('client_id', $client->id)->count()
        ];

        return response()->json([
            'stats' => $stats,
            'next_service_date' => $nextService?->scheduled_date,
            'active_services' => $activeServices,
            'recent_invoices' => $recentInvoices,
            'activity' => $activities
        ]);
    }
}
