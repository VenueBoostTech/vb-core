<?php
namespace App\Http\Controllers\v1;
use App\Enums\DeliveryRequestStatus;
use App\Enums\InventoryActivityCategory;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\v2\InvoiceController;
use App\Mail\CustomerOrderConfirmationEmail;
use App\Mail\LowInventoryAlert;
use App\Mail\NewOrderEmail;
use App\Models\ActivityRetail;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryProvider;
use App\Models\DeliveryProviderRestaurant;
use App\Models\Discount;
use App\Models\Gallery;
use App\Models\Guest;
use App\Models\Ingredient;
use App\Models\InventoryAlert;
use App\Models\InventoryAlertHistory;
use App\Models\InventoryRetail;
use App\Models\LoyaltyProgram;
use App\Models\Member;
use App\Models\OrderIngredient;
use App\Models\OrderSplitPayment;
use App\Models\OrderStatusChange;
use App\Models\User;
use App\Models\Wallet;
use App\Services\VenueService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Models\InventoryActivity;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderProduct;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\StoreSetting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use stdClass;
use Twilio\Rest\Client;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\TwilioException;
use function event;
use function response;

/**
 * @OA\Info(
 *   title="Orders API",
 *   version="1.0",
 *   description="This API allows use Orders Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Orders",
 *   description="Operations related to Orders"
 * )
 */


