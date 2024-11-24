<?php
namespace App\Http\Controllers\AppSuite\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\AppSupportTicket;
use App\Services\ClientAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClientTicketController extends Controller
{
    protected ClientAuthService $clientAuthService;

    public function __construct(ClientAuthService $clientAuthService)
    {
        $this->clientAuthService = $clientAuthService;
    }

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->clientAuthService->validateClientAccess()) {
            return $response;
        }

        $client = $this->clientAuthService->getAuthenticatedClient();

        $query = AppSupportTicket::with(['messages', 'venue', 'project', 'service', 'serviceRequest'])
            ->where('client_id', $client->id);

        // Handle status filter
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->whereIn('status', ['open', 'in_progress']);
            } elseif ($request->status !== 'all') {
                $query->where('status', $request->status);
            }
        }

        // Handle priority filter
        if ($request->filled('priority') && $request->priority !== 'all') {
            $query->where('priority', strtolower($request->priority));
        }

        // Handle search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $tickets = $query->latest()->paginate($request->per_page ?? 10);

        // Update stats based on current client
        $stats = [
            'active_count' => $client->supportTickets()
                ->whereIn('status', ['open', 'in_progress'])
                ->count(),
            'resolved_count' => $client->supportTickets()
                ->where('status', 'resolved')
                ->count(),
            'avg_response_time' => $this->calculateAverageResponseTime($client->id)
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

    private function calculateAverageResponseTime($clientId): string
    {
        try {
            // Get average time between ticket creation and first staff response
            $avgTime = DB::table('app_support_tickets as t')
                ->join('app_support_ticket_messages as m', 't.id', '=', 'm.app_support_ticket_id')
                ->where('t.client_id', $clientId)
                ->where('m.sender_type', 'staff') // Only staff responses
                ->whereRaw('m.id IN (
                SELECT MIN(id)
                FROM app_support_ticket_messages
                WHERE sender_type = "staff"
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

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->clientAuthService->validateClientAccess()) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'app_project_id' => 'nullable|exists:app_projects,id',
            'service_id' => 'nullable|exists:services,id',
            'service_request_id' => 'nullable|exists:service_requests,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $client = $this->clientAuthService->getAuthenticatedClient();

        DB::beginTransaction();
        try {
            $ticket = AppSupportTicket::create([
                'number' => AppSupportTicket::generateNumber(),
                'client_id' => $client->id,
                'venue_id' => $client->venue_id,
                'subject' => $request->subject,
                'description' => $request->description,
                'priority' => $request->priority,
                'status' => AppSupportTicket::STATUS_OPEN,
                'app_project_id' => $request->app_project_id,
                'service_id' => $request->service_id,
                'service_request_id' => $request->service_request_id,
            ]);

            // Create initial message
            $ticket->messages()->create([
                'message' => $request->description,
                'sender_type' => 'client',
                'sender_id' => $client->id,
                'attachments' => $request->attachments ?? []
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Ticket created successfully',
                'ticket' => $this->formatTicket($ticket)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create ticket'], 500);
        }
    }

    public function show($id): JsonResponse
    {
        if ($response = $this->clientAuthService->validateClientAccess()) {
            return $response;
        }

        $client = $this->clientAuthService->getAuthenticatedClient();

        $ticket = AppSupportTicket::with(['messages.sender', 'assignedEmployee', 'project', 'service', 'serviceRequest'])
            ->where('client_id', $client->id)
            ->findOrFail($id);

        return response()->json([
            'ticket' => $this->formatTicket($ticket)
        ]);
    }

    public function reply(Request $request, $id): JsonResponse
    {
        if ($response = $this->clientAuthService->validateClientAccess()) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'attachments' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $client = $this->clientAuthService->getAuthenticatedClient();

        $ticket = AppSupportTicket::where('client_id', $client->id)->findOrFail($id);

        DB::beginTransaction();
        try {
            $message = $ticket->messages()->create([
                'message' => $request->message,
                'sender_type' => 'client',
                'sender_id' => $client->id,
                'attachments' => $request->attachments ?? []
            ]);

            $ticket->update([
                'last_reply_at' => now(),
                'status' => $ticket->status === 'resolved' ? 'open' : $ticket->status
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
                $ticket->messages->map(fn($msg) => $this->formatMessage($msg)) : []
        ];
    }

    private function formatMessage($message): array
    {
        return [
            'id' => $message->id,
            'message' => $message->message,
            'sender_type' => $message->sender_type,
            'sender' => [
                'id' => $message->sender->id,
                'name' => $message->sender->name
            ],
            'attachments' => $message->attachments,
            'created_at' => $message->created_at
        ];
    }
}
