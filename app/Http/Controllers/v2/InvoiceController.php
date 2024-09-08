<?php

namespace App\Http\Controllers\v2;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Services\VenueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class InvoiceController extends Controller
{

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $data
     * @return \Illuminate\Http\Response
     */
    public function store($data): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $userID = auth()->user()->id;

        if ($data instanceof Request) {
            $data = $data;
        } else {
            return response()->json(['error' => 'Invalid data provided.'], 400);
        }

        $validator = Validator::make($data->all(), [
            'customer_id' => 'required',
            'type' => 'required|string',
            'invoice_items' => 'required|array',
            'invoice_items.*.product_id' => 'required|exists:products,id',
            'invoice_items.*.quantity' => 'required|integer|min:1',
            'total_amount' => 'required|numeric',
            'payment_method' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $customer = Customer::with('customerAddresses')->find($data->customer_id);

        $address_id = null;
        if ($data->type === 'delivery') {
            $address = $customer->addresses->first();
            $address_id = $address ? $address->id : null;
        } else if ($data->type === 'onsite') {
            $address = $venue->addresses()->first();
            $address_id = $address ? $address->id : null;
        }

        if (!$address_id) {
            return response()->json(['error' => 'Address not found.'], 400);
        }

        $calculatedTotalAmount = 0;
        $productIds = collect($data->invoice_items)->pluck('product_id');
        $products = Product::findMany($productIds)->keyBy('id');

        // Static VAT rate
        $staticVatRate = 0.20;

        $data->total_amount += $data->total_amount * 0.20;

        $invoiceItems = [];
        foreach ($data->invoice_items as $item) {
            if (!$products->has($item['product_id'])) {
                return response()->json(['error' => "Product with ID {$item['product_id']} not found."], 404);
            }

            $product = $products->get($item['product_id']);
            $unitPrice = $product->price;
            $quantity = $item['quantity'];

            $totalWithVAT = $unitPrice * $quantity * (1 + $staticVatRate);

            $calculatedTotalAmount += $totalWithVAT;

            $invoiceItems[] = [
                'product_id' => $item['product_id'],
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'vat_rate' => $staticVatRate * 100,
                'total_with_vat' => $totalWithVAT,
            ];
        }

        if (abs($calculatedTotalAmount - $data->total_amount) > 0.01) {
            return response()->json(['error' => 'Calculated total amount does not match the provided total amount.'], 400);
        }

        $invoice = new Invoice([
            'customer_id' => $data->customer_id,
            'venue_id' => $venue->id,
            'address_id' => $address_id,
            'user_id' => $userID,
            'type' => $data->type,
            'date_issued' => now(),
            'total_amount' => $data->total_amount,
            'status' => OrderStatus::ORDER_PAID,
            'payment_method' => $data->payment_method,
        ]);
        $invoice->save();

        foreach ($invoiceItems as $invoiceItem) {
            $invoiceItem['invoice_id'] = $invoice->id;
            InvoiceItem::create($invoiceItem);
        }

        return response()->json([
            'message' => 'Invoice created successfully'
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function dailyInvoiceSummary(Request $request): \Illuminate\Http\JsonResponse
    {
        $today = Carbon::today();

        // Shift times
        $morningShiftStart = $today->copy()->setHour(7)->setMinute(0);
        $morningShiftEnd = $today->copy()->setHour(15)->setMinute(0);
        $eveningShiftStart = $morningShiftEnd; // 3:00 PM
        $eveningShiftEnd = $today->copy()->setHour(22)->setMinute(0);

        $invoices = Invoice::with('user')
            ->whereDate('created_at', $today)
            ->get();

        $invoicesByUser = $invoices->groupBy('user_id');

        $userSummaries = $invoicesByUser->map(function ($userInvoices, $userId) use ($morningShiftStart, $morningShiftEnd, $eveningShiftStart, $eveningShiftEnd) {
            $morningShiftInvoices = $userInvoices->filter(function ($invoice) use ($morningShiftStart, $morningShiftEnd) {
                return $invoice->created_at->between($morningShiftStart, $morningShiftEnd);
            });
            $eveningShiftInvoices = $userInvoices->filter(function ($invoice) use ($eveningShiftStart, $eveningShiftEnd) {
                return $invoice->created_at->between($eveningShiftStart, $eveningShiftEnd);
            });

            return [
                'user_id' => $userId,
                'morning_shift' => [
                    'total_amount' => $morningShiftInvoices->sum('total_amount'),
                    'invoice_count' => $morningShiftInvoices->count(),
                    'invoices' => $morningShiftInvoices
                ],
                'evening_shift' => [
                    'total_amount' => $eveningShiftInvoices->sum('total_amount'),
                    'invoice_count' => $eveningShiftInvoices->count(),
                    'invoices' => $eveningShiftInvoices
                ],
            ];
        });

        return response()->json($userSummaries);
    }
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $data
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $data, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //test
    }
}
