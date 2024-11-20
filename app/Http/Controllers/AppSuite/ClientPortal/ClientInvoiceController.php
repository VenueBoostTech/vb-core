<?php

namespace App\Http\Controllers\AppSuite\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\AppInvoice;
use App\Services\AppInvoiceService;
use App\Services\ClientAuthService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClientInvoiceController extends Controller
{
    protected AppInvoiceService $invoiceService;
    protected ClientAuthService $clientAuthService;

    public function __construct(
        AppInvoiceService $invoiceService,
        ClientAuthService $clientAuthService
    ) {
        $this->invoiceService = $invoiceService;
        $this->clientAuthService = $clientAuthService;
    }

    public function index(Request $request): JsonResponse
    {
        // Validate client access
        if ($response = $this->clientAuthService->validateClientAccess()) {
            return $response;
        }

        $client = $this->clientAuthService->getAuthenticatedClient();

        $query = AppInvoice::where('client_id', $client->id)
            ->with('serviceRequest.service');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('serviceRequest.service', function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $invoices = $query->latest()->paginate($request->per_page ?? 10);

        // Calculate stats
        $stats = [
            'due_now' => $query->where('status', 'pending')->sum('total_amount'),
            'last_payment' => $query->where('status', 'paid')
                    ->orderByDesc('updated_at')
                    ->first()?->total_amount ?? 0,
            'total_count' => $invoices->total(),
        ];

        return response()->json([
            'stats' => $stats,
            'invoices' => [
                'data' => $invoices->map(function($invoice) {
                    return [
                        'id' => $invoice->id,
                        'number' => $invoice->number,
                        'service_name' => $invoice->serviceRequest->service->name ?? null,
                        'date' => $invoice->issue_date->format('M d, Y'),
                        'due_date' => $invoice->due_date->format('M d, Y'),
                        'amount' => $invoice->total_amount,
                        'status' => $invoice->status
                    ];
                }),
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'total_pages' => $invoices->lastPage(),
            ]
        ]);
    }

    public function show($id): JsonResponse
    {
        // Validate client access
        if ($response = $this->clientAuthService->validateClientAccess()) {
            return $response;
        }

        $client = $this->clientAuthService->getAuthenticatedClient();

        $invoice = AppInvoice::with(['serviceRequest.service', 'items', 'payments'])
            ->where('client_id', $client->id)
            ->findOrFail($id);

        return response()->json([
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'service' => [
                    'name' => $invoice->serviceRequest->service->name,
                    'description' => $invoice->serviceRequest->description
                ],
                'dates' => [
                    'issue_date' => $invoice->issue_date->format('Y-m-d'),
                    'due_date' => $invoice->due_date->format('Y-m-d')
                ],
                'amounts' => [
                    'subtotal' => $invoice->amount,
                    'tax' => $invoice->tax_amount,
                    'total' => $invoice->total_amount
                ],
                'status' => $invoice->status,
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
                        'date' => $payment->payment_date?->format('Y-m-d')
                    ];
                })
            ]
        ]);
    }

    public function initiatePayment(Request $request, $id): JsonResponse
    {
        // Validate client access
        if ($response = $this->clientAuthService->validateClientAccess()) {
            return $response;
        }

        $client = $this->clientAuthService->getAuthenticatedClient();

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:card,bank_transfer,cash',
            'payment_date' => [
                'nullable', // Allows payment_date to be null for "card" payments
                'date',
                'after:today'
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $invoice = AppInvoice::where('client_id', $client->id)
                ->whereIn('status', ['pending', 'overdue'])
                ->findOrFail($id);

            $payment = $this->invoiceService->processPayment($invoice, [
                'payment_method' => $request->payment_method,
                'payment_due_date' => $request->payment_date ? Carbon::parse($request->payment_date) : null,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Payment initiated successfully',
                'payment' => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'method' => $payment->payment_method,
                    'metadata' => $payment->metadata // Contains Stripe client secret or bank details
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to initiate payment: ' . $e->getMessage()], 500);
        }
    }

    public function downloadPdf($id)
    {
        // Validate client access
        if ($response = $this->clientAuthService->validateClientAccess()) {
            return $response;
        }

        $client = $this->clientAuthService->getAuthenticatedClient();

        try {
            $invoice = AppInvoice::with([
                'client',
                'serviceRequest.service',
                'items',
                'payments'
            ])->where('client_id', $client->id)
                ->findOrFail($id);

            $pdf = PDF::loadView('invoices.pdf', [
                'invoice' => $invoice
            ]);

            // Convert to base64
            $base64 = base64_encode($pdf->output());

            return response()->json([
                'data' => $base64,
                'filename' => "invoice-{$invoice->number}.pdf"
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate PDF'], 500);
        }
    }
}
