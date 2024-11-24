<?php
// app/Http/Controllers/AppSuite/Staff/AdminTicketController.php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppSupportTicket;
use App\Models\Employee;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminTicketController extends Controller
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

        $query = AppSupportTicket::with(['client', 'assignedEmployee', 'messages'])
            ->where('venue_id', $venue->id);

        // Apply filters
        if ($request->status) {
            if ($request->status === 'active') {
                $query->whereIn('status', ['open', 'in_progress']);
            } elseif ($request->status !== 'all') {
                $query->where('status', $request->status);
            }
        }

        if ($request->priority && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('subject', 'like', "%{$request->search}%")
                    ->orWhere('number', 'like', "%{$request->search}%")
                    ->orWhereHas('client', function($q2) use ($request) {
                        $q2->where('name', 'like', "%{$request->search}%");
                    });
            });
        }

        $tickets = $query->latest()->paginate($request->per_page ?? 10);

        // Calculate real stats
        $stats = [
            'open_tickets' => AppSupportTicket::where('venue_id', $venue->id)
                ->whereIn('status', ['open', 'in_progress'])
                ->count(),
            'avg_response_time' => $this->calculateAverageResponseTime($venue->id),
            'resolution_rate' => $this->calculateResolutionRate($venue->id),
            'active_agents' => Employee::where('restaurant_id', $venue->id)
                ->whereHas('assignedTickets', function($query) {
                    $query->whereIn('status', ['open', 'in_progress']);
                })
                ->count()
        ];

        return response()->json([
            'tickets' => [
                'data' => $tickets->map(fn($ticket) => $this->formatTicket($ticket)),
                'current_page' => $tickets->currentPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
                'total_pages' => $tickets->lastPage(),
            ],
            'stats' => $stats
        ]);
    }

    public function show($id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $ticket = AppSupportTicket::with([
            'client',
            'assignedEmployee',
            'messages.sender',
            'project',
            'service',
            'serviceRequest'
        ])
        ->where('venue_id', $venue->id)
        ->findOrFail($id);

        return response()->json([
            'ticket' => $this->formatTicket($ticket)
        ]);
    }

    public function reply(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'attachments' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ticket = AppSupportTicket::where('venue_id', $venue->id)->findOrFail($id);

        $employee = Employee::where('restaurant_id', $venue->id)
            ->where('user_id', auth()->id())->first();

        // not found check
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        DB::beginTransaction();
        try {
            $message = $ticket->messages()->create([
                'message' => $request->message,
                'sender_type' => 'employee',
                'sender_id' => $employee->id,
                'attachments' => $request->attachments ?? []
            ]);

            $ticket->update([
                'last_reply_at' => now(),
                'status' => 'in_progress'
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Reply added successfully',
                'ticket_message' => $this->formatMessage($message)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to add reply'], 500);
        }
    }

    public function assign(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ticket = AppSupportTicket::where('venue_id', $venue->id)->findOrFail($id);

        $employee = Employee::where('restaurant_id', $venue->id)
            ->findOrFail($request->employee_id);

        $ticket->update([
            'employee_id' => $employee->id,
            'status' => 'in_progress'
        ]);

        return response()->json([
            'message' => 'Ticket assigned successfully',
            'ticket' => $this->formatTicket($ticket)
        ]);
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:open,in_progress,resolved,closed'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ticket = AppSupportTicket::where('venue_id', $venue->id)->findOrFail($id);

        $ticket->update([
            'status' => $request->status,
            'resolved_at' => in_array($request->status, ['resolved', 'closed']) ? now() : null
        ]);

        return response()->json([
            'message' => 'Status updated successfully',
            'ticket' => $this->formatTicket($ticket)
        ]);
    }

    public function updatePriority(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'priority' => 'required|in:low,medium,high,urgent'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ticket = AppSupportTicket::where('venue_id', $venue->id)->findOrFail($id);

        $ticket->update(['priority' => $request->priority]);

        return response()->json([
            'message' => 'Priority updated successfully',
            'ticket' => $this->formatTicket($ticket)
        ]);
    }

    private function formatTicket($ticket): array
    {
        return [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
            'last_reply_at' => $ticket->last_reply_at,
            'client' => $ticket->client ? [
                'id' => $ticket->client->id,
                'name' => $ticket->client->name,
                'email' => $ticket->client->email
            ] : null,
            'assigned_to' => $ticket->assignedEmployee ? [
                'id' => $ticket->assignedEmployee->id,
                'name' => $ticket->assignedEmployee->name
            ] : null,
            'related_to' => [
                'project' => $ticket->project ? [
                    'id' => $ticket->project->id,
                    'name' => $ticket->project->name
                ] : null,
                'service' => $ticket->service ? [
                    'id' => $ticket->service->id,
                    'name' => $ticket->service->name
                ] : null,
                'service_request' => $ticket->serviceRequest ? [
                    'id' => $ticket->serviceRequest->id,
                    'reference' => $ticket->serviceRequest->reference
                ] : null
            ],
            'messages' => $ticket->messages ?
                $ticket->messages->map(fn($msg) => $this->formatMessage($msg))->toArray()
                : []
        ];
    }

    private function formatMessage($message): array
    {
        return [
            'id' => $message->id,
            'message' => $message->message,
            'sender_type' => $message->sender_type,
            'sender' => $message->sender ? [
                'id' => $message->sender->id,
                'name' => $message->sender->name
            ] : null,
            'attachments' => $message->attachments,
            'created_at' => $message->created_at
        ];
    }

    private function calculateAverageResponseTime($venueId): string
    {
        try {
            // Calculate average time between ticket creation and first staff response for venue
            $avgTime = DB::table('app_support_tickets as t')
                ->join('app_support_ticket_messages as m', 't.id', '=', 'm.app_support_ticket_id')
                ->where('t.venue_id', $venueId)
                ->where('m.sender_type', 'employee') // Staff/employee responses
                ->whereRaw('m.id IN (
                SELECT MIN(id)
                FROM app_support_ticket_messages
                WHERE sender_type = "employee"
                GROUP BY app_support_ticket_id
            )')
                ->avg(DB::raw('TIMESTAMPDIFF(MINUTE, t.created_at, m.created_at)'));

            if (!$avgTime) return 'N/A';

            if ($avgTime < 60) {
                return round($avgTime) . 'm';
            }

            return round($avgTime / 60, 1) . 'h';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    private function calculateResolutionRate($venueId): string
    {
        try {
            // Get total tickets in last 30 days
            $thirtyDaysAgo = now()->subDays(30);
            $totalTickets = AppSupportTicket::where('venue_id', $venueId)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->count();

            if ($totalTickets === 0) {
                return '0%';
            }

            // Get resolved tickets
            $resolvedTickets = AppSupportTicket::where('venue_id', $venueId)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->whereIn('status', ['resolved', 'closed'])
                ->count();

            $rate = ($resolvedTickets / $totalTickets) * 100;
            return round($rate, 1) . '%';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
}
