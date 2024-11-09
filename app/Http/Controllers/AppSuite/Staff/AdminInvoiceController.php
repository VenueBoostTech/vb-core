<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppInvoice;
use App\Models\ServiceRequest;
use App\Services\AppInvoiceService;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminInvoiceController extends Controller
{
    protected VenueService $venueService;
    protected AppInvoiceService $invoiceService;

    public function __construct(VenueService $venueService, AppInvoiceService $invoiceService)
    {
        $this->venueService = $venueService;
        $this->invoiceService = $invoiceService;
    }

    public function index(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $query = AppInvoice::with(['client', 'serviceRequest.service'])
            ->where('venue_id', $venue->id);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('client', function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $invoices = $query->latest()->paginate($request->input('per_page', 15));

        // Calculate stats
        $stats = [
            'total_revenue' => $query->where('status', 'paid')
                ->sum('total_amount'),
            'outstanding_amount' => $query->whereIn('status', ['pending', 'overdue'])
                ->sum('total_amount'),
            'paid_this_month' => $query->where('status', 'paid')
                ->whereMonth('updated_at', Carbon::now()->month)
                ->sum('total_amount'),
            'overdue_amount' => $query->where('status', 'overdue')
                ->sum('total_amount')
        ];

        return response()->json([
            'invoices' => [
                'data' => $invoices->map(function($invoice) {
                    return [
                        'id' => $invoice->id,
                        'number' => $invoice->number,
                        'client' => $invoice->client->name,
                        'service' => $invoice->serviceRequest->service->name,
                        'amount' => $invoice->total_amount,
                        'date' => $invoice->issue_date->format('M d, Y'),
                        'due_date' => $invoice->due_date->format('M d, Y'),
                        'status' => $invoice->status,
                        'payment_method' => $invoice->payment_method
                    ];
                }),
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'total_pages' => $invoices->lastPage(),
            ],
            'stats' => $stats
        ]);
    }

    public function generateInvoice(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'service_request_id' => 'required|exists:service_requests,id',
            'due_date' => 'required|date|after:today',
            'payment_terms' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $serviceRequest = ServiceRequest::where('venue_id', $venue->id)
                ->findOrFail($request->service_request_id);

            $invoice = $this->invoiceService->generateFromServiceRequest($serviceRequest, [
                'due_date' => Carbon::parse($request->due_date),
                'payment_terms' => $request->payment_terms,
                'notes' => $request->notes
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Invoice generated successfully',
                'invoice' => $invoice->load('items')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to generate invoice: ' . $e->getMessage()], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $invoice = AppInvoice::with([
            'client',
            'serviceRequest.service',
            'items',
            'payments'
        ])->where('venue_id', $venue->id)
            ->findOrFail($id);

        return response()->json([
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'client' => [
                    'name' => $invoice->client->name,
                    'address' => $invoice->client->address?->full_address,
                    'phone' => $invoice->client->phone,
                    'email' => $invoice->client->email
                ],
                'service' => [
                    'name' => $invoice->serviceRequest->service->name,
                    'description' => $invoice->serviceRequest->service->description
                ],
                'dates' => [
                    'issue_date' => $invoice->issue_date->format('Y-m-d'),
                    'due_date' => $invoice->due_date->format('Y-m-d'),
                    'payment_due_date' => $invoice->payment_due_date?->format('Y-m-d')
                ],
                'amounts' => [
                    'subtotal' => $invoice->amount,
                    'tax' => $invoice->tax_amount,
                    'total' => $invoice->total_amount
                ],
                'status' => $invoice->status,
                'payment_method' => $invoice->payment_method,
                'payment_terms' => $invoice->payment_terms,
                'notes' => $invoice->notes,
                'items' => $invoice->items->map(function($item) {
                    return [
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'rate' => $item->rate,
                        'amount' => $item->amount
                    ];
                }),
                'payments' => $invoice->payments->map(function($payment) {
                    return [
                        'amount' => $payment->amount,
                        'method' => $payment->payment_method,
                        'status' => $payment->status,
                        'date' => $payment->payment_date?->format('Y-m-d'),
                        'transaction_id' => $payment->transaction_id
                    ];
                })
            ]
        ]);
    }
}
