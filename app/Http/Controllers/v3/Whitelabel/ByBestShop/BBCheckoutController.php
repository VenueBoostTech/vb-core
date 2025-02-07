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
use App\Models\Cart;
use App\Models\PostalPricing;
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

class BBCheckoutController extends Controller
{
    protected $bktPaymentService;
    protected $inventoryService;
    public function __construct(BktPaymentService $bktPaymentService, InventoryService $inventoryService)
    {
        $this->bktPaymentService = $bktPaymentService;
        $this->inventoryService = $inventoryService;
    }

    public function quickCheckout(Request $request)
    {
        try {
            // Initialize required variables
            $total_order = 0;
            $total_order_eur = 0;
            $discount_amount = 0;
            $discount_amount_eur = 0;
            $currency_eur = Currency::where('currency_alpha', 'EUR')->first();
            $currency_all = Currency::where('currency_alpha', 'ALL')->first();

            $validator = Validator::make($request->all(), [
                'app_key' => 'required|string',
                'first_name' => 'required|string',
                'last_name' => 'nullable|string',
                'address' => 'required|string',
                'phone' => 'required|string',
                'email' => 'nullable|string',
                'country' => 'nullable|string',
                'city' => 'required|string',
                'payment_method' => 'required|string',
                'product_id' => 'required|integer',
                'language' => 'required|string',
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

//             // Add inventory check and update here
//            if (!$this->inventoryService->checkStockAvailability($product, 1)) {
//                throw new \Exception("Insufficient stock for product: {$product->title}");
//            }

            // Set default quantity to 1 for quick checkout
            // $product->quantity = 1;

            $payment_method = $request->input('payment_method');
            $customer = User::where('email', $request->email)->first();
            if ($payment_method == 'cash') {
                if($request->email) {
                    if(!$customer) {
                        $new_user = User::create([
                            'name' => $request->first_name . $request->last_name,
                            'email' => $request->email,
                            'password' => random_int(0, 899990000),
                            'first_name' => $request->first_name,
                            'last_name' => $request->last_name,
                            'country_code' => 'US',
                            'status' => 1,
                        ]);

                        $newCustomer = Customer::create([
                            'user_id' => $new_user->id,
                            'name' => $request->first_name . $request->last_name,
                            'email' => $request->email,
                            'phone' => $request->phone,
                            'address' => $request->address,
                        ]);

                        // $newCustomer->assignRole('client');

                        try {
                            $data_string = [
                                "crm_client_customer_id" => $new_user->id,
                                "source" => "bybest.shop_web",
                                "firstName" => $new_user->first_name,
                                "lastName" => $new_user->last_name,
                                "email" => $new_user->email,
                                "phone" => $newCustomer->phone,
                                "email_type" => "welcome"
                            ];
                            $response = Http::withHeaders([
                                "Content-Type" => "application/json",
                            ])->post('https://crmapi.pixelbreeze.xyz/api/create-crm-cart-session', $data_string);
                        } catch (\Exception $e) {
                            \Sentry\captureException($e);
                        }

                        $customer_id = $newCustomer->id;
                    } else {
                        $existingCustomer = Customer::where('user_id', $customer->id)->first();
                        $customer_id = $existingCustomer ? $existingCustomer->id : null;
                    }
                } else {
                    $temp_email = $this->unique_code(10).'@temp.com';

                    $new_user = User::create([
                        'name' => $request->first_name . $request->last_name,
                        'email' => $temp_email,
                        'password' => random_int(0, 899990000),
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'country_code' => 'AL',
                        'status' => 1,
                    ]);

                    $newCustomer = Customer::create([
                        'user_id' => $new_user->id,
                        'name' => $request->first_name . $request->last_name,
                        'email' => $temp_email,
                        'phone' => $request->phone,
                        'address' => $request->address,
                    ]);

                    try {
                        $data_string = [
                            "crm_client_customer_id" => $new_user->id,
                            "source" => "bybest.shop_web",
                            "firstName" => $new_user->first_name,
                            "lastName" => $new_user->last_name,
                            "email" => $new_user->email,
                            "phone" => $newCustomer->phone,
                            "email_type" => "welcome"
                        ];
                        $response = Http::withHeaders([
                            "Content-Type" => "application/json",
                        ])->post('https://crmapi.pixelbreeze.xyz/api/create-crm-cart-session', $data_string);
                    } catch (\Exception $e) {
                        \Sentry\captureException($e);
                    }

                    $customer_id = $newCustomer->id;
                }
                // TOOD: get IP
                $orders = Order::create([
                    'customer_id' => $customer_id,
                    'shipping_id' => 2,
                    'tracking_number' => 'undefined',
                    'status' => 1,
                    'payment_method_id' => 5,
                    'ip' => 'undefined',
                    'tracking_latitude' => 0,
                    'tracking_longtitude' => 0,
                    'tracking_countryCode' => 'undefined',
                    'tracking_cityName' => 'undefined',
                    'source_id' => 3,
                    'subtotal' => $total_order,
                    'discount' => abs($discount_amount),
                    'postal' => 0,
                    'total_amount' => (abs($total_order) - abs($discount_amount)),
                    'restaurant_id' => $venue->id,
                    'payment_status' => 'paid',
                    'total' => (abs($total_order) - abs($discount_amount)) + 0,
                    'total_eur' => (abs($total_order_eur) - abs($discount_amount_eur)) + 0,
                    'exchange_rate_eur' => 1,
                    'exchange_rate_all' => 1,
                    'shipping_name' => $request->first_name,
                    'shipping_surname' => $request->last_name,
                    'shipping_state' => $request->country,
                    'shipping_city' => $request->city,
                    'shipping_phone_no' => $request->phone,
                    'shipping_email' => $request->email,
                    'shipping_address' => $request->address,
                    'shipping_postal_code' => $request->zip ?? '0000',
                    'billing_name' => $request->first_name,
                    'billing_surname' => $request->last_name,
                    'billing_state' => $request->country,
                    'billing_city' => $request->city,
                    'billing_phone_no' => $request->phone,
                    'billing_email' => $request->email,
                    'billing_address' => $request->address,
                    'billing_postal_code' => $request->zip ?? '0000',
                    'coupon_id' => null,
                ]);

                $order_id = $orders->id;

                OrderProduct::create([
                    'order_id' => $order_id,
                    'product_id' => $product->id,
                    'product_quantity' => 1,
                    'product_total_price' => 20,
                    'product_discount_price' => 0,
                ]);

                // $this->inventoryService->decreaseStock($product, 1, $order_id);

                // Send webhook after successful order creation
                $this->sendOrderWebhook($orders, 'quick_checkout');

            } else {
                // Handle card payment
                if($request->email) {
                    $customer = User::where('email', $request->email)->first();
                    if(!$customer) {
                        $new_user = User::create([
                            'name' => $request->first_name . $request->last_name,
                            'email' => $request->email,
                            'password' => random_int(0, 899990000),
                            'first_name' => $request->first_name,
                            'last_name' => $request->last_name,
                            'country_code' => 'US',
                            'status' => 1,
                        ]);

                        $newCustomer = Customer::create([
                            'user_id' => $new_user->id,
                            'name' => $request->first_name . $request->last_name,
                            'email' => $request->email,
                            'phone' => $request->phone,
                            'address' => $request->address,
                        ]);

                        //$newCustomer->assignRole('client');
                        $customer_id = $newCustomer->id;
                    } else {
                        $existingCustomer = Customer::where('user_id', $customer->id)->first();
                        $customer_id = $existingCustomer ? $existingCustomer->id : null;
                    }
                } else {
                    $temp_email = $this->unique_code(10).'@temp.com';

                    $new_user = User::create([
                        'name' => $request->first_name . $request->last_name,
                        'email' => $temp_email,
                        'password' => random_int(0, 899990000),
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'country_code' => 'US',
                        'status' => 1,
                    ]);

                    $newCustomer = Customer::create([
                        'user_id' => $new_user->id,
                        'name' => $request->first_name . $request->last_name,
                        'email' => $temp_email,
                        'phone' => $request->phone,
                        'address' => $request->address,
                    ]);


                    $customer_id = $newCustomer->id;
                }

                // Pass the created customer to finalizeOrder
                $customerData = Customer::find($customer_id);
                $result = $this->finalizeOrder(
                    $venue,
                    $customerData,
                    [['product_id' => $product->id, 'product_quantity' => 1]],
                    20,
                    null
                );

                if (!$result['status']) {
                    return response()->json(['message' => $result['error']], 500);
                }
                $result_order = $result['order'];
                // $this->inventoryService->decreaseStock($product, 1, $result_order->id);
                $orderDetails = [
                    'id' => $result_order->id,
                    'total' => $result_order->total_amount,
                ];

                $paymentInfo = $this->bktPaymentService->initiatePayment($orderDetails);

                $this->sendOrderWebhook($result_order, 'quick_checkout');

                return response()->json([
                    'status' => 'success',
                    'payment_url' => $paymentInfo['url'],
                    'payment_data' => $paymentInfo['data']
                ]);
            }



            return response()->json(['message' => 'Order added successfully'], 200);

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
            // Initialize required variables
            $total_order = 0;
            $total_order_eur = 0;
            $discount_amount = 0;
            $discount_amount_eur = 0;
            $currency_eur = Currency::where('currency_alpha', 'EUR')->first();
            $currency_all = Currency::where('currency_alpha', 'ALL')->first();

            $validator = Validator::make($request->all(), [
                'app_key' => 'required|string',
                'first_name' => 'required|string',
                'last_name' => 'nullable|string',
                'address' => 'required|string',
                'phone' => 'required|string',
                'email' => 'nullable|string',
                'country' => 'required|string',
                'city' => 'required|string',
                'payment_method' => 'required|string',
                'token' => 'required_if:payment_method,paysera',
                'order_products' => 'required|array',
                'order_products.*.id' => 'required|integer',
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

            $order_products = array_map(function($item) {
                return [
                    'id' => $item['id'],
                    'product_id' => $item['id'],
                    'product_quantity' => $item['product_quantity']
                ];
            }, $request->input('order_products'));

            // Check if all products are available in stock
            // $outOfStockProducts = [];
            // foreach($order_products as $productData) {
            //     $product = Product::find($productData['id']);
            //     if (!$product) {
            //         return response()->json(['message' => 'Product not found: ' . $productData['id']], 404);
            //     }
            //     if (!$this->inventoryService->checkStockAvailability($product, $productData['product_quantity'])) {
            //         $outOfStockProducts[] = $product->title;
            //     }
            // }

            // if(count($outOfStockProducts) > 0) {
            //     return response()->json(['message' => 'Insufficient stock for products: ' . implode(', ', $outOfStockProducts)], 400);
            // }

            $total = $this->getProductsTotal($order_products);
            if ($total['status'] == false) {
                return response()->json(['message' => $total['error']], 400);
            }
            $total_price = $total['value'];

            $result_order = null;

            foreach($order_products as $productData) {
                $product = Product::find($productData['id']);
                if (!$product) {
                    return response()->json(['message' => 'Product not found: ' . $productData['id']], 404);
                }

                $product->quantity = $productData['product_quantity'];

                $product_discounted_subtotal = 0;
                $product_subtotal = 0;
                $product_price = 0;
                $product_discount = 0;

                $product_discounted_subtotal_eur = 0;
                $product_subtotal_eur = 0;
                $product_price_eur = 0;
                $product_discount_eur = 0;

                $sale_valid = false; // default value
                if ($product->attributes &&
                    isset($product->attributes->date_sale_start) &&
                    isset($product->attributes->date_sale_end)) {
                    $sale_valid = $this->checkIfSaleValidForProduct(
                        $product->attributes->date_sale_start,
                        $product->attributes->date_sale_end
                    );
                }
                $offer_valid = false;
                $coupon = session()->get('coupon');
                if($coupon) {
                    $sale_valid = false; // default value
                    if ($product->attributes &&
                        isset($product->attributes->date_sale_start) &&
                        isset($product->attributes->date_sale_end)) {
                        $sale_valid = $this->checkIfSaleValidForProduct(
                            $product->attributes->date_sale_start,
                            $product->attributes->date_sale_end
                        );
                    } }

                $has_applied_offer = false;
                $currencyProduct = 'EUR';
                if ($currencyProduct === 'EUR') {
                    if ($coupon && !$has_applied_offer && $offer_valid) {
                        if (!$sale_valid) {
                            if ($coupon->type_id == 1) {
                                $product_discount = ((float)$coupon->coupon_amount) / 100;
                                $product_subtotal = 10;
                                $product_discounted_subtotal = $product_subtotal - ($product_subtotal * $product_discount);
                                $discount_amount += $product_subtotal - $product_discounted_subtotal;
                                $total_order += $product_subtotal;
                            } else if ($coupon->type_id == 3) {
                                $product_subtotal = 10;
                                $product_discounted_subtotal = $product_subtotal;
                                $discount_amount += $product_subtotal - $product_discounted_subtotal;
                                $total_order += $product_subtotal;
                            }
                        } else {
                            $product_subtotal = 10;
                            $product_discount = (float)$product->attributes->sale_price / 100;
                            $product_discounted_subtotal = $product_subtotal - ($product_subtotal * $product_discount);
                            $total_order += $product_discounted_subtotal;
                        }
                    } else {
                        if (!$sale_valid) {
                            $total_order += 10;
                        } else {
                            $product_subtotal = 10;
                            $product_discount = (float)$product->attributes->sale_price / 100;
                            $product_discounted_subtotal = $product_subtotal - ($product_subtotal * $product_discount);
                            $total_order += $product_discounted_subtotal;
                        }
                    }
                } else {
                    if ($coupon && !$has_applied_offer && $offer_valid) {
                        if (!$sale_valid) {
                            if ($coupon->type_id == 1) {
                                $product_discount = ((float)$coupon->coupon_amount) / 100;
                                $product_subtotal = 10;
                                $product_discounted_subtotal = $product_subtotal - ($product_subtotal * $product_discount);
                                $discount_amount += $product_subtotal - $product_discounted_subtotal;
                                $total_order += $product_subtotal;
                            } else if ($coupon->type_id == 3) {
                                $product_subtotal = 10;
                                $product_discounted_subtotal = $product_subtotal;
                                $discount_amount += (float)$coupon->coupon_amount;
                                $total_order += $product_subtotal;
                            }
                        } else {
                            $product_subtotal = 10;
                            $product_discount = (float)$product->attributes->sale_price / 100;
                            $product_discounted_subtotal = $product_subtotal - ($product_subtotal * $product_discount);
                            $total_order += $product_discounted_subtotal;
                        }
                    } else {
                        if (!$sale_valid) {
                            $total_order += 10;
                        } else {
                            $product_subtotal = 10;
                            $product_discount = (float)$product->attributes->sale_price / 100;
                            $product_discounted_subtotal = $product_subtotal - ($product_subtotal * $product_discount);
                            $total_order += $product_discounted_subtotal;
                        }
                    }
                }

                $currencyProduct = 'LEK';
                if ($currencyProduct === 'LEK') {
                    if ($coupon && !$has_applied_offer && $offer_valid) {
                        if (!$sale_valid) {
                            if ($coupon->type_id == 1) {
                                $product_discount_eur = ((float)$coupon->coupon_amount) / 100;
                                $product_subtotal_eur = 10;
                                $product_discounted_subtotal_eur = $product_subtotal_eur - ($product_subtotal_eur * $product_discount_eur);
                                $discount_amount_eur += $product_subtotal_eur - $product_discounted_subtotal_eur;
                                $total_order_eur += $product_subtotal_eur;
                            } else if ($coupon->type_id == 3) {
                                $product_subtotal_eur = 10;
                                $product_discounted_subtotal_eur = $product_subtotal_eur;
                                $discount_amount_eur += $product_subtotal_eur - $product_discounted_subtotal_eur;
                                $total_order_eur += $product_subtotal_eur;
                            }
                        } else {
                            $product_subtotal_eur = 10;
                            $product_discount_eur = (float)$product->attributes->sale_price / 100;
                            $product_discounted_subtotal_eur = $product_subtotal_eur - ($product_subtotal_eur * $product_discount_eur);
                            $total_order_eur += $product_discounted_subtotal_eur;
                        }
                    } else {
                        if (!$sale_valid) {
                            $total_order_eur += 10;
                        } else {
                            $product_subtotal_eur = 10;
                            $product_discount_eur = (float)$product->attributes->sale_price / 100;
                            $product_discounted_subtotal_eur = $product_subtotal_eur - ($product_subtotal_eur * $product_discount_eur);
                            $total_order_eur += $product_discounted_subtotal_eur;
                        }
                    }
                } else {
                    if ($coupon && !$has_applied_offer && $offer_valid) {
                        if (!$sale_valid) {
                            if ($coupon->type_id == 1) {
                                $product_discount_eur = ((float)$coupon->coupon_amount) / 100;
                                $product_subtotal_eur = 10;
                                $product_discounted_subtotal_eur = $product_subtotal_eur - ($product_subtotal_eur * $product_discount_eur);
                                $discount_amount_eur += $product_subtotal_eur - $product_discounted_subtotal_eur;
                                $total_order_eur += $product_subtotal_eur;
                            } else if ($coupon->type_id == 3) {
                                $product_subtotal_eur = 10;
                                $product_discounted_subtotal_eur = $product_subtotal_eur;
                                $discount_amount_eur += $product_subtotal_eur - $product_discounted_subtotal_eur;
                                $total_order_eur += $product_subtotal_eur;
                            }
                        } else {
                            $product_subtotal_eur = 10;
                            $product_discount_eur = (float)$product->attributes->sale_price / 100;
                            $product_discounted_subtotal_eur = $product_subtotal_eur - ($product_subtotal_eur * $product_discount_eur);
                            $total_order_eur += $product_discounted_subtotal_eur;
                        }
                    } else {
                        if (!$sale_valid) {
                            $total_order_eur += 10;
                        } else {
                            $product_subtotal_eur = 10;
                            $product_discount_eur = (float)$product->attributes->sale_price / 100;
                            $product_discounted_subtotal_eur = $product_subtotal_eur - ($product_subtotal_eur * $product_discount_eur);
                            $total_order_eur += $product_discounted_subtotal_eur;
                        }
                    }
                }

                if ($coupon && $coupon->type_id == 3) {
                    $discount_amount_eur = $coupon->coupon_amount;
                    $discount_amount = $coupon->coupon_amount * $currency_all->exchange;
                }
            }

            if ($payment_method == 'cash') {
                $new_customer = null;
                if($request->email) {
                    $customer = User::where('email', $request->email)->first();
                    if(!$customer) {
                        $new_user = User::create([
                            'name' => $request->first_name . $request->last_name,
                            'email' => $request->email,
                            'password' => random_int(0, 899990000),
                            'first_name' => $request->first_name,
                            'last_name' => $request->last_name,
                            'country_code' => 'US',
                            'status' => 1,
                        ]);

                        // Krijo customer direkt, pa kërkuar të ekzistojë
                        $new_customer = Customer::create([
                            'user_id' => $new_user->id,  // Shto user_id në krijim
                            'name' => $request->first_name . $request->last_name,
                            'email' => $request->email,
                            'phone' => $request->phone,
                            'address' => $request->address,
                        ]);

                        if(!$new_customer) {
                            $newCustomer = Customer::create([
                                'name' => $request->first_name . $request->last_name,
                                'email' => $request->email,
                                'phone' => $request->phone,
                                'address' => $request->address,
                            ]);

                            // $newCustomer->assignRole('client');

                            try {
                                $data_string = [
                                    "crm_client_customer_id" => $new_user->id,
                                    "source" => "bybest.shop_web",
                                    "firstName" => $new_user->first_name,
                                    "lastName" => $new_user->last_name,
                                    "email" => $new_user->email,
                                    "phone" => $newCustomer->phone,
                                    "email_type" => "welcome"
                                ];
                                $response = Http::withHeaders([
                                    "Content-Type" => "application/json",
                                ])->post('https://crmapi.pixelbreeze.xyz/api/create-crm-cart-session', $data_string);
                            } catch (\Exception $e) {
                                \Sentry\captureException($e);
                            }
                        }
                    } else {
                        $new_customer = Customer::where('email', $request->email)->first();
                        if(!$new_customer) {
                            $newCustomer = Customer::create([
                                'name' => $request->first_name . $request->last_name,
                                'email' => $request->email,
                                'phone' => $request->phone,
                                'address' => $request->address,
                            ]);

                            // $newCustomer->assignRole('client');

                            try {
                                $data_string = [
                                    "crm_client_customer_id" => $new_user->id,
                                    "source" => "bybest.shop_web",
                                    "firstName" => $new_user->first_name,
                                    "lastName" => $new_user->last_name,
                                    "email" => $new_user->email,
                                    "phone" => $newCustomer->phone,
                                    "email_type" => "welcome"
                                ];
                                $response = Http::withHeaders([
                                    "Content-Type" => "application/json",
                                ])->post('https://crmapi.pixelbreeze.xyz/api/create-crm-cart-session', $data_string);
                            } catch (\Exception $e) {
                                \Sentry\captureException($e);
                            }
                        }
                    }
                } else {
                    $temp_email = $this->unique_code(10).'@temp.com';

                    $new_user = User::create([
                        'name' => $request->first_name . $request->last_name,
                        'email' => $request->email,
                        'password' => random_int(0, 899990000),
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'country_code' => 'US',
                        'status' => 1,
                    ]);


                                        $new_customer = Customer::create([
                                            'user_id' => $new_user->id,
                                            'name' => $request->first_name . $request->last_name,
                                            'email' => $request->email,
                                            'phone' => $request->phone,
                                            'address' => $request->address,
                                        ]);
                                        if(!$new_customer) {
                                            $newCustomer = Customer::create([
                                                'name' => $request->first_name . $request->last_name,
                                                'email' => $request->email,
                                                'phone' => $request->phone,
                                                'address' => $request->address,
                                            ]);

                                            // $newCustomer->assignRole('client');

                                            try {
                                                $data_string = [
                                                    "crm_client_customer_id" => $new_user->id,
                                                    "source" => "bybest.shop_web",
                                                    "firstName" => $new_user->first_name,
                                                    "lastName" => $new_user->last_name,
                                                    "email" => $new_user->email,
                                                    "phone" => $newCustomer->phone,
                                                    "email_type" => "welcome"
                                                ];
                                                $response = Http::withHeaders([
                                                    "Content-Type" => "application/json",
                                                ])->post('https://crmapi.pixelbreeze.xyz/api/create-crm-cart-session', $data_string);
                                            } catch (\Exception $e) {
                                                \Sentry\captureException($e);
                                            }
                                        }
                                    }

                $postal_price = PostalPricing::where('city_id', $request->city)
                    ->join('postals', 'postals.id', '=', 'postal_pricing.postal_id')
                    ->where('postal_id', '2')->first();



                $orders = Order::create([
                    'customer_id' => $new_customer->id,
                    'shipping_id' => 2,
                    'tracking_number' => 'undefined',
                    'status' => 'new',
                    'payment_method_id' => 5,
                    'ip' => 'undefined',
                    'tracking_latitude' => 0,
                    'tracking_longtitude' => 0,
                    'tracking_countryCode' => 'undefined',
                    'tracking_cityName' => 'undefined',
                    'source_id' => 3,
                    'subtotal' => $total_order,
                    'discount' => abs($discount_amount),
                    'postal' => 0,
                    'total_amount' => (abs($total_order) - abs($discount_amount)),
                    'restaurant_id' => $venue->id,
                    'payment_status' => 'paid',
                    'total' => (abs($total_order) - abs($discount_amount)) + 0,
                    'total_eur' => (abs($total_order_eur) - abs($discount_amount_eur)) + 0,
                    'exchange_rate_eur' => 1,
                    'exchange_rate_all' => 1,
                    'shipping_name' => $request->first_name,
                    'shipping_surname' => $request->last_name,
                    'shipping_state' => $request->country,
                    'shipping_city' => $request->city,
                    'shipping_phone_no' => $request->phone,
                    'shipping_email' => $request->email,
                    'shipping_address' => $request->address,
                    'shipping_postal_code' => $request->zip ?? '0000',
                    'billing_name' => $request->first_name,
                    'billing_surname' => $request->last_name,
                    'billing_state' => $request->country,
                    'billing_city' => $request->city,
                    'billing_phone_no' => $request->phone,
                    'billing_email' => $request->email,
                    'billing_address' => $request->address,
                    'billing_postal_code' => $request->zip ?? '0000',
                    'coupon_id' => $coupon ? $coupon->id : null,
                ]);
                $venuePrefix = strtoupper(substr($venue->name, 0, 2));
                $randomFourDigits = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

                $orders->order_number = $venuePrefix . '-' . $orders->id . $randomFourDigits;
                $orders->save();
                $order_id = $orders->id;

                foreach ($order_products as $productData) {
                    $product = Product::where('id', $productData['id'])->first();
                    if (!$product) continue;

                    $sale_valid = false;
                    if ($product->attributes &&
                        isset($product->attributes->date_sale_start) &&
                        isset($product->attributes->date_sale_end)) {
                        $temp_date_start = new DateTime($product->attributes->date_sale_start);
                        $temp_date_end = new DateTime($product->attributes->date_sale_end);
                        $temp_date_now = new DateTime();
                        $sale_valid = $temp_date_now > $temp_date_start && $temp_date_now < $temp_date_end;
                    }

                    OrderProduct::create([
                        'order_id' => $order_id,
                        'product_id' => $product->id,
//                        'variation_id' => $product->attributes->variation,
                        'product_quantity' => $productData['product_quantity'],
                        'product_total_price' => 20,
                        'product_discount_price' => 0,
                    ]);

                    // $this->inventoryService->decreaseStock($product, $productData['product_quantity'], $order_id);



                }
            } else {
                // Handle other payment methods
                $result = $this->finalizeOrder($venue, $customer, $order_products, $total_price, null);

                if (!$result['status']) {
                    return response()->json(['message' => $result['error']], 500);
                }
                $result_order = $result['order'];

                $orderDetails = [
                    'id' => $result_order->id,
                    'total' => $result_order->total_amount,
                ];

                $paymentInfo = $this->bktPaymentService->initiatePayment($orderDetails);
                // foreach($order_products as $productData) {
                //     $product = Product::where('id', $productData['id'])->first();
                //     if ($product) {
                //         $this->inventoryService->decreaseStock($product, $productData['product_quantity'], $result_order->id);
                //     }
                // }
                return response()->json([
                    'status' => 'success',
                    'payment_url' => $paymentInfo['url'],
                    'payment_data' => $paymentInfo['data']
                ]);
            }


            // if ($venue->email) {
            //     Mail::to($venue->email)->send(new NewOrderEmail($venue->name));
            // }


            $this->sendOrderWebhook($result_order, 'regular_checkout');
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

            if ($product->inventories->count() > 0) {
                $inventoryProduct = $product->inventories->first()->products()
                    ->where('product_id', $product->id)
                    ->first();

                if (!$inventoryProduct || $inventoryProduct->pivot->quantity < $productData['product_quantity']) {
                    return [
                        'status' => false,
                        'error' => 'Insufficient quantity in inventory for product: ' . $product->title
                    ];
                }
            }

            $total += $product->price * $productData['product_quantity'];
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

                    // $this->inventoryService->decreaseStock($product, $productData['product_quantity'], $order->id);
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

    public function index(Request $request): JsonResponse
    {
        $app_key = $request->input('app_key');
        $venue = Restaurant::where('app_key', $app_key)->first();
        if (!$venue) {
            return response()->json(['message' => 'Venue not found'], 400);
        }

        $postals = $venue->postals()->get()->map(function ($postal) {
            return [
                'id' => $postal->id,
                'type' => $postal->type,
                'status' => $postal->status,
                'title' => $postal->title,
                'name' => $postal->name,
                'logo' => $postal->logo,
                'description' => $postal->description,
            ];
        });

        return response()->json(['data' => $postals], 200);
    }

    public function pricing(Request $request)
    {
        $app_key = $request->input('venue_app_key');
        $venue = Restaurant::where('app_key', $app_key)->first();
        if (!$venue) {
            return response()->json(['message' => 'Venue not found'], 404);
        }

        $postalIds = $venue->postals()->pluck('id');

        $pricing = PostalPricing::whereIn('postal_id', $postalIds)
            ->with(['postal', 'city'])
            ->get()
            ->map(function ($price) {
                return [
                    'id' => $price->id,
                    'price' => $price->price,
                    'price_without_tax' => $price->price_without_tax,
                    'city' => $price->city->name,
                    'postal' => $price->postal->name,
                    'type' => $price->type,
                    'alpha_id' => $price->alpha_id,
                    'alpha_description' => $price->alpha_description,
                    'notes' => $price->notes,
                ];
            });

        return response()->json(['data' => $pricing], 200);
    }

    /**
     * Send webhook notification for new order
     */
    private function mapOrderStatus($status)
    {
        $statusMap = [
            1 => 'new',
            2 => 'processing',
            3 => 'completed',
            4 => 'refunded',
            5 => 'cancelled'
        ];
        return $statusMap[$status] ?? 'new';
    }

    private function sendOrderWebhook(Order $order, $checkoutType = 'regular_checkout')
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
                'status' => $this->mapOrderStatus($order->status),
                'payment_method_id' => $order->payment_method_id,
                'payment_status' => $order->payment_status,
                'stripe_payment_id' => $order->payment['transactionId'] ?? null,
                'payment_metadata' => $order->payment ?? [],
                'source_type' => $checkoutType,
                'source_url' => 'https://metroshop.al',
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

                // Order products
                'order_products' => $order->orderProducts->map(function($orderProduct) {
                    return [
                        'product_id' => $orderProduct->product_id,
                        'product_name' => $orderProduct->product->title ?? 'Unknown Product',
                        'product_quantity' => $orderProduct->product_quantity,
                        'product_total_price' => $orderProduct->product_total_price
                    ];
                })->toArray()
            ];


            // Get venue's webhook URL and construct full endpoint
            $webhookUrl = rtrim(env('OMNISTACK_GATEWAY_BASEURL'), '/') . '/webhooks/orders/' . $venue->short_code;

            // Send webhook
            $response = Http::withHeaders([
                'webhook-api-key' => env('OMNISTACK_GATEWAY_MSHOP_API_KEY'),
                'x-api-key' => env('OMNISTACK_GATEWAY_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post($webhookUrl, $webhookData);

            if (!$response->successful()) {
                \Log::error('Webhook failed for order ' . $order->id, [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Error sending webhook for order ' . $order->id, [
                'error' => $e->getMessage()
            ]);
        }
    }

}
