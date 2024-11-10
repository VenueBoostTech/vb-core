<?php

namespace App\Http\Controllers\AppSuite\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\AppInvoice;
use App\Services\AppInvoiceService;
use App\Services\ClientAuthService;
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

        $query = AppInvoice::with(['serviceRequest.service'])
            ->where('client_id', $client->id);

        // Get stats
        $stats = [
            'due_now' => $query->whereIn('status', ['pending', 'overdue'])
                ->sum('total_amount'),
            'last_payment' => $query->where('status', 'paid')
                    ->latest()
                    ->first()?->total_amount ?? 0,
            'total_count' => $query->count()
        ];

        // Get paginated invoices
        $invoices = $query->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'stats' => $stats,
            'invoices' => [
                'data' => $invoices->map(function($invoice) {
                    return [
                        'id' => $invoice->id,
                        'number' => $invoice->number,
                        'service_name' => $invoice->serviceRequest->service->name,
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
}