class OrdersController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    /**
     * @OA\Post(
     *     path="/orders",
     *     tags={"Orders"},
     *     summary="Create a new order",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="total_amount",
     *                 type="number",
     *                 description="The total amount of the order"
     *             ),
     *             @OA\Property(
     *                 property="customer_id",
     *                 type="integer",
     *                 description="The ID of the customer (optional)"
     *             ),
     *             @OA\Property(
     *                 property="reservation_id",
     *                 type="integer",
     *                 description="The ID of the reservation (optional)"
     *             ),
     *             @OA\Property(
     *                 property="order_products",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(
     *                         property="product_id",
     *                         type="integer",
     *                         description="The ID of the product"
     *                     ),
     *                     @OA\Property(
     *                         property="product_instructions",
     *                         type="string",
     *                         description="Instructions for the product (optional)"
     *                     ),
     *                     @OA\Property(
     *                         property="product_quantity",
     *                         type="integer",
     *                         description="The quantity of the product"
     *                     ),
     *                     @OA\Property(
     *                         property="product_total_price",
     *                         type="number",
     *                         description="The total price of the product"
     *                     ),
     *                     @OA\Property(
     *                         property="product_discount_price",
     *                         type="number",
     *                         description="The discount price of the product (optional)"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order added successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error or insufficient quantity in inventory"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }


        $validator = Validator::make($request->all(), [
            'total_amount' => [
                'required',
                function ($attribute, $value, $fail) use ($request) {
                    $productTotalPrices = $request->input('order_products.*.product_total_price');
                    $expectedTotalAmount = array_sum($productTotalPrices);

                    if ($value != $expectedTotalAmount) {
                        $fail('The total amount of the order is not valid.');
                    }
                },
            ],
            'customer_id' => 'nullable|exists:customers,id',
            'reservation_id' => [
                'nullable',
                'exists:reservations,id',
                function ($attribute, $value, $fail) use ($request, $venue) {
                    if ($value) {
                        $reservation = Reservation::find($value);
                        if (!$reservation || $reservation->restaurant_id != $venue->id) {
                            $fail('The selected reservation is not valid for this restaurant.');
                        }
                    }
                },
            ],
            'order_products' => 'required|array',
            'order_products.*.product_id' => 'required|exists:products,id',
            'order_products.*.product_instructions' => 'nullable|string',
            'order_products.*.product_quantity' => 'required|integer|min:1',
            'products.*.product_total_price' => [
                'required',
                function ($attribute, $value, $fail) use ($request) {
                    $productId = $request->input('products.*.product_id');
                    $productQuantity = $request->input('products.*.product_quantity');
                    $product = Product::find($productId);
                    $expectedTotalPrice = $product->price * $productQuantity;

                    if ($value != $expectedTotalPrice) {
                        $fail('The total price for product ID ' . $productId . ' is not valid.');
                    }
                },
            ],
            'order_products.*.product_discount_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $customerId = null;

        if ($request->input('reservation_id')) {
            $reservationId = $request->input('reservation_id');
            $reservation = Reservation::where('restaurant_id', $venue->id)->find($reservationId);
            if (!$reservation) {
                return response()->json(['error' => 'Reservation not found'], 400);
            }

            $guest = Reservation::with('guests')->where('id', $reservationId)->first();

            $mainGuest = $guest->guests->where('is_main', true)->first();

            if ($mainGuest) {
                $customerId = $mainGuest->id;
            } else {
                return response()->json(['error' => 'Invalid reservation ID'], 400);
            }

        }

        // Create the order
        $order = Order::create([
            'total_amount' => $request->total_amount,
            'reservation_id' => $request->reservation_id ?? null,
            'customer_id' => $customerId,
            'restaurant_id' => $venue->id,
            'status' => OrderStatus::RESERVATION_CONFIRMED
        ]);

        // Create the order products
        foreach ($request->order_products as $productData) {
            $product = Product::find($productData['product_id']);

            if ($product && $product->inventories->count() > 0) {
                $inventoryProduct = $product->inventories->first()->products()
                    ->where('product_id', $product->id)
                    ->first();

                if ($inventoryProduct && $inventoryProduct->pivot->quantity >= $productData['product_quantity']) {
                    $inventoryProduct->pivot->quantity -= $productData['product_quantity'];
                    $inventoryProduct->pivot->save();

                    // Create an InventoryActivity record for the deduction
                    $activity = new InventoryActivity();
                    $activity->product_id = $inventoryProduct->id;
                    $activity->quantity = $productData['product_quantity'];
                    $activity->activity_category = InventoryActivityCategory::ORDER_SALE;
                    $activity->activity_type = 'deduct';
                    $activity->inventory_id = $inventoryProduct->pivot->inventory_id;
                    $activity->order_id = $order->id;
                    $activity->save();

                } else {
                    return response()->json(['error' => 'Insufficient quantity in inventory for product: ' . $product->title], 400);
                }
            }

            OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $productData['product_id'],
                'product_instructions' => $productData['product_instructions'] ?? null,
                'product_quantity' => $productData['product_quantity'],
                'product_total_price' => $productData['product_total_price'] * $productData['product_quantity'],
                'product_discount_price' => $productData['product_discount_price'] ?? null
            ]);
        }

        return response()->json(['message' => 'Order added successfully']);
    }


    /**
     * Accept an order
     *
     * @OA\Post(
     *     path="/orders/accept",
     *     summary="Accept an order",
     *     tags={"Orders"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="order_id",
     *                     description="The ID of the order",
     *                     type="integer",
     *                     example=12345
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="order",
     *                 type="object",
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Order accepted successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="Order not found"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="Restaurant not found for the user making the API call"
     *             )
     *         )
     *     )
     * )
     */
    function acceptOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $order = Order::where('restaurant_id', $venue->id)->find($request->order_id);
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 400);
        }

        $order = Order::find($request->order_id);
        $order->status = OrderStatus::ORDER_CONFIRMED;
        $order->save();
        return response()->json([
            'order' => $order,
            'message' => 'Order accepted successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/orders/delivery-request",
     *     summary="Create an order delivery request",
     *     tags={"Orders"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="order_id",
     *                     description="The ID of the order",
     *                     type="integer",
     *                     example=12345
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="delivery_request",
     *                 type="object",
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Delivery request created successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="Order not found"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="Restaurant not found for the user making the API call"
     *             )
     *         )
     *     )
     * )
     */
    function createDeliveryRequest(Request $request): \Illuminate\Http\JsonResponse
    {

        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $order = Order::where('restaurant_id', $venue->id)->find($request->order_id);
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 400);
        }

        $existingDeliveryRequest = $order->orderDelivery;

        if ($existingDeliveryRequest && $existingDeliveryRequest->delivery_status !== DeliveryRequestStatus::CANCELLED) {
            return response()->json(['error' => 'Delivery request already exists for this order'], 400);
        }

        $order->status = OrderStatus::ORDER_READY_FOR_PICKUP;
        $order->save();

        $restaurantDeliveryProvider = DeliveryProviderRestaurant::where('restaurant_id', $venue->id)->first();
        $deliverProvider = DeliveryProvider::find($restaurantDeliveryProvider->delivery_provider_id);

        if (!$deliverProvider) {
            return response()->json(['error' => 'Delivery provider not found'], 400);
        }

        if (!$restaurantDeliveryProvider) {
            return response()->json(['error' => 'Restaurant delivery provider not found'], 400);
        }

        // order delivery request
        $deliveryRequest = OrderDelivery::create([
            'order_id' => $order->id,
            'restaurant_id' => $venue->id,
            'delivery_provider_id' => $restaurantDeliveryProvider->delivery_provider_id,
            'delivery_status' => $deliverProvider->name === 'Doordash' ? DeliveryRequestStatus::DOORDASH_READY_TO_CALL_RIDER : 'pending',
            'external_delivery_id' => strtoupper($deliverProvider->code . '-' . substr(md5(uniqid()), 0, 8)),
        ]);

        return response()->json([
            'delivery_request' => $deliveryRequest,
            'message' => 'Delivery request created successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/orders/by-vendor",
     *     summary="Get orders by vendor",
     *     tags={"Orders"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="orders",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Orders retrieved successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="Restaurant not found for the user making the API call"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="Restaurant not found for the user making the API call"
     *             )
     *         )
     *     )
     * )
     */
    public function getOrdersByVendor(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $orders = Order::with('orderProducts', 'orderDelivery', 'orderDelivery.deliveryProvider')
            ->where('restaurant_id', $venue->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'orders' => $orders,
            'message' => 'Orders retrieved successfully'
        ]);
    }

    public function getDeliveryOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $orders = Order::where('restaurant_id', $venue->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $orders->load('customer');

        $transformedOrders = $orders->map(function ($order) {
            return [
                'order_number' => $order->order_number,
                'id' => $order->id,
                'customer_full_name' => $order->customer->name ?? '',
                'total_amount' => $order->total_amount,
                'status' => $order->status,
                'currency' => $order->currency,
                'order_for' => $order->is_for_self ? 'Self' : $order->other_person_name,
                'stripe_payment_id' => $order->stripe_payment_id,
                'created_at' => $order->created_at->format('F d, Y h:i A'),
            ];
        });

        return response()->json(['orders' => $transformedOrders]);
    }

    public function getPickupOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = [];
        return response()->json(['data' => $data]);
    }

    public function getOrderAndPayOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = [];
        return response()->json(['data' => $data]);
    }

    public function show($id): \Illuminate\Http\JsonResponse
    {
        $order = new \stdClass();

        return response()->json([
            'order' => $order,
            'message' => 'Order retrieved successfully'
        ]);
    }


    public function retailOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }


        $validator = Validator::make($request->all(), [
            'subtotal' => [
                'required',
                function ($attribute, $value, $fail) use ($request) {
                    $orderProducts = $request->input('order_products');
                    $expectedTotalAmount = 0;

                    foreach ($orderProducts as $product) {
                        $expectedTotalAmount += ($product['product_total_price'] * $product['product_quantity']) - $product['product_discount_price'];
                    }

                    if ($value != $expectedTotalAmount) {
                        $fail('The subtotal of the order is not valid.');
                    }
                },
            ],
            'order_products' => 'required|array',
            'order_products.*.product_id' => 'required|exists:products,id',
            'order_products.*.product_instructions' => 'nullable|string',
            'order_products.*.product_quantity' => 'required|integer|min:1',
            'products.*.product_total_price' => [
                'required',
                function ($attribute, $value, $fail) use ($request) {
                    $productId = $request->input('products.*.product_id');
                    $productQuantity = $request->input('products.*.product_quantity');
                    $product = Product::find($productId);
                    $expectedTotalPrice = $product->price * $productQuantity;

                    if ($value != $expectedTotalPrice) {
                        $fail('The total price for product ID ' . $productId . ' is not valid.');
                    }
                },
            ],
            'order_products.*.product_discount_price' => 'nullable|numeric|min:0',
            'customer.first_name' => 'required|string',
            'customer.last_name' => 'required|string',
            'customer.phone' => 'required|string',
            'customer.email' => 'required|email',

            'shipping_address.line1' => 'required|string',
            'shipping_address.line2' => 'nullable|string',
            'shipping_address.state' => 'required|string',
            'shipping_address.city' => 'required|string',
            'shipping_address.postal_code' => 'required|string',

            'coupon' => 'nullable|string',
            'discount_value' => 'nullable|numeric|min:0',
            'total_amount' => 'nullable|numeric|min:0',

            'payment_method' => 'required|in:Cash,Card',
            'stripe_payment_id' => Rule::requiredIf(function () use ($request) {
                return $request->input('payment_method') === 'Card';
            }),
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $paymentMethod = PaymentMethod::where('name', $request->input('payment_method'))->first();

        if (!$paymentMethod) {
            return response()->json(['error' => 'Payment method not found'], 404);
        }

        $customerId = null;
        if ($request->customer['email']) {
            $customer = Customer::where('email', $request->customer['email'])->first();

            if (!$customer) {
                $customer = Customer::create([
                    'name' => $request->customer['first_name'] . ' ' . $request->customer['last_name'],
                    'phone' => $request->customer['phone'],
                    'email' => $request->customer['email'],
                    'address' => $request->customer['address'] ?? 'shipping_address_added',
                    'venue_id' => $venue->id,
                ]);
            }

            $customerId = $customer->id;
        }

        $addressData = $request->input('shipping_address');

        try {
            $address = new Address();
            $address->address_line1 = $addressData['line1'];
            $address->address_line2 = $addressData['line2'] ?? null;
            $address->city = $addressData['city'];
            $address->state = $addressData['state'];
            $address->postcode = $addressData['postal_code'];
            $address->is_for_retail = 1;
            $address->save();

            $customerAddress = new CustomerAddress();
            $customerAddress->customer_id = $customerId;  // Assuming you have the customer ID at this point
            $customerAddress->address_id = $address->id;
            $customerAddress->save();
        } catch (\Exception $e) {
            // do nothing
            \Sentry\captureException($e);
        }

        $coupon = null;

        if ($request->input('coupon')) {
            $coupon = Coupon::where('code', $request->coupon)->first();
            if (!$coupon) {
                return response()->json(['error' => 'Invalid coupon code'], 400);
            }

            // Check if the coupon is within the valid time frame
            $currentTime = Carbon::now();
            if ($currentTime->lt($coupon->start_time) || $currentTime->gt($coupon->expiry_time)) {
                return response()->json(['error' => 'Coupon is not valid at this time'], 400);
            }

            // Calculate discount value based on discount type
            $calculatedDiscount = 0;
            if ($coupon->discount_type === 'percentage_cart_discount') {
                $calculatedDiscount = ($request->subtotal * $coupon->discount_amount) / 100;
            } elseif ($coupon->discount_type === 'fixed_cart_discount') {
                $calculatedDiscount = $coupon->discount_amount;
            }

            if ($calculatedDiscount != $request->discount_value) {
                return response()->json(['error' => 'Discount value does not match the coupon'], 400);
            }

            // Check if the total amount is within min and max spent of the coupon
            if (($coupon->minimum_spent && $request->subtotal < $coupon->minimum_spent) ||
                ($coupon->maximum_spent && $request->subtotal > $coupon->maximum_spent)) {
                return response()->json(['error' => 'Total amount is not eligible for this coupon'], 400);
            }

            // Check usage limits for the coupon
            $couponUsageCount = DB::table('order_coupons')->where('coupon_id', $coupon->id)->count();
            if ($couponUsageCount >= $coupon->usage_limit_per_coupon) {
                return response()->json(['error' => 'Coupon usage limit reached'], 400);
            }

            // Check usage limits for the customer
            $customerCouponUsageCount = DB::table('order_coupons')
                ->where('coupon_id', $coupon->id)
                ->where('customer_id', $customerId)
                ->count();
            if ($customerCouponUsageCount >= $coupon->usage_limit_per_customer) {
                return response()->json(['error' => 'You have reached the usage limit for this coupon'], 400);
            }
        }

        // Get currency setting for the venue
        $storeSetting = StoreSetting::where('venue_id', $venue->id)->first();
        $currency = $storeSetting ? $storeSetting->currency : null;

        if ($request->total_amount != $request->subtotal - $request->discount_value) {
            return response()->json(['error' => 'Total amount does not match the subtotal'], 400);
        }

        // get payment id from stripe based on client secret

        $paymentIntent = null;
        if ($request->stripe_payment_id) {
            $paymentIntent = explode('_secret_', $request->stripe_payment_id)[0];
        }
        // TODO: after v1 testing of retail add shipping fee logic and column to order table
        // Create the order
        $order = Order::create([
            'total_amount' => $request->total_amount,
            'subtotal' => $request->subtotal,
            'customer_id' => $customerId,
            'restaurant_id' => $venue->id,
            'status' => OrderStatus::NEW_ORDER,
            'payment_method_id' => $paymentMethod->id,
            'stripe_payment_id' => $paymentIntent ?? null,
            'notes' => $request->input('notes') ?? null,
            'currency' => $currency,
            'address_id' => $address->id ?? null,
            'payment_status' => $request->stripe_payment_id ? 'paid' : 'unpaid',
        ]);

        // Create the order products
        foreach ($request->order_products as $productData) {
            $product = Product::find($productData['product_id']);

            if ($product && $product->inventories->count() > 0) {
                $inventoryProduct = $product->inventories->first()->products()
                    ->where('product_id', $product->id)
                    ->first();

                if ($inventoryProduct && $inventoryProduct->pivot->quantity >= $productData['product_quantity']) {
                    $inventoryProduct->pivot->quantity -= $productData['product_quantity'];
                    $inventoryProduct->pivot->save();

                    // Create an InventoryActivity record for the deduction
                    $activity = new InventoryActivity();
                    $activity->product_id = $inventoryProduct->id;
                    $activity->quantity = $productData['product_quantity'];
                    $activity->activity_category = InventoryActivityCategory::ORDER_SALE;
                    $activity->activity_type = 'deduct';
                    $activity->inventory_id = $inventoryProduct->pivot->inventory_id;
                    $activity->order_id = $order->id;
                    $activity->save();

                } else {
                    return response()->json(['error' => 'Insufficient quantity in inventory for product: ' . $product->title], 400);
                }
            }

            OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $productData['product_id'],
                'product_instructions' => $productData['product_instructions'] ?? null,
                'product_quantity' => $productData['product_quantity'],
                'product_total_price' => $productData['product_total_price'] * $productData['product_quantity'],
                'product_discount_price' => $productData['product_discount_price'] ?? null
            ]);


            // update retail inventory of products
            $inventoryRetail = InventoryRetail::where('product_id', $productData['product_id'])
                ->where('venue_id', $venue->id)
                ->first();


            if ($inventoryRetail?->exists && $inventoryRetail?->manage_stock) {
                $inventoryRetail->stock_quantity -= $productData['product_quantity'];
                $inventoryRetail->save();

                ActivityRetail::create([
                    'inventory_retail_id' => $inventoryRetail->id,
                    'venue_id' =>  $venue->id,
                    'activity_type' => 'deduct_from_order',
                    'description' => "Stock quantity deducted with {$productData['product_quantity']} from order {$order->order_number}",
                    'data' =>json_encode( [
                        'previous_quantity' => $inventoryRetail->stock_quantity + $productData['product_quantity'],
                        'new_quantity' => $inventoryRetail->stock_quantity,
                    ])
                ]);


                $inventoryAlert = InventoryAlert::where('inventory_retail_id', $inventoryRetail->id)->first();
                if($inventoryAlert) {
                    // Check for existing unresolved alert
                    $existingAlert = InventoryAlertHistory::where('inventory_alert_id', $inventoryAlert?->id)
                        ->where('is_resolved', false)
                        ->latest()
                        ->first();

                    // If there is no existing unresolved alert, check if a new alert should be triggered
                    if (!$existingAlert) {
                        if ($inventoryRetail->stock_quantity < $inventoryAlert->alert_level) {
                            // Create alert history entry
                            $alertHistoryRecord = InventoryAlertHistory::create([
                                'inventory_alert_id' => $inventoryAlert->id,
                                'stock_quantity_at_alert' => $inventoryRetail->stock_quantity,
                                'alert_triggered_at' => now(),
                            ]);

                            if ($storeSetting?->new_order_email_recipient) {

                                $alertData = [
                                    'product_name' => $inventoryRetail->product->title,
                                    'alert_level' => $inventoryAlert->alert_level,
                                    'stock_quantity_alert' => $alertHistoryRecord->stock_quantity_at_alert,
                                ];
                                // Send email notification (implement this as per your requirements)
                                Mail::to($storeSetting?->new_order_email_recipient)->send(new LowInventoryAlert($alertData, $venue->name));
                            }
                           ;
                        }
                    }
                }
            }
        }

        // Link the coupon to the order
        if ($coupon) {
            DB::table('order_coupons')->insert([
                'order_id' => $order->id,
                'coupon_id' => $coupon->id,
                'customer_id' => $customerId,
                'discount_value' => $request->discount_value
            ]);

            // update order discount total
            $order->update(['discount_total' => $request->discount_value]);
        }

        $venuePrefix = strtoupper(substr($venue->name, 0, 2));
        $randomFourDigits = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        $orderNumber = $venuePrefix .'-'. $order->id . $randomFourDigits;
        $order->update(['order_number' => $orderNumber]);

        // Send order confirmation email and create guest account

        if ($request->customer['email']) {
            $checkGuest = Guest::where('email', $request->customer['email'])->where('is_for_retail', true)->first();
            if (!$checkGuest) {
                $guest = Guest::create([
                    'name' => $request->customer['first_name'] . ' ' . $request->customer['last_name'],
                    'phone' => $request->customer['phone'],
                    'email' => $request->customer['email'],
                    'restaurant_id' => $venue->id,
                    'is_main' => true,
                    'is_for_retail' => true,
                ]);

                // create user
                User::create([
                    'name' => $request->customer['first_name'] . ' ' . $request->customer['last_name'],
                    'email' => $request->customer['email'],
                    // make hash random password
                    'password' => Hash::make(Str::random(8)),
                    'country_code' => 'US',
                    'enduser' => true
                ]);
            } else {
                $guest = $checkGuest;
            }


            $loyaltyProgram = LoyaltyProgram::where('venue_id', $venue->id)->first();


            if (!$loyaltyProgram) {
                $canEnroll = false;
            } else {
                $enrolledGuests = $loyaltyProgram->guests()->select('name', 'email', 'phone')
                    ->withPivot('created_at')->get();

                $isMainGuestEnrolled = $enrolledGuests->where('email', $guest->email)->first();

                if ($isMainGuestEnrolled) {
                    $canEnroll = false;

                    $percentage = $loyaltyProgram->reward_value;
                    $pointsEarned = $guest->calculatePointsEarned($request->total_amount, $percentage);

                    $enrolledGuests = $loyaltyProgram->guests()->select('name', 'email', 'phone')
                        ->withPivot('created_at')->get();

                    $isMainGuestEnrolled = $enrolledGuests->where('email', $guest->email)->first();

                    if($isMainGuestEnrolled) {
                        // Update guest's wallet balance with the earned points
                        if (!$guest->wallet) {
                            Wallet::create([
                                'guest_id' => $guest->id,
                                'balance' => $pointsEarned,
                                'venue_id' => $venue->id,
                            ]);
                        } else {
                            $guest->wallet->increment('balance', $pointsEarned);
                        }
                    }


                    // Add a record to the earnPointsHistory table with guest_id, reservation_id, and points_earned
                    $guest->earnPointsHistory()->create([
                        'order_id' => $order->id,
                        'points_earned' => $pointsEarned,
                        'venue_id' => $venue->id,
                    ]);
                } else {
                    $canEnroll = true;
                }
            }
        }


        // Order Items
        $orderItems = [];
        foreach ($order->orderProducts as $orderProduct) {
            if ($orderProduct->product) {
                $orderItems[] = [
                    'image' => $orderProduct->product->image_path ? Storage::disk('s3')->temporaryUrl($orderProduct->product->image_path, '+5 minutes') : null,
                    'price' => $orderProduct->product->price,
                    'title' => $orderProduct->product->title,
                    'quantity' => $orderProduct->product_quantity,
                    'total_price' => $orderProduct->product_total_price,
                    'article_no' => $orderProduct->product->article_no ?? null,
                ];
            }
        }


        $orderAddress = new StdClass();
        $orderAddress->address_line1 = $order->address->address_line1 ?? null;
        $orderAddress->address_line2 = $order->address->address_line2 ?? null;
        $orderAddress->state = $order->address->state ?? null;
        $orderAddress->city = $order->address->city ?? null;
        $orderAddress->postcode = $order->address->postcode ?? null;

        // Get currency setting for the venue
        $storeSetting = StoreSetting::where('venue_id', $venue->id)->first();
        $currency = $storeSetting ? $storeSetting->currency : null;

        $logo = $venue->logo && $venue->logo !== 'logo' && $venue->logo !== 'https://via.placeholder.com/300x300' ? Storage::disk('s3')->temporaryUrl($venue->logo, '+5 minutes') : null;

        $rest_addr = DB::table('restaurant_addresses')->where('restaurants_id', $venue->id)->first();
        $venueAddress = null;
        if ($rest_addr) {
            $addressRetrieve = Address::where('id', $rest_addr->address_id)->first();

            // make address full
            $venueAddress = $addressRetrieve->address_line1 . ', ' . $addressRetrieve->address_line2 . ', ' . $addressRetrieve->city . ', ' . $addressRetrieve->state . ', ' . $addressRetrieve->postcode;
        }

        // Record the new status and the date
        OrderStatusChange::create([
            'order_id' => $order->id,
            'new_status' => OrderStatus::NEW_ORDER,
            'changed_at' => now(),
        ]);

        $orderFinal = [
            'shipping_address' => $orderAddress,
            'customer' => [
                'name' => $order->customer->name,
                'email' => $order->customer->email,
                'phone' => $order->customer->phone
            ],
            'order_items' => $orderItems,
            'order_summary' => [
                'discount_value' => $order->discount_total,
                'total_amount' => $order->total_amount,
                'subtotal' => $order->subtotal,
                'payment_method' => $order->paymentMethod?->name,
                'created_at' => $order->created_at->format('F d, Y h:i A'),
            ],
            'currency' => $currency,
            'order_number' => $order->order_number,
            'delivery_fee' => 0,
            'logo' => $logo,
            'venue_name' => $venue->name,
            'venue_address' => $venueAddress,
        ];


        Mail::to($request->customer['email'])->send(new CustomerOrderConfirmationEmail($venue->name, $orderFinal));

        // send just a you received an order email to the store settings email
        if ( $storeSetting?->new_order_email_recipient) {
            Mail::to($storeSetting->new_order_email_recipient)->send(new NewOrderEmail($venue->name));
        }

        return response()->json(['message' => 'Order added successfully', 'can_enroll' => $canEnroll ?? false, 'order_number' => $orderNumber], 200);
    }

    public function restaurantOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }


        $validator = Validator::make($request->all(), [
            'subtotal' => [
                'required',
                function ($attribute, $value, $fail) use ($request) {
                    $orderProducts = $request->input('order_products');
                    $expectedTotalAmount = 0;

                    foreach ($orderProducts as $product) {
                        $expectedTotalAmount += ($product['product_total_price'] * $product['product_quantity']) - $product['product_discount_price'];
                    }

                    if ($value != $expectedTotalAmount) {
                        $fail('The subtotal of the order is not valid.');
                    }
                },
            ],
            'order_products' => 'required|array',
            'order_products.*.product_id' => 'required|exists:products,id',
            'order_products.*.product_instructions' => 'nullable|string',
            'order_products.*.product_quantity' => 'required|integer|min:1',
            'products.*.product_total_price' => [
                'required',
                function ($attribute, $value, $fail) use ($request) {
                    $productId = $request->input('products.*.product_id');
                    $productQuantity = $request->input('products.*.product_quantity');
                    $product = Product::find($productId);
                    $expectedTotalPrice = $product->price * $productQuantity;

                    if ($value != $expectedTotalPrice) {
                        $fail('The total price for product ID ' . $productId . ' is not valid.');
                    }
                },
            ],
            'order_products.*.product_discount_price' => 'nullable|numeric|min:0',
            'customer.first_name' => 'required|string',
            'customer.last_name' => 'required|string',
            'customer.phone' => 'required|string',
            'customer.email' => 'required|email',

            'shipping_address.line1' => 'required|string',
            'shipping_address.line2' => 'nullable|string',
            'shipping_address.state' => 'required|string',
            'shipping_address.city' => 'required|string',
            'shipping_address.postal_code' => 'required|string',

            'coupon' => 'nullable|string',
            'discount_value' => 'nullable|numeric|min:0',
            'total_amount' => 'nullable|numeric|min:0',

            'is_for_self' => 'required|boolean',
            // other person name is required if is_for_self is false
            'other_person_name' => Rule::requiredIf(function () use ($request) {
                return !$request->input('is_for_self');
            }),
            'payment_method' => 'required|in:Cash,Card',
            'stripe_payment_id' => Rule::requiredIf(function () use ($request) {
                return $request->input('payment_method') === 'Card';
            }),
            'notes' => 'nullable|string',
            'discount_applied' => 'nullable|boolean',
            'discount_id' => 'nullable|exists:discounts,id',
            'delivery_fee' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $paymentMethod = PaymentMethod::where('name', $request->input('payment_method'))->first();

        if (!$paymentMethod) {
            return response()->json(['error' => 'Payment method not found'], 404);
        }

        $customerId = null;
        if ($request->customer['email']) {
            $customer = Customer::where('email', $request->customer['email'])->first();

            if (!$customer) {
                $customer = Customer::create([
                    'name' => $request->customer['first_name'] . ' ' . $request->customer['last_name'],
                    'phone' => $request->customer['phone'],
                    'email' => $request->customer['email'],
                    'address' => $request->customer['address'] ?? 'shipping_address_added',
                    'venue_id' => $venue->id,
                ]);
            }

            $customerId = $customer->id;
        }

        $addressData = $request->input('shipping_address');

        try {
            $address = new Address();
            $address->address_line1 = $addressData['line1'];
            $address->address_line2 = $addressData['line2'] ?? null;
            $address->city = $addressData['city'];
            $address->state = $addressData['state'];
            $address->postcode = $addressData['postal_code'];
            $address->is_for_retail = 0;
            $address->save();

            $customerAddress = new CustomerAddress();
            $customerAddress->customer_id = $customerId;  // Assuming you have the customer ID at this point
            $customerAddress->address_id = $address->id;
            $customerAddress->save();
        } catch (\Exception $e) {
            // do nothing
            \Sentry\captureException($e);
        }

        $coupon = null;

        if ($request->input('coupon')) {
            $coupon = Coupon::where('code', $request->coupon)->where('venue_id', $venue->id)->first();
            if (!$coupon) {
                return response()->json(['error' => 'Invalid coupon code'], 400);
            }

            // Check if the coupon is within the valid time frame
            $currentTime = Carbon::now();
            if ($currentTime->lt($coupon->start_time) || $currentTime->gt($coupon->expiry_time)) {
                return response()->json(['error' => 'Coupon is not valid at this time'], 400);
            }

            // Calculate discount value based on discount type
            $calculatedDiscount = 0;
            if ($coupon->discount_type === 'percentage_cart_discount') {
                $calculatedDiscount = ($request->subtotal * $coupon->discount_amount) / 100;
            } elseif ($coupon->discount_type === 'fixed_cart_discount') {
                $calculatedDiscount = $coupon->discount_amount;
            }

            if ($calculatedDiscount != $request->discount_value) {
                return response()->json(['error' => 'Discount value does not match the coupon'], 400);
            }

        }

        // check if discount is applied and then check if id is provided
        if ($request->discount_applied && !$request->discount_id) {
            return response()->json(['error' => 'Discount id is required'], 400);
        }

        // if discount id is provided then check if it exists
        if ($request->discount_id) {
            $discount = Discount::where('id', $request->discount_id)->where('venue_id', $venue->id)->first();
            if (!$discount) {
                return response()->json(['error' => 'Discount not found'], 404);
            }

            // if it is found check if it is valid
            $currentTime = Carbon::now();
            if ($currentTime->lt($discount->start_time) || $currentTime->gt($discount->end_time)) {
                return response()->json(['error' => 'Discount is not valid at this time'], 400);
            }

            // if it is valid check if the value % or fixed matches the discount value of order
            $calculatedDiscount = 0;
            if ($discount->type === 'percentage') {
                $calculatedDiscount = ($request->subtotal * $discount->value) / 100;
            } elseif ($discount->type === 'fixed') {
                $calculatedDiscount = $discount->value;
            }

            if ($calculatedDiscount != $request->discount_value) {
                return response()->json(['error' => 'Discount value does not match the discount'], 400);
            }
        }

        // Get currency setting for the venue
        $currency = '$';

        if ($request->total_amount != $request->subtotal - $request->discount_value) {
            return response()->json(['error' => 'Total amount does not match the subtotal'], 400);
        }

        $paymentIntent = null;
        if ($request->stripe_payment_id) {
            $paymentIntent = explode('_secret_', $request->stripe_payment_id)[0];
        }

        // Create the order
        $order = Order::create([
            'total_amount' => $request->total_amount,
            'subtotal' => $request->subtotal,
            'customer_id' => $customerId,
            'restaurant_id' => $venue->id,
            'status' => OrderStatus::NEW_ORDER,
            'payment_method_id' => $paymentMethod->id,
            'stripe_payment_id' => $paymentIntent ?? null,
            'notes' => $request->input('notes') ?? null,
            'currency' => $currency,
            'address_id' => $address->id ?? null,
            'payment_status' => $request->stripe_payment_id ? 'paid' : 'unpaid',
            'delivery_fee' => $request->delivery_fee ?? 0,
            'hospital_room_id' => $request->hospital_room_id ?? null,
            'is_for_self' => $request->is_for_self ?? 1,
            'other_person_name' => $request->other_person_name ?? null,
        ]);

        // Create the order products
        foreach ($request->order_products as $productData) {
            $product = Product::find($productData['product_id']);

            if ($product && $product->inventories->count() > 0) {
                $inventoryProduct = $product->inventories->first()->products()
                    ->where('product_id', $product->id)
                    ->first();

                if ($inventoryProduct && $inventoryProduct->pivot->quantity >= $productData['product_quantity']) {
                    $inventoryProduct->pivot->quantity -= $productData['product_quantity'];
                    $inventoryProduct->pivot->save();

                    // Create an InventoryActivity record for the deduction
                    $activity = new InventoryActivity();
                    $activity->product_id = $inventoryProduct->id;
                    $activity->quantity = $productData['product_quantity'];
                    $activity->activity_category = InventoryActivityCategory::ORDER_SALE;
                    $activity->activity_type = 'deduct';
                    $activity->inventory_id = $inventoryProduct->pivot->inventory_id;
                    $activity->order_id = $order->id;
                    $activity->save();

                } else {
                    return response()->json(['error' => 'Insufficient quantity in inventory for product: ' . $product->title], 400);
                }
            }

            OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $productData['product_id'],
                'product_instructions' => $productData['product_instructions'] ?? null,
                'product_quantity' => $productData['product_quantity'],
                'product_total_price' => $productData['product_total_price'] * $productData['product_quantity'],
                'product_discount_price' => $productData['product_discount_price'] ?? null
            ]);

        }

        // Link the coupon to the order
        if ($coupon) {
            DB::table('order_coupons')->insert([
                'order_id' => $order->id,
                'coupon_id' => $coupon->id,
                'customer_id' => $customerId,
                'discount_value' => $request->discount_value
            ]);

            // update order discount total
            $order->update(['discount_total' => $request->discount_value]);
        }

        // Link the discount to the order
        if ($request->discount_id) {
            $discount = Discount::where('id', $request->discount_id)->where('venue_id', $venue->id)->first();

            if (!$discount) {
                return response()->json(['error' => 'Discount not found'], 404);
            }

            // if it is found check if it is valid
            $currentTime = Carbon::now();
            if ($currentTime->lt($discount->start_time) || $currentTime->gt($discount->end_time)) {
                return response()->json(['error' => 'Discount is not valid at this time'], 400);
            }

            DB::table('order_discounts')->insert([
                'order_id' => $order->id,
                'discount_id' => $discount->id,
                'customer_id' => $customerId,
                'discount_value' => $request->discount_value
            ]);

            // update order discount total
            $order->update(['discount_total' => $request->discount_value]);
        }

        $venuePrefix = strtoupper(substr($venue->name, 0, 2));
        $randomFourDigits = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        $orderNumber = $venuePrefix .'-'. $order->id . $randomFourDigits;
        $order->update(['order_number' => $orderNumber]);

        // Send order confirmation email and create guest account

        if ($request->customer['email']) {
            $checkGuest = Guest::where('email', $request->customer['email'])->where('is_from_restaurant_checkout', true)->first();
            if (!$checkGuest) {
                $guest = Guest::create([
                    'name' => $request->customer['first_name'] . ' ' . $request->customer['last_name'],
                    'phone' => $request->customer['phone'],
                    'email' => $request->customer['email'],
                    'restaurant_id' => $venue->id,
                    'is_main' => true,
                    'is_from_restaurant_checkout' => true,
                ]);

                // create user
               User::create([
                    'name' => $request->customer['first_name'] . ' ' . $request->customer['last_name'],
                    'email' => $request->customer['email'],
                    // make hash random password
                    'password' => Hash::make(Str::random(8)),
                    'country_code' => 'US',
                    'enduser' => true
                ]);
            } else {
                $guest = $checkGuest;
            }


            $loyaltyProgram = LoyaltyProgram::where('venue_id', $venue->id)->first();


            if (!$loyaltyProgram) {
                $canEnroll = false;
            } else {
                $enrolledGuests = $loyaltyProgram->guests()->select('name', 'email', 'phone')
                    ->withPivot('created_at')->get();

                $isMainGuestEnrolled = $enrolledGuests->where('email', $guest->email)->first();

                if ($isMainGuestEnrolled) {
                    $canEnroll = false;

                    $percentage = $loyaltyProgram->reward_value;
                    $pointsEarned = $guest->calculatePointsEarned($request->total_amount, $percentage);

                    $enrolledGuests = $loyaltyProgram->guests()->select('name', 'email', 'phone')
                        ->withPivot('created_at')->get();

                    $isMainGuestEnrolled = $enrolledGuests->where('email', $guest->email)->first();

                    if($isMainGuestEnrolled) {
                        // Update guest's wallet balance with the earned points
                        if (!$guest->wallet) {
                            Wallet::create([
                                'guest_id' => $guest->id,
                                'balance' => $pointsEarned,
                                'venue_id' => $venue->id,
                            ]);
                        } else {
                            $guest->wallet->increment('balance', $pointsEarned);
                        }
                    }


                    // Add a record to the earnPointsHistory table with guest_id, reservation_id, and points_earned
                    $guest->earnPointsHistory()->create([
                        'order_id' => $order->id,
                        'points_earned' => $pointsEarned,
                        'venue_id' => $venue->id,
                    ]);
                } else {
                    $canEnroll = true;
                }
            }
        }


        // Order Items
        $orderItems = [];
        foreach ($order->orderProducts as $orderProduct) {
            if ($orderProduct->product) {
                $orderItems[] = [
                    'image' => $orderProduct->product->image_path ? Storage::disk('s3')->temporaryUrl($orderProduct->product->image_path, '+5 minutes') : null,
                    'price' => $orderProduct->product->price,
                    'title' => $orderProduct->product->title,
                    'quantity' => $orderProduct->product_quantity,
                    'total_price' => $orderProduct->product_total_price,
                    'article_no' => $orderProduct->product->article_no ?? '',
                ];
            }
        }

        $orderAddress = new StdClass();
        $orderAddress->address_line1 = $order->address->address_line1 ?? null;
        $orderAddress->address_line2 = $order->address->address_line2 ?? null;
        $orderAddress->state = $order->address->state ?? null;
        $orderAddress->city = $order->address->city ?? null;
        $orderAddress->postcode = $order->address->postcode ?? null;

        $logo = $venue->logo && $venue->logo !== 'logo' && $venue->logo !== 'https://via.placeholder.com/300x300' ? Storage::disk('s3')->temporaryUrl($venue->logo, '+5 minutes') : null;

        $rest_addr = DB::table('restaurant_addresses')->where('restaurants_id', $venue->id)->first();
        $venueAddress = null;
        if ($rest_addr) {
            $addressRetrieve = Address::where('id', $rest_addr->address_id)->first();

            // make address full
            $venueAddress = $addressRetrieve->address_line1 . ', ' . $addressRetrieve->address_line2 . ', ' . $addressRetrieve->city . ', ' . $addressRetrieve->state . ', ' . $addressRetrieve->postcode;
        }

        $orderFinal = [
            'order_address' => $orderAddress,
            'customer' => [
                'name' => $order->customer->name,
                'email' => $order->customer->email,
                'phone' => $order->customer->phone
            ],
            'order_items' => $orderItems,
            'order_summary' => [
                'discount_value' => $order->discount_total,
                'total_amount' => $order->total_amount,
                'subtotal' => $order->subtotal,
                'payment_method' => $order->paymentMethod?->name,
                'created_at' => $order->created_at->format('F d, Y h:i A'),
            ],
            'currency' => '$',
            'order_number' => $order->order_number,
            'is_for_self' => $order->is_for_self,
            'other_person_name' => $order->other_person_name,
            'is_for_food_order' => true,
            'delivery_fee' => $order->delivery_fee ?? 0,
            'hospital_room_id' => $order->hospital_room_id ?? null,
            'logo' => $logo,
            'venue_name' => $venue->name,
            'venue_address' => $venueAddress,
        ];


        Mail::to($request->customer['email'])->send(new CustomerOrderConfirmationEmail($venue->name, $orderFinal));

        // send just received an order email to the venue email
        if ( $venue->email) {
            Mail::to($venue->email)->send(new NewOrderEmail($venue->name));
        }

        return response()->json(['message' => 'Order added successfully', 'can_enroll' => $canEnroll ?? false, 'order_number' => $orderNumber], 200);
    }

    /**
     * @OA\Post(
     *     path="/orders/create-customer",
     *     summary="Create a new customer",
     *     tags={"Orders"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data for the new customer",
     *         @OA\JsonContent(
     *             required={"customer", "shipping_address"},
     *             @OA\Property(
     *                 property="customer",
     *                 type="object",
     *                 required={"first_name", "last_name", "phone", "email"},
     *                 @OA\Property(property="first_name", type="string", example="string"),
     *                 @OA\Property(property="last_name", type="string", example="string"),
     *                 @OA\Property(property="phone", type="string", example="0685555555"),
     *                 @OA\Property(property="email", type="string", format="email", example="strin@string.com")
     *             ),
     *             @OA\Property(
     *                 property="shipping_address",
     *                 type="object",
     *                 required={"line1", "state", "city", "postal_code"},
     *                 @OA\Property(property="line1", type="string", example="Sheshi Skenderbeu"),
     *                 @OA\Property(property="line2", type="string", example=""),
     *                 @OA\Property(property="state", type="string", example="TR"),
     *                 @OA\Property(property="city", type="string", example="Tirana"),
     *                 @OA\Property(property="postal_code", type="string", example="1013")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Customer created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Customer created successfully"),
     *             @OA\Property(property="customer_id", type="integer", example=123)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Validation error")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Unauthorized access")
     *         )
     *     )
     * )
     */
    public function createCustomer(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        $validator = Validator::make($request->all(), [
            'customer.first_name' => 'required|string',
            'customer.last_name' => 'required|string',
            'customer.phone' => 'required|string',
            'customer.email' => 'required|email',
            'shipping_address.line1' => 'required|string',
            'shipping_address.line2' => 'nullable|string',
            'shipping_address.state' => 'required|string',
            'shipping_address.city' => 'required|string',
            'shipping_address.postal_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $customerId = null;
        $guest = Guest::where('email', $request->customer['email'])->where('is_from_restaurant_checkout', true)->first();

        if (!$guest) {
            $guest = Guest::create([
                'name' => $request->customer['first_name'] . ' ' . $request->customer['last_name'],
                'phone' => $request->customer['phone'],
                'email' => $request->customer['email'],
                'address' => $request->shipping_address['line1'] . ' ' . $request->shipping_address['line2'],
                'restaurant_id' => $venue->id,
                'is_main' => true,
                'is_from_restaurant_checkout' => true,
            ]);
        }

        $customer = Customer::where('email', $request->customer['email'])->first();

        if (!$customer) {
            $customer = User::create([
                'name' => $request->customer['first_name'] . ' ' . $request->customer['last_name'],
                'email' => $request->customer['email'],
                'password' => Hash::make(Str::random(8)),
                'country_code' => 'US',
                'enduser' => true,
            ]);
        }

        $customerId = $customer->id;

        $addressData = $request->input('shipping_address');

        try {
            $address = new Address();
            $address->address_line1 = $addressData['line1'];
            $address->address_line2 = $addressData['line2'] ?? null;
            $address->city = $addressData['city'];
            $address->state = $addressData['state'];
            $address->postcode = $addressData['postal_code'];
            $address->is_for_retail = 0;
            $address->save();

            $customerAddress = new CustomerAddress();
            $customerAddress->customer_id = $customerId;
            $customerAddress->address_id = $address->id;
            $customerAddress->save();
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['error' => 'An error occurred while saving the address.'], 500);
        }

        return response()->json([
            'message' => 'Customer added successfully successfully.',
            'customer_id' => $customerId
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/orders/finalize-order",
     *     summary="Finalize an order",
     *     description="Finalizes the details of an order including processing payments, inventory, and sending confirmation.",
     *     tags={"Orders"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data needed to finalize the order",
     *         @OA\JsonContent(
     *             required={"subtotal", "order_products", "customer_id", "is_for_self", "split_payments"},
     *             @OA\Property(property="subtotal", type="number", example=100.50),
     *             @OA\Property(
     *                 property="order_products",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="product_quantity", type="integer", example=2),
     *                     @OA\Property(property="product_total_price", type="number", example=50.25)
     *                 )
     *             ),
     *             @OA\Property(property="customer_id", type="integer", example=123),
     *             @OA\Property(property="is_for_self", type="boolean", example=true),
     *             @OA\Property(
     *                 property="split_payments",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="payment_type", type="string", example="card"),
     *                     @OA\Property(property="amount", type="number", example=100.50)
     *                 )
     *             ),
     *             @OA\Property(property="notes", type="string", example="Leave at front door.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Order has been finalized successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid input data")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="An unexpected error occurred")
     *         )
     *     )
     * )
     */
    public function finalizeOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        // New admin auth checker
        $venue = $this->venueService->adminAuthCheck();

        $validator = Validator::make($request->all(), [
            // Ingredient request logic
//            'subtotal' => [
//                'required',
//                function ($attribute, $value, $fail) use ($request) {
//                    $orderIngredients = $request->input('order_ingredients');
//                    $expectedTotalAmount = 0;
//
//                    foreach ($orderIngredients as $ingredient) {
//                        $expectedTotalAmount += ($ingredient['ingredient_total_price'] * $ingredient['ingredient_quantity']);
//                    }
//
//                    if ($value != $expectedTotalAmount) {
//                        $fail('The subtotal of the order is not valid.');
//                    }
//                },
//            ],
//            'order_ingredients' => 'required|array',
//            'order_ingredients.*.ingredient_id' => 'required|exists:products,id',
//            'order_ingredients.*.ingredient_instructions' => 'nullable|string',
//            'order_ingredients.*.ingredient_quantity' => 'required|integer|min:1',
//            'ingredients.*.product_total_price' => [
//                'required',
//                function ($attribute, $value, $fail) use ($request) {
//                    $ingredientId =$request->input('ingredients.*.ingredient_id');
//                    $ingredientQuantity = $request->input('ingredients.*.ingredient_quantity');
//
//                    $ingredient = Ingredient::find($ingredientId);
//                    $ingredientExpectedTotalPrice = $ingredient->price * $ingredientQuantity;
//
//                    if ($value != $ingredientExpectedTotalPrice) {
//                        $fail('The total price for ingredient ID ' . $ingredientId . ' is not valid.');
//                    }
//                },
//            ],
            'subtotal' => [
                'required',
                function ($attribute, $value, $fail) use ($request) {
                    $orderProducts = $request->input('order_products');
                    $expectedTotalAmount = 0;

                    foreach ($orderProducts as $product) {
                        $expectedTotalAmount += ($product['product_total_price'] * $product['product_quantity']) - $product['product_discount_price'];
                    }

                    if ($value != $expectedTotalAmount) {
                        $fail('The subtotal of the order is not valid.');
                    }
                },
            ],
            'order_products' => 'required|array',
            'order_products.*.product_id' => 'required|exists:products,id',
            'order_products.*.product_instructions' => 'nullable|string',
            'order_products.*.product_quantity' => 'required|integer|min:1',
            'products.*.product_total_price' => [
                'required',
                function ($attribute, $value, $fail) use ($request) {
                    $productId = $request->input('products.*.product_id');
                    $productQuantity = $request->input('products.*.product_quantity');
                    $product = Product::find($productId);
                    $expectedTotalPrice = $product->price * $productQuantity;

                    if ($value != $expectedTotalPrice) {
                        $fail('The total price for product ID ' . $productId . ' is not valid.');
                    }
                },
            ],
            'coupon' => 'nullable|string',
            'discount_value' => 'nullable|numeric|min:0',
            'total_amount' => 'nullable|numeric|min:0',
            'customer_id' => 'required|exists:customers,id',
            'is_for_self' => 'required|boolean',
            // other person name is required if is_for_self is false
            'other_person_name' => Rule::requiredIf(function () use ($request) {
                return !$request->input('is_for_self');
            }),
//           TODO: Whether the payment method will be from the split_payment data ex: 90$ cash 10e card which one belongs here
//            'payment_method' => 'required|in:Cash,Card',   // OLD!!!
            // New payment request for payment
            'split_payments' => 'required|array',
            'split_payments.*.payment_type' => 'required|in:cash,card,other',
            'split_payments.*.amount' => 'required|numeric|min:0',
            'total_payment' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use ($request) {
                    $splitPayments = $request->input('split_payments');
                    $totalSplitAmount = array_sum(array_column($splitPayments, 'amount'));

                    if ($value != $totalSplitAmount) {
                        $fail('The total payment does not match the sum of split payments.');
                    }
                },
            ],
            'notes' => 'nullable|string',
            'discount_applied' => 'nullable|boolean',
            'discount_id' => 'nullable|exists:discounts,id',
            'delivery_fee' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $customerId = $request->customer_id;

        $coupon = null;

        if ($request->input('coupon')) {
            $coupon = Coupon::where('code', $request->coupon)->where('venue_id', $venue->id)->first();
            if (!$coupon) {
                return response()->json(['error' => 'Invalid coupon code'], 400);
            }

            // Check if the coupon is within the valid time frame
            $currentTime = Carbon::now();
            if ($currentTime->lt($coupon->start_time) || $currentTime->gt($coupon->expiry_time)) {
                return response()->json(['error' => 'Coupon is not valid at this time'], 400);
            }

            // Calculate discount value based on discount type
            $calculatedDiscount = 0;
            if ($coupon->discount_type === 'percentage_cart_discount') {
                $calculatedDiscount = ($request->subtotal * $coupon->discount_amount) / 100;
            } elseif ($coupon->discount_type === 'fixed_cart_discount') {
                $calculatedDiscount = $coupon->discount_amount;
            }

            if ($calculatedDiscount != $request->discount_value) {
                return response()->json(['error' => 'Discount value does not match the coupon'], 400);
            }

        }

        // check if discount is applied and then check if id is provided
        if ($request->discount_applied && !$request->discount_id) {
            return response()->json(['error' => 'Discount id is required'], 400);
        }

        // if discount id is provided then check if it exists
        if ($request->discount_id) {
            $discount = Discount::where('id', $request->discount_id)->where('venue_id', $venue->id)->first();
            if (!$discount) {
                return response()->json(['error' => 'Discount not found'], 404);
            }

            // if it is found check if it is valid
            $currentTime = Carbon::now();
            if ($currentTime->lt($discount->start_time) || $currentTime->gt($discount->end_time)) {
                return response()->json(['error' => 'Discount is not valid at this time'], 400);
            }

            // if it is valid check if the value % or fixed matches the discount value of order
            $calculatedDiscount = 0;
            if ($discount->type === 'percentage') {
                $calculatedDiscount = ($request->subtotal * $discount->value) / 100;
            } elseif ($discount->type === 'fixed') {
                $calculatedDiscount = $discount->value;
            }

            if ($calculatedDiscount != $request->discount_value) {
                return response()->json(['error' => 'Discount value does not match the discount'], 400);
            }
        }

//        TODO: If the currency will come from the payment split give $currency value here
        // Get currency setting for the venue
        $currency = '$';

        if ($request->total_amount != $request->subtotal - $request->discount_value) {
            return response()->json(['error' => 'Total amount does not match the subtotal'], 400);
        }

        $paymentTypes = collect($request->split_payments)
            ->pluck('payment_type')
            ->unique();

        $paymentMethod = $paymentTypes->join(' & ');

        $invoiceItems = $this->transformOrderProductsToInvoiceItems($request->order_products);

        // Create the order
        $order = Order::create([
            'total_amount' => $request->total_amount,
            'subtotal' => $request->subtotal,
            'customer_id' => $request->customer_id,
            'restaurant_id' => $venue->id,
            'status' => OrderStatus::NEW_ORDER,
            'stripe_payment_id' => $paymentIntent ?? null,
            'notes' => $request->input('notes') ?? null,
            'currency' => $currency,
            'address_id' => $address->id ?? null,
            'payment_status' => $request->stripe_payment_id ? 'paid' : 'unpaid',
            'payment_method' => $paymentMethod,
            'delivery_fee' => $request->delivery_fee ?? 0,
            'is_for_self' => $request->is_for_self ?? 1,
            'other_person_name' => $request->other_person_name ?? null,
            'added_by_restaurant' => true,
        ]);

        $additionalInvoiceData = [
            'customer_id' => $order->customer_id,
            'type' => 'delivery',
            'total_amount' => $request->input('total_amount'),
            'payment_method' => $paymentMethod,
            'invoice_items' => $invoiceItems,
        ];

        $invoiceController = resolve(InvoiceController::class);
        $invoiceResponse = $invoiceController->store(new Request($additionalInvoiceData));

        // Process split payments
        foreach ($request->split_payments as $paymentData) {
            $order->orderSplitPayments()->create([
                'payment_type' => $paymentData['payment_type'],
                'amount' => $paymentData['amount'],
            ]);
        }

        // Process products
        foreach ($request->order_products as $productData) {
            $productData['quantity'] = $productData['product_quantity'];
            $result = $this->processInventoryItem($productData, $order, false);

            if (isset($result['error'])) {
                return response()->json(['error' => $result['error']], 400);
            }
        }

        // Process ingredients (later to be implemented)
//        foreach ($request->order_ingredients as $ingredientData) {
//            $ingredientData['quantity'] = $ingredientData['ingredient_quantity'];
//            $result = $this->processInventoryItem($ingredientData, $order, true);
//
//            if (isset($result['error'])) {
//                return response()->json(['error' => $result['error']], 400);
//            }
//        }

        $order->load('orderProducts');
        // If ingredient feature will proceed with development in frontend
//        $order->load('orderProducts', 'orderIngredients');

        // Link the coupon to the order
        if ($coupon) {
            DB::table('order_coupons')->insert([
                'order_id' => $order->id,
                'coupon_id' => $coupon->id,
                'customer_id' => $customerId,
                'discount_value' => $request->discount_value
            ]);

            // update order discount total
            $order->update(['discount_total' => $request->discount_value]);
        }

        // Link the discount to the order
        if ($request->discount_id) {
            $discount = Discount::where('id', $request->discount_id)->where('venue_id', $venue->id)->first();

            if (!$discount) {
                return response()->json(['error' => 'Discount not found'], 404);
            }

            // if it is found check if it is valid
            $currentTime = Carbon::now();
            if ($currentTime->lt($discount->start_time) || $currentTime->gt($discount->end_time)) {
                return response()->json(['error' => 'Discount is not valid at this time'], 400);
            }

            DB::table('order_discounts')->insert([
                'order_id' => $order->id,
                'discount_id' => $discount->id,
                'customer_id' => $customerId,
                'discount_value' => $request->discount_value
            ]);

            // update order discount total
            $order->update(['discount_total' => $request->discount_value]);
        }

        $venuePrefix = strtoupper(substr($venue->name, 0, 2));
        $randomFourDigits = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        $orderNumber = $venuePrefix .'-'. $order->id . $randomFourDigits;
        $order->update(['order_number' => $orderNumber]);

        // Retrieve the customer information from the database
        $customer = Customer::find($customerId);

        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $customerEmail = $customer->email;

        $guest = Guest::where('email', $customerEmail)->where('is_from_restaurant_checkout', true)->first();

        // Send order confirmation email
        if ($guest) {

            $loyaltyProgram = LoyaltyProgram::where('venue_id', $venue->id)->first();

            if (!$loyaltyProgram) {
                $canEnroll = false;
            } else {
                $enrolledGuests = $loyaltyProgram->guests()->select('name', 'email', 'phone')
                    ->withPivot('created_at')->get();

                $isMainGuestEnrolled = $enrolledGuests->where('email', $guest->email)->first();

                if ($isMainGuestEnrolled) {
                    $canEnroll = false;

                    $percentage = $loyaltyProgram->reward_value;
                    $pointsEarned = $guest->calculatePointsEarned($request->total_amount, $percentage);

                    $enrolledGuests = $loyaltyProgram->guests()->select('name', 'email', 'phone')
                        ->withPivot('created_at')->get();

                    $isMainGuestEnrolled = $enrolledGuests->where('email', $guest->email)->first();

                    if($isMainGuestEnrolled) {
                        // Update guest's wallet balance with the earned points
                        if (!$guest->wallet) {
                            Wallet::create([
                                'guest_id' => $guest->id,
                                'balance' => $pointsEarned,
                                'venue_id' => $venue->id,
                            ]);
                        } else {
                            $guest->wallet->increment('balance', $pointsEarned);
                        }
                    }

                    // Add a record to the earnPointsHistory table with guest_id, reservation_id, and points_earned
                    $guest->earnPointsHistory()->create([
                        'order_id' => $order->id,
                        'points_earned' => $pointsEarned,
                        'venue_id' => $venue->id,
                    ]);
                } else {
                    $canEnroll = true;
                }
            }
        }

        // Order Items
        $orderItems = [];
        foreach ($order->orderProducts as $orderProduct) {
            if ($orderProduct->product) {
                $orderItems[] = [
                    'image' => $orderProduct->product->image_path ? Storage::disk('s3')->temporaryUrl($orderProduct->product->image_path, '+5 minutes') : null,
                    'price' => $orderProduct->product->price,
                    'title' => $orderProduct->product->title,
                    'quantity' => $orderProduct->product_quantity,
                    'total_price' => $orderProduct->product_total_price,
                    'article_no' => $orderProduct->product->article_no ?? '',
                ];
            }
        }

        $orderAddress = new StdClass();
        $orderAddress->address_line1 = $order->address->address_line1 ?? null;
        $orderAddress->address_line2 = $order->address->address_line2 ?? null;
        $orderAddress->state = $order->address->state ?? null;
        $orderAddress->city = $order->address->city ?? null;
        $orderAddress->postcode = $order->address->postcode ?? null;

        $logo = $venue->logo && $venue->logo !== 'logo' && $venue->logo !== 'https://via.placeholder.com/300x300' ? Storage::disk('s3')->temporaryUrl($venue->logo, '+5 minutes') : null;

        $rest_addr = DB::table('restaurant_addresses')->where('restaurants_id', $venue->id)->first();
        $venueAddress = null;
        if ($rest_addr) {
            $addressRetrieve = Address::where('id', $rest_addr->address_id)->first();

            // make address full
            $venueAddress = $addressRetrieve->address_line1 . ', ' . $addressRetrieve->address_line2 . ', ' . $addressRetrieve->city . ', ' . $addressRetrieve->state . ', ' . $addressRetrieve->postcode;
        }

        $orderFinal = [
            'order_address' => $orderAddress,
            'customer' => [
                'name' => $order->customer->name,
                'email' => $order->customer->email,
                'phone' => $order->customer->phone
            ],
            'order_items' => $orderItems,
            'order_summary' => [
                'discount_value' => $order->discount_total,
                'total_amount' => $order->total_amount,
                'subtotal' => $order->subtotal,
//               TODO: what payment method to show in the end after saving the records in splitorderpayment??
                'payment_method' => $order->paymentMethod?->name,
                'created_at' => $order->created_at->format('F d, Y h:i A'),
            ],
//          TODO: Currency coming from frontend if 2 records cash any card which
            'currency' => '$',
            'order_number' => $order->order_number,
            'is_for_self' => $order->is_for_self,
            'other_person_name' => $order->other_person_name,
            'is_for_food_order' => true,
            'delivery_fee' => $order->delivery_fee ?? 0,
            'hospital_room_id' => $order->hospital_room_id ?? null,
            'logo' => $logo,
            'venue_name' => $venue->name,
            'venue_address' => $venueAddress,
        ];


        Mail::to($customerEmail)->send(new CustomerOrderConfirmationEmail($venue->name, $orderFinal));

        // Send just received an order email to the venue email
        if ( $venue->email) {
            Mail::to($venue->email)->send(new NewOrderEmail($venue->name));
        }

        return response()->json(['message' => 'Order added successfully', 'can_enroll' => $canEnroll ?? false, 'order_number' => $orderNumber], 200);
    }

    /**
     * Processes an inventory item by updating its quantity and creating an associated inventory activity record.
     * This method is intended for internal use within order processing inventory updates for products or ingredients.
     *
     * @param array $itemData Data about the item being processed, including its ID and quantity.
     * @param Order $order The order to which this item is associated.
     * @param bool $isProduct Flag indicating whether the item is a product (true) or an ingredient (false).
     * @return array Returns an array with a success status and message or an error if the operation fails.
     */
    private function processInventoryItem($itemData, $order, $isProduct = false)
    {

        // Identify if it's a Product or Ingredient
//        $item = $isProduct ? Product::find($itemData['product_id']) : Ingredient::find($itemData['ingredient_id']);

        $product = Product::find($itemData['product_id']);

        if (!$product) {
            return ['error' => 'Product not found.'];
        }

        if ($product && $product->inventories->count() > 0) {
            $inventoryItem = $product->inventories->first()->products()
//                ->where($isProduct ? 'product_id' : 'ingredient_id', $product->id)
                ->where('product_id', $product->id)
                ->first();

            if ($inventoryItem && $inventoryItem->pivot->quantity >= $itemData['quantity']) {
                $inventoryItem->pivot->quantity -= $itemData['quantity'];
                $inventoryItem->pivot->save();

                // Create an InventoryActivity record for the deduction
                $activity = new InventoryActivity();
                if ($product) {
                    $activity->product_id = $product->id;
                }
                $activity->quantity = $itemData['quantity'];
                $activity->activity_category = InventoryActivityCategory::ORDER_SALE;
                $activity->activity_type = 'deduct';
                $activity->inventory_id = $inventoryItem->pivot->inventory_id;
                $activity->order_id = $order->id;
                $activity->save();

            } else {
                return ['error' => 'Insufficient quantity in inventory for item: ' . $product->title];
            }
        }

        if ($product) {
            OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $itemData['product_id'],
                'product_instructions' => $itemData['product_instructions'] ?? null,
                'product_quantity' => $itemData['product_quantity'],
                'product_total_price' => $itemData['product_total_price'] * $itemData['product_quantity']
            ]);
        } else {
            // Add ingredient to the order
//            OrderIngredient::create([
//                'order_id' => $order->id,
//                'ingredient_id' => $itemData['ingredient_id'],
//                'ingredient_instructions' => $itemData['ingredient_instructions'] ?? null,
//                'ingredient_quantity' => $itemData['ingredient_quantity'],
//                'ingredient_total_price' => $itemData['ingredient_total_price'] * $itemData['ingredient_quantity']
//            ]);
        }

        return ['success' => true];
    }

    private function transformOrderProductsToInvoiceItems(array $orderProducts)
    {
        $invoiceItems = array_map(function ($item) {
            return [
                'product_id' => $item['product_id'],
                'quantity' => $item['product_quantity'],
            ];
        }, $orderProducts);

        return $invoiceItems;
    }

    public function validateMMCoupon(Request $request): \Illuminate\Http\JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'mm_coupon_code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $member = Member::where('old_platform_member_code', $request->mm_coupon_code)
            ->whereNull('deleted_at')->first();

        if(!$member) {
            return response()->json(['error' => 'Member not found'], 404);
        }

        $coupon = Coupon::where('code', "EXTRA25")->where('venue_id', $venue->id)->first();

        if (!$coupon) {
            return response()->json(['error' => 'Invalid coupon code'], 404);
        }

        // Check if the coupon is expired or not started yet
        $currentDate = now();
        if ($coupon->start_time > $currentDate || $coupon->expiry_time < $currentDate) {
            return response()->json(['error' => 'Coupon is not valid for this time period'], 400);
        }

        // Calculate the discount based on discount_type
        $discountValue = 0;
        if ($coupon->discount_type === 'percentage_cart_discount') {
            $discountValue = ($coupon->discount_amount / 100) * $request->subtotal;
        } elseif ($coupon->discount_type === 'fixed_cart_discount') {
            $discountValue = $coupon->discount_amount;
        }

        // Ensure the discount does not exceed the subtotal
        $discountValue = min($discountValue, $request->subtotal);

        return response()->json(['discount_value' => $discountValue]);
    }

    public function validateCoupon(Request $request): \Illuminate\Http\JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'coupon_code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $coupon = Coupon::where('code', $request->coupon_code)->where('venue_id', $venue->id)->first();

        if (!$coupon) {
            return response()->json(['error' => 'Invalid coupon code'], 404);
        }

        // Check if the coupon is expired or not started yet
        $currentDate = now();
        if ($coupon->start_time > $currentDate || $coupon->expiry_time < $currentDate) {
            return response()->json(['error' => 'Coupon is not valid for this time period'], 400);
        }

        // Calculate the discount based on discount_type
        $discountValue = 0;
        if ($coupon->discount_type === 'percentage_cart_discount') {
            $discountValue = ($coupon->discount_amount / 100) * $request->subtotal;
        } elseif ($coupon->discount_type === 'fixed_cart_discount') {
            $discountValue = $coupon->discount_amount;
        }

        // Ensure the discount does not exceed the subtotal
        $discountValue = min($discountValue, $request->subtotal);

        return response()->json(['discount_value' => $discountValue]);
    }

    public function getRetailOrders(): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $orders = Order::where('restaurant_id', $venue->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $orders->load('customer');

        $transformedOrders = $orders->map(function ($order) {
            return [
                'order_number' => $order->order_number,
                'id' => $order->id,
                'customer_full_name' => $order->customer->name,
                'total_amount' => $order->total_amount,
                'status' => $order->status,
                'currency' => $order->currency,
                'stripe_payment_id' => $order->stripe_payment_id,
                'created_at' => $order->created_at->format('F d, Y h:i A'),
            ];
        });

        return response()->json(['orders' => $transformedOrders]);
    }

    // + update status and sms on notification order status +  fix customers list
    public function deliveryOrderDetails($id): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        try {

            $order = Order::with(['address', 'customer', 'orderProducts.product', 'paymentMethod', 'promotion'])
                ->where('restaurant_id', $venue->id)
                ->findOrFail($id);

            // Order Items
            $orderItems = [];
            foreach ($order->orderProducts as $orderProduct) {
                if ($orderProduct->product) {
                    $orderItems[] = [
                        'image' => $orderProduct->product->image_path ? Storage::disk('s3')->temporaryUrl($orderProduct->product->image_path, '+5 minutes') : null,
                        'price' => $orderProduct->product->price,
                        'title' => $orderProduct->product->title,
                        'quantity' => $orderProduct->product_quantity,
                        'total_price' => $orderProduct->product_total_price
                    ];
                }
            }

            $orderAddress = new StdClass();
            $orderAddress->address_line1 = $order->address->address_line1 ?? null;
            $orderAddress->address_line2 = $order->address->address_line2 ?? null;
            $orderAddress->state = $order->address->state ?? null;
            $orderAddress->city = $order->address->city ?? null;
            $orderAddress->postcode = $order->address->postcode ?? null;

            // Get currency setting for the venue
            $storeSetting = StoreSetting::where('venue_id', $venue->id)->first();
            $currency = '$  ';

            $response = [
                'delivery_address' => $orderAddress,
                'customer' => [
                    'name' => $order->customer->name,
                    'email' => $order->customer->email,
                    'phone' => $order->customer->phone
                ],
                'status' => $order->status,
                'order_items' => $orderItems,
                'order_summary' => [
                    'discount_value' => $order->discount_total,
                    'total_amount' => $order->total_amount,
                    'subtotal' => $order->subtotal,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->paymentMethod?->name,
                    'delivery_fee' => $order->delivery_fee,
                    'created_at' => $order->created_at->format('F d, Y h:i A'),
                ],
                'currency' => $currency,
                'order_number' => $order->order_number,
                'order_for' => $order->is_for_self ? 'Self' : $order->other_person_name,
                'is_for_self' => $order->is_for_self ?? null,
                'hospital_room_id' => $order->hospital_room_id ?? null,
            ];

            return response()->json(['data' => $response], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Order not found'], 404);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function retailOrderDetails($id): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        try {
            $order = Order::with(['address', 'customer', 'orderProducts.product', 'paymentMethod', 'promotion', 'statusChanges'])
                ->where('restaurant_id', $venue->id)
                ->findOrFail($id);

            // Order Items
            $orderItems = [];
            foreach ($order->orderProducts as $orderProduct) {
                if ($orderProduct->product) {
                    $orderItems[] = [
                        'image' => $orderProduct->product->image_path ? Storage::disk('s3')->temporaryUrl($orderProduct->product->image_path, '+5 minutes') : null,
                        'price' => $orderProduct->product->price,
                        'title' => $orderProduct->product->title,
                        'quantity' => $orderProduct->product_quantity,
                        'total_price' => $orderProduct->product_total_price
                    ];
                }
            }

            $orderAddress = new StdClass();
            $orderAddress->address_line1 = $order->address->address_line1 ?? null;
            $orderAddress->address_line2 = $order->address->address_line2 ?? null;
            $orderAddress->state = $order->address->state ?? null;
            $orderAddress->city = $order->address->city ?? null;
            $orderAddress->postcode = $order->address->postcode ?? null;

            // Get currency setting for the venue
            $storeSetting = StoreSetting::where('venue_id', $venue->id)->first();
            $currency = $storeSetting ? $storeSetting->currency : null;

            // Status Changes
            $statusChanges = $order->statusChanges->map(function ($statusChange) {
                return [
                    'new_status' => $statusChange->new_status,
                    'readable_new_status' => $this->getStatusTitle($statusChange->new_status),
                    'changed_at' => $statusChange->changed_at
                ];
            });

            $response = [
                'shipping_address' => $orderAddress,
                'customer' => [
                    'name' => $order->customer->name,
                    'email' => $order->customer->email,
                    'phone' => $order->customer->phone
                ],
                'status' => $order->status,
                'order_items' => $orderItems,
                'order_summary' => [
                    'discount_value' => $order->discount_total,
                    'total_amount' => $order->total_amount,
                    'subtotal' => $order->subtotal,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->paymentMethod?->name,
                    'created_at' => $order->created_at->format('F d, Y h:i A'),
                ],
                'currency' => $currency,
                'order_number' => $order->order_number,
                'status_changes' => $statusChanges,
            ];

            return response()->json(['data' => $response], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Order not found'], 404);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function webProductDetails($id)
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        try {
            $product = Product::where('restaurant_id', $venue->id)->with(['variations.attribute', 'variations.value'])->find($id);
            if (!$product) {
                return response()->json(['message' => 'Not found product'], 404);
            }

            $options = DB::table('product_options')->where('product_id', $product->id)->where('type', 'option')->get();
            $additions = DB::table('product_options')->where('product_id', $product->id)->where('type', 'addition')->get();

            $product->image_path = $product->image_path ? Storage::disk('s3')->temporaryUrl($product->image_path, '+5 minutes') : null;

            // check if it has category
            $productCategoryRelationship = DB::table('product_category')->where('product_id', $product->id)->first();
            if ($productCategoryRelationship) {
                $productCategory =  new StdClass;
                $productCategory->id = $productCategoryRelationship->category_id;
                $productCategory->title = DB::table('categories')->where('id', $productCategoryRelationship->category_id)->first()->title;

                $product->category = $productCategory;
            }

            $galleryProduct = Gallery::where('product_id',  $product->id)->get();

            $managedGallery = $galleryProduct->map(function ($item) {
                return [
                    'photo_id' => $item->photo_id,
                    'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
                ];
            });

            $product->gallery = $managedGallery;

            $variationsOutput = $product->variations->map(function ($variation) {
                return [
                    'id' => $variation->id,
                    'attribute' => [
                        'name' => $variation->attribute->name,
                        'id' => $variation->attribute->id,
                    ],
                    'value' => [
                        'name' => $variation->value->value,  // Assuming the name of the attribute value is stored in 'value' column.
                        'id' => $variation->value->id,
                    ],
                    'price' => $variation->price,
                ];
            })->toArray();

            $stockQuantity = DB::table('inventory_retail')->where('product_id', $product->id)->first();

            // Step 2: Convert the Eloquent model to an array
            $productArray = $product->toArray();

            $attributes = DB::table('product_attribute_value')
                ->join('attribute_values', 'product_attribute_value.attribute_value_id', '=', 'attribute_values.id')
                ->join('product_attributes', 'attribute_values.attribute_id', '=', 'product_attributes.id')
                ->where('product_attribute_value.product_id', $product->id)
                ->select(
                    'product_attributes.name as attribute_name',
                    'attribute_values.value as attribute_value',
                    'product_attributes.id as attribute_id',
                    'product_attribute_value.visible_on_product_page as visible_on_product_page',
                    'product_attribute_value.used_for_variations as used_for_variations'
                )
                ->get();

            $groupedAttributes = [];

            foreach ($attributes as $attribute) {
                if (!isset($groupedAttributes[$attribute->attribute_id])) {
                    $groupedAttributes[$attribute->attribute_id] = [
                        'id' => $attribute->attribute_id,
                        'name' => $attribute->attribute_name,
                        'visible_on_product_page' => $attribute->visible_on_product_page,
                        'used_for_variations' => $attribute->used_for_variations,
                        'values' => [],
                    ];
                }

                $groupedAttributes[$attribute->attribute_id]['values'][] = $attribute->attribute_value;
            }

            $attributesFinal = [];
            foreach ($groupedAttributes as $attribute) {
                $attribute['values'] = implode(', ', $attribute['values']);
                $attributesFinal[] = $attribute;
            }

            $productCategory = DB::table('product_category')->where('product_id', $product->id)->first();
            if ($productCategory) {
                $relatedProducts = DB::table('product_category')->where('category_id', $productCategory->category_id)->where('product_id', '!=', $product->id)->get();

                $relatedProductsFinal = $relatedProducts->map(function ($item) {
                    $findItem = Product::find($item->product_id);
                    return [
                        'id' => $findItem->id,
                        'title' => $findItem->title,
                        'short_description' => $findItem->short_description,
                        'price' => $findItem->price,
                        'image_path' => $findItem->image_path ? Storage::disk('s3')->temporaryUrl($findItem->image_path, '+5 minutes') : null,
                    ];
                });
            } else {
                $relatedProductsFinal = [];
            }


            // Step 3: Overwrite the 'attributes' key
            $productArray['attributes'] = $attributesFinal;
            $productArray['variations'] = $variationsOutput;
            $productArray['related_products'] = $relatedProductsFinal;
            $productArray['venue_name'] = $venue->name;
            $productArray['venue_logo'] = $venue->logo ? Storage::disk('s3')->temporaryUrl($venue->logo, '+5 minutes') : null;
            $productArray['currency'] =  $venue->storeSettings()->first()->currency ?: null;
            $productArray['stock_quantity'] =  $stockQuantity ? $stockQuantity->stock_quantity : 0;


            return response()->json(['message' => 'Product retrieved successfully',
                'product' => $productArray, 'options' => $options, 'additions' => $additions], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function shippingMethods(): \Illuminate\Http\JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        try {

            $shippingZones = $venue->shippingZones()->with(['shippingMethods' => function($query) {
                $query->withPivot('has_minimum_order_amount', 'flat_rate_cost', 'minimum_order_amount');
            }])->orderBy('created_at', 'desc')->get();

            $shippingMethods = [];
            // if exists get first always
            if ($shippingZones->count() > 0) {
                $shippingZone = $shippingZones[0];

                foreach ($shippingZone->shippingMethods as $method) {

                    $shippingMethods[] = [
                        'method_id' => $method->id,
                        'method_type' => $method->type,
                        'method_name' => $method->name === 'flat_rate' ? 'Flat Rate' : 'Free Shipping',
                        'flat_rate_cost' => $method->pivot->flat_rate_cost,
                        'has_minimum_order_amount' => $method->pivot->has_minimum_order_amount,
                        'minimum_order_amount' => $method->pivot->minimum_order_amount
                    ];
                }
            };

            return response()->json(['message' => 'Shipping methods retrieved successfully',
                'shipping_methods' => $shippingMethods], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function changeOrderStatus(Request $request, $orderId): \Illuminate\Http\JsonResponse
    {
        // Ensure user has access to modify orders.
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // Retrieve the order based on order id.
        $order = Order::where('restaurant_id', $venue->id)
            ->findOrFail($orderId);
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $newStatus = $request->input('status');
        if (!$newStatus) {
            return response()->json(['error' => 'Status is required'], 400);
        }

        if (!in_array($newStatus, [OrderStatus::NEW_ORDER, OrderStatus::ON_HOLD, OrderStatus::PROCESSING, OrderStatus::ORDER_CANCELLED, OrderStatus::ORDER_COMPLETED])) {
            return response()->json(['error' => 'Invalid status provided'], 400);
        }

        if (!$this->isStatusTransitionValid($order->status, $newStatus)) {
            return response()->json(['error' => 'Invalid status transition'], 400);
        }

        // Update order status and save.
        $order->status = $newStatus;

        // If the order is marked as completed, set payment status as paid
        if ($newStatus === OrderStatus::ORDER_COMPLETED) {
            $order->payment_status = 'paid';
        }

        $order->save();

        // Record the new status and the date
        OrderStatusChange::create([
            'order_id' => $order->id,
            'new_status' => $newStatus,
            'changed_at' => now(),
        ]);

        return response()->json(['message' => 'Order status updated successfully'], 200);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ConfigurationException
     * @throws TwilioException
     * @throws ContainerExceptionInterface
     */
    public function changeOrderDeliveryStatus(Request $request, $orderId): \Illuminate\Http\JsonResponse
    {
        // Ensure user has access to modify orders.
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // Retrieve the order based on order id.
        $order = Order::where('restaurant_id', $venue->id)
            ->findOrFail($orderId);
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $newStatus = $request->input('status');
        if (!$newStatus) {
            return response()->json(['error' => 'Status is required'], 400);
        }

        if (!in_array($newStatus, [OrderStatus::NEW_ORDER, OrderStatus::ORDER_ON_DELIVERY, OrderStatus::PROCESSING, OrderStatus::ORDER_CANCELLED, OrderStatus::ORDER_COMPLETED])) {
            return response()->json(['error' => 'Invalid status provided'], 400);
        }

        if (!$this->isDeliveryStatusTransitionValid($order->status, $newStatus)) {
            return response()->json(['error' => 'Invalid status transition'], 400);
        }

        // Update order status and save.
        $order->status = $newStatus;

        // If the order is marked as completed, set payment status as paid
        if ($newStatus === OrderStatus::ORDER_COMPLETED) {
            $order->payment_status = 'paid';
        }

        $order->save();

        $order->load('customer');
        $order->load('address');

        $customerName = $order->customer->name;
        $customerPhone = $order->customer->phone;

        $smsMessage = match ($order->status) {
            OrderStatus::PROCESSING => "Hello $customerName, your order at $venue->name has been accepted and is being prepared. We will update you when it is on your way.",
            OrderStatus::ORDER_CANCELLED => "Hi $customerName, we're sorry to inform you that your order at $venue->name has been cancelled. Please contact us for further assistance.",
            OrderStatus::ORDER_ON_DELIVERY => "Hello $customerName, your order from $venue->name is on its way. Expect it to arrive shortly!",
            OrderStatus::ORDER_COMPLETED => "Hello $customerName, your order from $venue->nam has been successfully delivered. We hope you enjoyed your meal!",
            default => '',
        };

        // Twilio account information
        $account_sid = env('TWILIO_ACCOUNT_SID');
        $auth_token = env('TWILIO_AUTH_TOKEN');
        $twilio_number = env('TWILIO_NUMBER');

        try {

            $client = new Client($account_sid, $auth_token);
            // here send SMS
            $client->messages->create(
                $customerPhone,
                array(
                    'from' => $twilio_number,
                    'body' => $smsMessage
                )
            );
        }
        catch (TwilioException $e) {
            echo "Error: " . $e->getMessage();
        }


        return response()->json(['message' => 'Order status updated successfully'], 200);
    }

    public function isStatusTransitionValid($currentStatus, $newStatus): bool
    {
        $allowedTransitions = [
            OrderStatus::NEW_ORDER => [OrderStatus::PROCESSING, OrderStatus::ORDER_CANCELLED, OrderStatus::ON_HOLD],
            OrderStatus::ON_HOLD => [OrderStatus::PROCESSING, OrderStatus::ORDER_CANCELLED],
            OrderStatus::PROCESSING => [OrderStatus::ORDER_CANCELLED, OrderStatus::ON_HOLD, OrderStatus::ORDER_COMPLETED]
        ];

        return in_array($newStatus, $allowedTransitions[$currentStatus] ?? []);
    }

    public function isDeliveryStatusTransitionValid($currentStatus, $newStatus): bool
    {
        $allowedTransitions = [
            OrderStatus::NEW_ORDER =>
                [
                    OrderStatus::PROCESSING,
                    OrderStatus::ORDER_CANCELLED,
                ],

            OrderStatus::PROCESSING => [
                OrderStatus::ORDER_CANCELLED,
                OrderStatus::ORDER_ON_DELIVERY,
                OrderStatus::ORDER_COMPLETED
            ],
            OrderStatus::ORDER_ON_DELIVERY => [
                OrderStatus::ORDER_COMPLETED,
                OrderStatus::ORDER_CANCELLED
            ],
        ];

        return in_array($newStatus, $allowedTransitions[$currentStatus] ?? []);
    }

    // Mapping function to convert status value to human-readable form
    public function getStatusTitle($statusValue): string
    {
        $statusMap = [
            'processing' => 'Processing',
            'on_hold' => 'On Hold',
            'order_cancelled' => 'Cancelled',
            'new' => 'New',
            'order_completed' => 'Order Completed'
        ];
        return $statusMap[$statusValue] ?? $statusValue;
    }

}


