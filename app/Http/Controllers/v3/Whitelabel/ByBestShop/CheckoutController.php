<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Models\AccountingFinance\Currency;
use App\Models\VbStoreProductAttribute;
use App\Services\BktPaymentService;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
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
use App\Http\Controllers\v3\Whitelabel\ByBestShop\PaymentController;
use App\Models\Restaurant;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Mail\CustomerOrderConfirmationEmail;
use App\Mail\NewOrderEmail;
use Stevebauman\Location\Facades\Location;

class CheckoutController extends Controller
{
    protected $bktPaymentService;

    public function __construct(BktPaymentService $bktPaymentService)
    {
        $this->bktPaymentService = $bktPaymentService;
    }


    public function quickCheckout(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'app_key' => 'required|string',
                'first_name' => 'required|string',
                'last_name' => 'nullable|string',
                'address_details' => 'required|string',
                'phone_number' => 'required|string',
                'email' => 'nullable|string',
                'country' => 'required|integer',
                'order_city' => 'required|integer',
                'product_id' => 'required|integer',
                'language' => 'required|string',
                'payment_method' => 'required|string', // Add this to handle different payment methods

            ]);

            if ($validator->fails()) {
                return response()->json(['message' => $validator->errors()], 400);
            }

            $app_key = $request->input('app_key');
            $venue = Restaurant::where('app_key', $app_key)->first();
            if (!$venue) {
                return response()->json(['message' => 'Venue not found'], 404);
            }

            $product = Product::where('id', $request->product_id)->where('restaurant_id', $venue->id)->first();

            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            
            $currency_all = Currency::where('currency_alpha', '=', 'LEK')->first();
            $currency_eur = Currency::where('currency_alpha', '=', 'EUR')->first();
            $location = Location::get($request->ip());
            $order_tracking_code = strtoupper('BB'.$this->unique_code(10));
            $has_applied_offer = false;

            $discount_amount = 0;
            $total_order = 0;
            $discount_amount_eur = 0;
            $total_order_eur = 0;

            $locale = $request->input('language');

            if ($request->variation_id_cart != null) {

                $product_details = DB::table('store_products_variants')
                    ->rightJoin('store_products', 'store_products.id', '=', 'store_products_variants.product_id')
                    ->select(
                        'store_products_variants.sale_price',
                        'store_products_variants.price',
                        'store_products_variants.stock_quantity',
                        'store_products_variants.currency_alpha',
                        'store_products_variants.bb_points',
                        'store_products_variants.date_sale_start',
                        'store_products_variants.date_sale_end',
                        'store_products.product_image',
                        'store_products.product_url',
                        'store_products.product_url',
                        'store_products.product_short_description',
                    )
                    ->where('store_products_variants.id', '=', $request->variation_id_cart)->first();

                $attributes = DB::table('store_product_variant_at5ributes')
                    ->join('store_attributes_options', 'store_attributes_options.id', '=', 'store_product_variant_atributes.atribute_id')
                    ->join('store_attributes', 'store_attributes.id', '=', 'store_attributes_options.attribute_id')
                    ->select(
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(store_attributes.attr_name, '$." . App::getLocale() . "')) AS attr_name"),
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(store_attributes_options.option_name, '$." . App::getLocale() . "')) AS option_name"),
                    )
                    ->where('store_product_variant_attributes.variant_id', '=', $request->variation_id_cart)
                    ->get();
            } else {

                $product_details = DB::table('products')
                    ->select('products.*')
                    ->where('products.id', '=', $product->id)->first();

                $attributes = VbStoreProductAttribute::join('vb_store_attributes_options', 'vb_store_attributes_options.id', '=', 'vb_store_product_attributes.attribute_id')
                    ->join('vb_store_attributes', 'vb_store_attributes.id', '=', 'vb_store_attributes_options.attribute_id')
                    ->select(
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(vb_store_attributes.attr_name, '$." . $locale . "')) AS attr_name"),
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(vb_store_attributes_options.option_name, '$." . $locale . "')) AS option_name"),
                    )
                    ->where('vb_store_product_attributes.product_id', '=', $product->id)
                    ->get();
            }

            // Calculate total order and discounts
            $sale_valid = $this->checkIfSaleValidForProduct($product_details->date_sale_start, $product_details->date_sale_end);

            if ($product_details->currency_alpha === 'EUR') {
                if (!$sale_valid) {
                    $total_order += ($product_details->price) * $currency_all->exchange;
                    $total_order_eur += $product_details->price;
                } else {
                    $product_subtotal = ($product_details->price) * $currency_all->exchange;
                    $product_discount = (float)$product_details->sale_price / 100;
                    $product_discounted_subtotal = $product_subtotal - ($product_subtotal * $product_discount);
                    $total_order += $product_discounted_subtotal;
                    $total_order_eur += ($product_details->price - ($product_details->price * $product_discount));
                }
            } else {
                if (!$sale_valid) {
                    $total_order += $product_details->price;
                    $total_order_eur += $product_details->price / $currency_eur->exchange_rate;
                } else {
                    $product_subtotal = $product_details->price;
                    $product_discount = (float)$product_details->sale_price / 100;
                    $product_discounted_subtotal = $product_subtotal - ($product_subtotal * $product_discount);
                    $total_order += $product_discounted_subtotal;
                    $total_order_eur += $product_discounted_subtotal / $currency_eur->exchange_rate;
                }
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
            // Handle payment via paysera
            if ($payment_method == 'cash') {
              //
            } else {
                // Handle other payment methods like cash
                $result = $this->finalizeOrder($venue, $customer, $order_products, $total_price, null);

                if (!$result['status']) {
                    return response()->json(['message' => $result['error']], 500);
                }
                $result_order = $result['order'];

                $orderDetails = [
                    'id' => $result_order->id,
                    'total' => $result_order->total_amount, // Make sure this matches the property name in your Order model
                    // Add any other necessary details here
                ];

                $paymentInfo = $this->bktPaymentService->initiatePayment($orderDetails);

                return response()->json([
                    'status' => 'success',
                    'payment_url' => $paymentInfo['url'],
                    'payment_data' => $paymentInfo['data']
                ]);
            }

            // if ($venue->email) {
            //     Mail::to($venue->email)->send(new NewOrderEmail($venue->name));
            // }

//            return response()->json(['message' => 'Order added successfully', 'order' => $result_order], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @throws \Exception
     */
    private function checkIfSaleValidForProduct($date_sale_start, $date_sale_end): bool
    {
        $temp_date_start = new DateTime($date_sale_start);
        $temp_date_end = new DateTime($date_sale_end);
        $temp_date_now = new DateTime();
        return $temp_date_now > $temp_date_start && $temp_date_now < $temp_date_end;
    }

    private function unique_code($limit): string
    {
        return substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, $limit);
    }

    public function checkout(Request $request)
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
                'token' => 'required_if:payment_method,paysera', // Card token for paysera
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
            // Handle payment via paysera
            if ($payment_method == 'cash') {
                //
            } else {
                // Handle other payment methods like cash
                $result = $this->finalizeOrder($venue, $customer, $order_products, $total_price, null);

                if (!$result['status']) {
                    return response()->json(['message' => $result['error']], 500);
                }
                $result_order = $result['order'];

                $orderDetails = [
                    'id' => $result_order->id,
                    'total' => $result_order->total_amount, // Make sure this matches the property name in your Order model
                    // Add any other necessary details here
                ];

                $paymentInfo = $this->bktPaymentService->initiatePayment($orderDetails);

                return response()->json([
                    'status' => 'success',
                    'payment_url' => $paymentInfo['url'],
                    'payment_data' => $paymentInfo['data']
                ]);
            }

            // if ($venue->email) {
            //     Mail::to($venue->email)->send(new NewOrderEmail($venue->name));
            // }

//            return response()->json(['message' => 'Order added successfully', 'order' => $result_order], 200);
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
            $customer = Customer::where('email', $customer['email'])->first();

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

    public function testCheckout(Request $request)
    {
        // do nothing
    }
}
