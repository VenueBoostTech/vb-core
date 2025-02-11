<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Models\AccountingFinance\Currency;
use App\Models\VbStoreProductAttribute;
use App\Models\VbStoreProductVariant;
use App\Models\VbStoreProductVariantAttribute;
use App\Services\BktPaymentService;
use App\Services\InventoryService;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Cart;
use App\Models\PostalPricing;
use App\Models\Restaurant;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;

class BBCheckoutController extends Controller
{
    protected $bktPaymentService;

    public function __construct(BktPaymentService $bktPaymentService)
    {
        $this->bktPaymentService = $bktPaymentService;
    }

    /**
     * Main checkout endpoint for processing orders
     */
    public function checkout(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validatedData = $this->validateCheckoutRequest($request);
            if (isset($validatedData['error'])) {
                return response()->json($validatedData['error'], 400);
            }

            // Get venue
            $venue = $this->getAndValidateVenue($request->app_key);
            if (!$venue) {
                return response()->json(['message' => 'Invalid venue'], 404);
            }

            // Process customer
            $customer = $this->getOrCreateCustomer([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'venueShortCode' => $venue->short_code
            ]);

            // Calculate totals
            $orderTotals = $this->calculateOrderTotals($request->order_products);

            // Process order based on payment method
            if ($request->payment_method === 'cash') {
                $order = $this->processCashOrder($venue, $customer, $orderTotals, $request);

                // Send webhook after successful creation
                $this->sendOrderWebhook($order, 'regular_checkout');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Order created successfully',
                    'order' => $order
                ]);
            } else {
                $order = $this->processCardOrder($venue, $customer, $orderTotals, $request);

                // Get payment URL from BKT
                $paymentInfo = $this->bktPaymentService->initiatePayment([
                    'id' => $order->id,
                    'total' => $order->total_amount
                ]);

                // Send webhook
                $this->sendOrderWebhook($order, 'regular_checkout');

                return response()->json([
                    'status' => 'success',
                    'payment_url' => $paymentInfo['url'],
                    'payment_data' => $paymentInfo['data']
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Checkout error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error processing checkout: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quick checkout for single product purchases
     */
    public function quickCheckout(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'app_key' => 'required|string',
                'first_name' => 'required|string',
                'last_name' => 'nullable|string',
                'address' => 'required|string',
                'phone' => 'required|string',
                'email' => 'nullable|string|email',
                'product_id' => 'required|integer',
                'payment_method' => 'required|string|in:cash,card'
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => $validator->errors()], 400);
            }

            $venue = $this->getAndValidateVenue($request->app_key);
            if (!$venue) {
                return response()->json(['message' => 'Invalid venue'], 404);
            }

            // Get product
            $product = Product::find($request->product_id);
            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            // Transform to regular order format
            $orderRequest = $request->all();
            $orderRequest['order_products'] = [[
                'id' => $product->id,
                'product_quantity' => 1
            ]];

            return $this->checkout(new Request($orderRequest));

            // Note: we don't create customer during quick checkout
            // maybe we should check tomrorow

        } catch (\Exception $e) {
            Log::error('Quick checkout error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error processing quick checkout: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper Methods
     */
    private function validateCheckoutRequest(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'app_key' => 'required|string',
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'address' => 'required|string',
            'phone' => 'required|string',
            'email' => 'nullable|string|email',
            'country' => 'nullable|string',
            'city' => 'required|string',
            'payment_method' => 'required|string|in:cash,card',
            'order_products' => 'required|array',
            'order_products.*.id' => 'required|integer',
            'order_products.*.product_quantity' => 'required|integer|min:1',
            'order_products.*.variant_id' => 'nullable|integer',
            'order_products.*.options' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return ['error' => ['message' => $validator->errors()]];
        }

        return ['success' => true];
    }

    private function getAndValidateVenue(string $appKey): ?Restaurant
    {
        return Restaurant::where('app_key', $appKey)
            // ->where('status', 'active')
            ->first();
    }

    private function getOrCreateCustomer(array $customerData): Customer
    {
        if ($customerData['email']) {
            // First check if customer exists directly
            $existingCustomer = Customer::where('email', $customerData['email'])->first();
            if ($existingCustomer) {
                return $existingCustomer;
            }

            // If no customer but user exists
            $user = User::where('email', $customerData['email'])->first();
            if ($user) {
                $customer = Customer::firstOrCreate(
                    ['email' => $customerData['email']],
                    [
                        'user_id' => $user->id,
                        'name' => $customerData['first_name'] . ' ' . $customerData['last_name'],
                        'phone' => $customerData['phone'],
                        'address' => $customerData['address']
                    ]
                );
                return $customer;
            }

            // Create new user and customer
            $user = User::create([
                'name' => $customerData['first_name'] . ' ' . $customerData['last_name'],
                'email' => $customerData['email'],
                'password' => Str::random(12),
                'first_name' => $customerData['first_name'],
                'last_name' => $customerData['last_name'],
                'country_code' => 'US',
                'status' => 1
            ]);

            $customer = Customer::create([
                'user_id' => $user->id,
                'name' => $customerData['first_name'] . ' ' . $customerData['last_name'],
                'email' => $customerData['email'],
                'phone' => $customerData['phone'],
                'address' => $customerData['address']
            ]);

            // Only for new customers, register with OmniStack
            try {
                $data_string = [
                    "external_ids" => [
                        "venueBoostUserId" => $user->id,
                        "venueBoostCustomerId" => $customer->id
                    ],
                    "registrationSource" => "metroshop",
                    "name" => $customerData['first_name'],
                    "surname" => $customerData['last_name'],
                    "email" => $customerData['email'],
                    "phone" => $customer->phone,
                    "password" => Str::random(12),
                    "address" => [
                        "addressLine1" => $customerData['address'],
                        "city" => $customerData['city'] ?? '',
                        "state" => $customerData['state'] ?? '',
                        "country" => $customerData['country'] ?? '',
                        "postcode" => $customerData['postcode'] ?? ''
                    ]
                ];

                $response = Http::withHeaders([
                    'webhook-api-key' => env('OMNISTACK_GATEWAY_MSHOP_API_KEY'),
                    'x-api-key' => env('OMNISTACK_GATEWAY_API_KEY'),
                ])->post(rtrim(env('OMNISTACK_GATEWAY_BASEURL'), '/') . '/users/' . $customerData['venueShortCode'] . '/register', $data_string);

                if ($response->successful()) {
                    $customerExternalIds['omniStackGateway'] = $response->json('customerId');
                    $customer->external_ids = json_encode($customerExternalIds);
                    $customer->save();

                    $userExternalIds['omniStackGateway'] = $response->json('userId');
                    $user->external_ids = json_encode($userExternalIds);
                    $user->save();
                }
            } catch (\Throwable $th) {
                \Sentry\captureException($th);
            }

            return $customer;
        }

        // Handle guest checkout with temp email
        $tempEmail = $this->generateTempEmail();
        $user = User::create([
            'name' => $customerData['first_name'] . ' ' . $customerData['last_name'],
            'email' => $tempEmail,
            'password' => Str::random(12),
            'first_name' => $customerData['first_name'],
            'last_name' => $customerData['last_name'],
            'country_code' => 'US',
            'status' => 1
        ]);

        return Customer::create([
            'user_id' => $user->id,
            'name' => $customerData['first_name'] . ' ' . $customerData['last_name'],
            'email' => $tempEmail,
            'phone' => $customerData['phone'],
            'address' => $customerData['address']
        ]);
    }

    private function calculateOrderTotals(array $products): array
    {
        $subtotal = 0;
        $total = 0;
        $discount = 0;

        foreach ($products as $productData) {
            $product = Product::find($productData['id']);
            if (!$product) continue;

            $price = $product->price;

            // Handle variant pricing
            if (isset($productData['variant_id'])) {
                $variant = VbStoreProductVariant::find($productData['variant_id']);
                if ($variant && $variant->price) {
                    $price = $variant->price;
                }
            }

            $itemTotal = $price * $productData['product_quantity'];
            $subtotal += $itemTotal;
        }

        $total = $subtotal - $discount;

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total
        ];
    }

    private function processCashOrder(Restaurant $venue, Customer $customer, array $totals, Request $request): Order
    {
        $order = Order::create([
            'customer_id' => $customer->id,
            'restaurant_id' => $venue->id,
            'order_number' => $this->generateOrderNumber($venue),
            'status' => OrderStatus::NEW_ORDER,
            'payment_method_id' => PaymentMethod::CASH,
            'payment_status' => 'pending',
            'subtotal' => $totals['subtotal'],
            'discount' => $totals['discount'],
            'total_amount' => $totals['total'],
            'shipping_name' => $request->first_name,
            'shipping_surname' => $request->last_name,
            'shipping_address' => $request->address,
            'shipping_city' => $request->city,
            'shipping_state' => $request->country,
            'shipping_phone_no' => $request->phone,
            'shipping_email' => $request->email,
            'billing_name' => $request->first_name,
            'billing_surname' => $request->last_name,
            'billing_address' => $request->address,
            'billing_city' => $request->city,
            'billing_state' => $request->country,
            'billing_phone_no' => $request->phone,
            'billing_email' => $request->email
        ]);

        $this->createOrderProducts($order, $request->order_products);

        return $order;
    }

    private function processCardOrder(Restaurant $venue, Customer $customer, array $totals, Request $request): Order
    {
        $order = Order::create([
            'customer_id' => $customer->id,
            'restaurant_id' => $venue->id,
            'order_number' => $this->generateOrderNumber($venue),
            'status' => OrderStatus::PENDING,
            'payment_method_id' => PaymentMethod::CARD,
            'payment_status' => 'pending',
            'subtotal' => $totals['subtotal'],
            'discount' => $totals['discount'],
            'total_amount' => $totals['total'],
            'shipping_name' => $request->first_name,
            'shipping_surname' => $request->last_name,
            'shipping_address' => $request->address,
            'shipping_city' => $request->city,
            'shipping_state' => $request->country,
            'shipping_phone_no' => $request->phone,
            'shipping_email' => $request->email,
            'billing_name' => $request->first_name,
            'billing_surname' => $request->last_name,
            'billing_address' => $request->address,
            'billing_city' => $request->city,
            'billing_state' => $request->country,
            'billing_phone_no' => $request->phone,
            'billing_email' => $request->email
        ]);

        $this->createOrderProducts($order, $request->order_products);

        return $order;
    }

    private function createOrderProducts(Order $order, array $products): void
    {
        foreach ($products as $productData) {
            $product = Product::find($productData['id']);
            if (!$product) continue;

            $metadata = [
                'external_ids' => $product->external_ids ?? null,
                'variant' => null,
                'options' => []
            ];

            // Handle variants
            if (isset($productData['variant_id'])) {
                $variant = VbStoreProductVariant::find($productData['variant_id']);
                if ($variant) {
                    $metadata['variant'] = [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'sku' => $variant->sku,
                        'price' => $variant->price
                    ];
                }
            }

            // Handle options
            if (isset($productData['options']) && is_array($productData['options'])) {
                foreach ($productData['options'] as $optionId) {
                    $option = VbStoreProductAttribute::find($optionId);
                    if ($option) {
                        $metadata['options'][] = [
                            'id' => $option->id,
                            'name' => $option->name,
                            'value' => $option->value
                        ];
                    }
                }
            }

            OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'variant_id' => $metadata['variant']['id'] ?? null,
                'product_quantity' => $productData['product_quantity'],
                'product_total_price' => $product->price * $productData['product_quantity'],
                'product_discount_price' => 0,
                'metadata' => $metadata
            ]);
        }
    }

    private function generateOrderNumber(Restaurant $venue): string
    {
        $prefix = strtoupper(substr($venue->name, 0, 2));
        $timestamp = time();
        $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        return "{$prefix}-{$timestamp}{$random}";
    }

    private function generateTempEmail(): string
    {
        return Str::random(10) . '@temp.com';
    }

    private function sendOrderWebhook(Order $order, string $checkoutType): void
    {
        try {
            $venue = Restaurant::find($order->restaurant_id);
            if (!$venue || !$venue->webhook_url) {
                return;
            }

            $webhookData = [
                'order_number' => $order->order_number,
                'id' => $order->id,
                'customer_id' => $order->customer_id,
                'subtotal' => $order->subtotal,
                'total_amount' => $order->total_amount,
                'discount' => $order->discount ?? 0,
                'currency' => 'ALL',
                'exchange_rate_all' => $order->exchange_rate_all ?? 1,
                'status' => $order->status,
                'payment_method_id' => $order->payment_method_id,
                'payment_status' => $order->payment_status,
                'stripe_payment_id' => $order->payment['transactionId'] ?? null,
                'payment_metadata' => $order->payment ?? [],
                'source_type' => $checkoutType,
                'source_url' => request()->header('Referer', 'https://metroshop.al'),
                'source_platform' => 'venueboost-whitelabel',
                'customer_email' => $order->customer ? $order->customer->email : ($order->billing_email ?? null),

                // Shipping details
                'shipping_name' => $order->shipping_name,
                'shipping_surname' => $order->shipping_surname,
                'shipping_address' => $order->shipping_address,
                'shipping_city' => $order->shipping_city,
                'shipping_state' => $order->shipping_state,
                'shipping_postal_code' => $order->shipping_postal_code,
                'shipping_phone_no' => $order->shipping_phone_no,
                'shipping_email' => $order->shipping_email,

                // Billing details
                'billing_name' => $order->billing_name,
                'billing_surname' => $order->billing_surname,
                'billing_address' => $order->billing_address,
                'billing_city' => $order->billing_city,
                'billing_state' => $order->billing_state,
                'billing_postal_code' => $order->billing_postal_code,
                'billing_phone_no' => $order->billing_phone_no,
                'billing_email' => $order->billing_email,

                // Order products with full details
                'order_products' => $order->orderProducts->map(function ($orderProduct) {
                    $product = $orderProduct->product;
                    return [
                        'product_id' => $orderProduct->product_id,
                        'product_name' => $product->title ?? 'Unknown Product',
                        'product_quantity' => $orderProduct->product_quantity,
                        'product_total_price' => $orderProduct->product_total_price,
                        'variant' => $orderProduct->metadata['variant'] ?? null,
                        'options' => $orderProduct->metadata['options'] ?? [],
                        'external_ids' => $orderProduct->metadata['external_ids'] ?? null
                    ];
                })->toArray()
            ];

            $webhookUrl = rtrim(env('OMNISTACK_GATEWAY_BASEURL'), '/') . '/webhooks/orders/' . $venue->short_code;

            $response = Http::withHeaders([
                'webhook-api-key' => env('OMNISTACK_GATEWAY_MSHOP_API_KEY'),
                'x-api-key' => env('OMNISTACK_GATEWAY_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post($webhookUrl, $webhookData);

            if (!$response->successful()) {
                Log::error('Webhook failed for order ' . $order->id, [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error sending webhook for order ' . $order->id, [
                'error' => $e->getMessage()
            ]);
        }
    }
}
