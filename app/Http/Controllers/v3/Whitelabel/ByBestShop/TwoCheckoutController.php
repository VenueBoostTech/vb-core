<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\InventoryActivity;
use App\Models\Product;
use App\Models\Customer;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Restaurant;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Mail\CustomerOrderConfirmationEmail;
use App\Mail\NewOrderEmail;
use TwoCheckout;
use TwoCheckout_Charge;
use TwoCheckout_Error;
use function response;

class TwoCheckoutController extends Controller
{
    private $sellerId;
    private $secretKey;
    private $privateKey;
    private $apiUrl;
    private $sandboxMode;

    public function __construct()
    {
        $this->sellerId = config('services.2checkout.seller_id');
        $this->secretKey = config('services.2checkout.secret_key');
        $this->privateKey = config('services.2checkout.private_key');
        $this->apiUrl = config('services.2checkout.sandbox')
            ? 'https://sandbox.2checkout.com/checkout/api/1/'
            : 'https://2checkout.com/checkout/api/1/';
        $this->sandboxMode = config('services.2checkout.sandbox');
    }

    public function quickCheckout(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'app_key' => 'required|string',
                'first_name' => 'required|string',
                'last_name' => 'nullable|string',
                'address' => 'required|string',
                'phone' => 'required|string',
                'email' => 'nullable|string',
                'country' => 'required|string',
                'city' => 'required|string',
                'payment_method' => 'required|string', // Add this to handle different payment methods
                'token' => 'required_if:payment_method,2checkout', // Card token for 2Checkout
                'order_products' => 'required|array',
                'order_products.*.product_id' => 'required|integer',
                'order_products.*.product_quantity' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => $validator->errors()], 400);
            }

            $app_key = $request->input('app_key');
            $venue = Restaurant::where('app_key', $app_key)->first();
            if (!$venue) {
                return response()->json(['message' => 'Venue not found'], 404);
            }

            $first_name = $request->input('first_name');
            $last_name = $request->input('last_name');
            $address_details = $request->input('address');
            $phone = $request->input('phone');
            $country = $request->input('country');
            $city = $request->input('city');
            $email = $request->input('email');
            $payment_method = $request->input('payment_method');

            $customer = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address_details,
            ];

            $order_products = $request->input('order_products');
            $total = $this->getProductsTotal($order_products);
            if ($total['status'] == false) {
                return response()->json(['message' => $total['error']], 400);
            }
            $total_price = $total['value'];

            $result_order = null;
            // Handle payment via 2Checkout
            if ($payment_method == '2checkout') {
                TwoCheckout::privateKey($this->privateKey);
                TwoCheckout::sellerId($this->sellerId);
                Twocheckout::verifySSL(false); // set it true for production
                //TwoCheckout::sandbox($this->sandboxMode); // Set to false for production
                // Twocheckout::format('json');

                $token = $request->input('token');

                $address = [
                    "name" => $first_name . ' ' . $last_name,
                    "addrLine1" => $address_details,
                    "city" => $city,
                    "state" => $city,
                    "zipCode" => '0000',
                    "country" => $country,
                    "email" => $email,
                    "phoneNumber" => $phone
                ];

                $paymentInfo = [
                    "sellerId" => $this->sellerId,
                    "merchantOrderId" => rand(1000, 100000),
                    "token" => $token,
                    "currency" => 'USD',
                    "total" => $total_price,
                    "billingAddr" => $address,
                    'shippingAddr' => $address,
                    "demo" => 'Y'
                ];

                try {
                    $charge = TwoCheckout_Charge::auth($paymentInfo);

                    if ($charge['response']['responseCode'] == 'APPROVED') {
                        // Payment successful, proceed with order placement
                        $result = $this->finalizeOrder($venue, $customer, $order_products, $total_price, $charge['response']['transactionId']);

                        if (!$result['status']) {
                            return response()->json(['message' => $result['error']], 500);
                        }

                        $result_order = $result['order'];
                    } else {
                        return response()->json(['message' => 'Payment failed. Please try again.'], 400);
                    }
                } catch (TwoCheckout_Error $e) {
                    return response()->json(['message' => 'Payment error: ' . $e->getMessage()], 400);
                }
            } else {
                // Handle other payment methods like cash
                $result = $this->finalizeOrder($venue, $customer, $order_products, $total_price, null);

                if (!$result['status']) {
                    return response()->json(['message' => $result['error']], 500);
                }
                $result_order = $result['order'];
            }

            // if ($venue->email) {
            //     Mail::to($venue->email)->send(new NewOrderEmail($venue->name));
            // }

            return response()->json(['message' => 'Order added successfully', 'order' => $result_order], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function getProductsTotal($order_products)
    {
        $total = 0;
        foreach ($order_products as $productData) {
            $product = Product::find($productData['product_id']);
            if (!$product) {
                return [
                    'status' => false,
                    'error' => 'Product not found'
                ];
            }

            if ($product && $product->inventories->count() > 0) {
                $inventoryProduct = $product->inventories->first()->products()
                    ->where('product_id', $product->id)
                    ->first();

                if ($inventoryProduct && $inventoryProduct->pivot->quantity >= $productData['product_quantity']) {

                } else {
                    return [
                        'status' => false,
                        'error' => 'Insufficient quantity in inventory for product: ' . $product->title
                    ];
                }
            }

            $total += $product->price;
        }

        return [
            'status' => true,
            'value' => $total
        ];
    }

    public function finalizeOrder($venue, $customer, $order_products, $total_price, $transactionId)
    {
        try {
            $user = User::create([
                'name' => $customer['first_name'] . ' ' . $customer['last_name'],
                'email' => $customer['email'],
                'password' => Hash::make('1234'),
                'country_code' => 'US',
                'end_user' => true
            ]);

            $customer = Customer::create([
                'user_id' => $user->id,
                'name' => $customer['first_name'] . ' ' . $customer['last_name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'address' => $customer['address'],
                'venue_id' => $venue->id,
            ]);

            // Create the order
            $order = Order::create([
                'total_amount' => $total_price,
                'subtotal' => $total_price,
                'customer_id' => $customer->id,
                'restaurant_id' => $venue->id,
                'status' => OrderStatus::NEW_ORDER,
                'payment_status' => $transactionId != null ? 'paid' : 'unpaid',
                'payment_method_id' => $transactionId != null ? PaymentMethod::CARD : PaymentMethod::CASH,
                'delivery_fee' => 0,
                'is_for_self' => 1,
            ]);

            $venuePrefix = strtoupper(substr($venue->name, 0, 2));
            $randomFourDigits = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

            $order->order_number = $venuePrefix . '-' . $order->id . $randomFourDigits;
            $order->save();

            foreach ($order_products as $productData) {
                $product = Product::find($productData['product_id']);

                if ($product && $product->inventories->count() > 0) {
                    $inventoryProduct = $product->inventories->first()->products()
                        ->where('product_id', $product->id)
                        ->first();

                    $inventoryProduct->pivot->quantity -= $productData['product_quantity'];
                    $inventoryProduct->pivot->save();

                    $activity = new InventoryActivity();
                    $activity->product_id = $inventoryProduct->id;
                    $activity->quantity = $productData['product_quantity'];
                    $activity->activity_category = InventoryActivity::ORDER_SALE;
                    $activity->activity_type = 'deduct';
                    $activity->inventory_id = $inventoryProduct->pivot->inventory_id;
                    $activity->order_id = $order->id;
                    $activity->save();

                    OrderProduct::create([
                        'order_id' => $order->id,
                        'product_id' => $productData['product_id'],
                        'product_quantity' => $productData['product_quantity'],
                        'product_total_price' => $product->price * $productData['product_quantity'],
                    ]);
                }
            }

            return ['status' => true, 'order' => $order];
        } catch (\Exception $e) {
            return ['status' => false, 'error' => $e->getMessage()];
        }
    }
}
