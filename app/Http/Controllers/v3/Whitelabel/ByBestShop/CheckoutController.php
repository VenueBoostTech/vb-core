<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Models\AccountingFinance\Currency;
use App\Models\VbStoreProductAttribute;
use App\Models\VbStoreProductVariant;
use App\Models\VbStoreProductVariantAttribute;
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

                $product_details = VbStoreProductVariant::with('product')
                ->select(
                    'sale_price',
                    'price',
                    'stock_quantity',
                    'currency_alpha',
                    'bb_points',
                    'date_sale_start',
                    'date_sale_end',
                )
                ->where('id', $request->variation_id_cart)
                ->first();

                $attributes = VbStoreProductVariantAttribute::with(['attributeOption.attribute'])
                ->where('variant_id', $request->variation_id_cart)
                ->get()
                ->map(function ($attribute) use ($locale) {
                    return [
                        'attr_name' => DB::raw("JSON_UNQUOTE(JSON_EXTRACT({$attribute->attributeOption->attribute->attr_name}, '$.{$locale}'))"),
                        'option_name' => DB::raw("JSON_UNQUOTE(JSON_EXTRACT({$attribute->attributeOption->option_name}, '$.{$locale}'))"),
                    ];
                });
            } else {

                $product_details = Product::where('id', $product->id)->first();

                $attributes = VbStoreProductAttribute::join('vb_store_attributes_options', 'vb_store_attributes_options.id', '=', 'vb_store_product_attributes.attribute_id')
                    ->join('vb_store_attributes', 'vb_store_attributes.id', '=', 'vb_store_attributes_options.attribute_id')
                    ->select(
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(vb_store_attributes.attr_name, '$." . $locale . "')) AS attr_name"),
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(vb_store_attributes_options.option_name, '$." . $locale . "')) AS option_name"),
                    )
                    ->where('vb_store_product_attributes.product_id', '=', $product->id)
                    ->get();
            }

//            Cart::clear();
//
//            Cart::create([
//                'id' => $request->product_id,
//                'name' => $product->product_name,
//                'price' => $product_details->regular_price,
//                'quantity' => 1,
//                'attributes' => array(
//                    'image' => $product_details->product_image,
//                    'url' => $product_details->product_url,
//                    'description' => $product->product_short_description,
//                    'variation' => $request->variation_id_cart,
//                    'product_no' => $request->product_no,
//                    'total_stock' => $product_details->stock_quantity,
//                    'currency_alpha' => $product_details->currency_alpha,
//                    'bb_points' => $product_details->bb_points,
//                    'exchange_rate' => $exchange_rate,
//                    'date_sale_start' => $product_details->date_sale_start,
//                    'date_sale_end' => $product_details->date_sale_end,
//                    'sale_price' => $product_details->sale_price,
//                    'regular_price' => $product_details->regular_price,
//                    'brand_id' => $product->brand_id,
//                    'attributes' => $attributes
//                )
//            ]);

            $product_discounted_subtotal = 0;
            $product_subtotal = 0;
            $product_price = 0;
            $product_discount = 0;

            $product_discounted_subtotal_eur = 0;
            $product_subtotal_eur = 0;
            $product_price_eur = 0;
            $product_discount_eur = 0;

            $sale_valid = $this->checkIfSaleValidForProduct($product->attributes->date_sale_start, $product->attributes->date_sale_end);
            $offer_valid = false;
            $coupon = $coupon = session()->get('coupon');
            if($coupon) {
                $offer_valid = $this->checkIfOfferValidForProduct($coupon->id, $product->attributes->brand_id, $product->id);
            }

            $currencyProduct = 'EUR';
            if ($currencyProduct === 'EUR') {
                if ($coupon && !$has_applied_offer && $offer_valid) {
                    if (!$sale_valid) {
                        if ($coupon->type_id == 1) {
                            $product_discount = ((float)$coupon->coupon_amount) / 100;
                            $product_subtotal = ($product->attributes->regular_price * $product->quantity) * $currency_all->exchange;
                            $product_discounted_subtotal = $product_subtotal - ($product_subtotal * $product_discount);
                            $discount_amount += $product_subtotal - $product_discounted_subtotal;
                            $total_order += $product_subtotal;
                        } else if ($coupon->type_id == 3) {
                            $product_subtotal = ($product->attributes->regular_price * $product->quantity) * $currency_all->exchange;
                            $product_discounted_subtotal = $product_subtotal;
                            $discount_amount += $product_subtotal - $product_discounted_subtotal;
                            $total_order += $product_subtotal;
                        }
                    } else {
                        $product_subtotal = ($product->attributes->regular_price * $product->quantity) * $currency_all->exchange;
                        $product_discount = (float)$product->attributes->sale_price / 100;
                        $product_discounted_subtotal = $product_subtotal - ($product_subtotal * $product_discount);
                        $total_order += $product_discounted_subtotal;
                    }
                } else {
                    if (!$sale_valid) {
                        $total_order += ($product->attributes->regular_price * $product->quantity) * $currency_all->exchange;
                    } else {
                        $product_subtotal = ($product->attributes->regular_price * $product->quantity) * $currency_all->exchange;
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
                            $product_subtotal = ($product->attributes->regular_price * $product->quantity);
                            $product_discounted_subtotal = $product_subtotal - ($product_subtotal * $product_discount);
                            $discount_amount += $product_subtotal - $product_discounted_subtotal;
                            $total_order += $product_subtotal;
                        } else if ($coupon->type_id == 3) {
                            $product_subtotal = ($product->attributes->regular_price * $product->quantity);
                            $product_discounted_subtotal = $product_subtotal;
                            $discount_amount += (float)$coupon->coupon_amount;
                            $total_order += $product_subtotal;
                        }


                    } else {
                        $product_subtotal = ($product->attributes->regular_price * $product->quantity);
                        $product_discount = (float)$product->attributes->sale_price / 100;
                        $product_discounted_subtotal = $product_subtotal - ($product_subtotal * $product_discount);
                        $total_order += $product_discounted_subtotal;
                    }
                } else {
                    if (!$sale_valid) {
                        $total_order += $product->attributes->regular_price * $product->quantity;
                    } else {
                        $product_subtotal = ($product->attributes->regular_price * $product->quantity);
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
                            $product_subtotal_eur = ($product->attributes->regular_price * $product->quantity) * $currency_eur->exchange;
                            $product_discounted_subtotal_eur = $product_subtotal_eur - ($product_subtotal_eur * $product_discount_eur);
                            $discount_amount_eur += $product_subtotal_eur - $product_discounted_subtotal_eur;
                            $total_order_eur += $product_subtotal_eur;
                        } else if ($coupon->type_id == 3) {
                            $product_subtotal_eur = ($product->attributes->regular_price * $product->quantity) * $currency_eur->exchange;
                            $product_discounted_subtotal_eur = $product_subtotal_eur;
                            $discount_amount_eur += $product_subtotal_eur - $product_discounted_subtotal_eur;
                            $total_order_eur += $product_subtotal_eur;
                        }

                    } else {
                        $product_subtotal_eur = ($product->attributes->regular_price * $product->quantity) * $currency_eur->exchange;
                        $product_discount_eur = (float)$product->attributes->sale_price / 100;
                        $product_discounted_subtotal_eur = $product_subtotal_eur - ($product_subtotal_eur * $product_discount_eur);
                        $total_order_eur += $product_discounted_subtotal_eur;
                    }
                } else {
                    if (!$sale_valid) {
                        $total_order_eur += ($product->attributes->regular_price * $product->quantity) * $currency_eur->exchange;
                    } else {
                        $product_subtotal_eur = ($product->attributes->regular_price * $product->quantity) * $currency_eur->exchange;
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
                            $product_subtotal_eur = ($product->attributes->regular_price * $product->quantity);
                            $product_discounted_subtotal_eur = $product_subtotal_eur - ($product_subtotal_eur * $product_discount_eur);
                            $discount_amount_eur += $product_subtotal_eur - $product_discounted_subtotal_eur;
                            $total_order_eur += $product_subtotal_eur;
                        } else if ($coupon->type_id == 3) {
                            $product_subtotal_eur = ($product->attributes->regular_price * $product->quantity);
                            $product_discounted_subtotal_eur = $product_subtotal_eur;
                            $discount_amount_eur += $product_subtotal_eur - $product_discounted_subtotal_eur;
                            $total_order_eur += $product_subtotal_eur;
                        }

                    } else {
                        $product_subtotal_eur = 20;
                        $product_discount_eur = 0;
                        $product_discounted_subtotal_eur = $product_subtotal_eur - ($product_subtotal_eur * $product_discount_eur);
                        $total_order_eur += $product_discounted_subtotal_eur;
                    }
                } else {
                    if (!$sale_valid) {
                        $total_order_eur += 20;
                    } else {
                        $product_subtotal_eur = 20;
                        $product_discount_eur = 0;
                        $product_discounted_subtotal_eur = $product_subtotal_eur - ($product_subtotal_eur * $product_discount_eur);
                        $total_order_eur += $product_discounted_subtotal_eur;
                    }
                }
            }

            if ($coupon && $coupon->type_id == 3) {
                $discount_amount_eur = $coupon->coupon_amount;
                $discount_amount = $coupon->coupon_amount * $currency_all->exchange;
            }

            // Calculate total order and discounts
            $sale_valid = $this->checkIfSaleValidForProduct($product_details->date_sale_start, $product_details->date_sale_end);

            $currencyProduct = 'EUR';
            if ($currencyProduct === 'EUR') {
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

                        $newCustomer->assignRole('client');

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
                        $customer_id = Customer::where('user_id', $customer->id)->first()->id;
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

                    $newCustomer->assignRole('client');

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

                $postal_price = PostalPricing::where('city_id', $request->order_city)
                    ->join('postals', 'postals.id', '=', 'postal_pricing.postal_id')
                    ->where('postal_id', '2')->first();

                $USERT = User::where('email', 'griseldgituser@gmail.com')->first();
                $orders = Order::create([
                    'customer_id' => Customer::where('user_id', $USERT->id)->first()->id,
                    'shipping_id' => 2,
                    'tracking_number' => $order_tracking_code,
                    'status' => 1,
                    'payment_method_id' => 5,
                    'ip' => $location ? $location->ip : 'undefined',
                    'tracking_latitude' => $location ? $location->latitude : 0,
                    'tracking_longtitude' => $location ? $location->longitude : 0,
                    'tracking_countryCode' => $location ? $location->countryCode : 'undefined',
                    'tracking_cityName' => $location ? $location->cityName : 'undefined',
                    'source_id' => 3,
                    'subtotal' => $total_order,
                    'discount' => abs($discount_amount),
                    'postal' => $postal_price->price,
                    'total' => (abs($total_order) - abs($discount_amount)) + $postal_price->price,
                    'total_eur' => (abs($total_order_eur) - abs($discount_amount_eur)) + ($postal_price->price * $currency_eur->exchange),
                    'exchange_rate_eur' => (float)$currency_eur->exchange,
                    'exchange_rate_all' => (float)$currency_all->exchange,
                    'shipping_name' => $request->first_name ? $request->first_name : $customer->name,
                    'shipping_surname' => $request->last_name ? $request->last_name : $customer->surname,
                    'shipping_state' => $request->country,
                    'shipping_city' => $request->order_city ? $request->order_city : 140,
                    'shipping_phone_no' => $request->phone_number ? $request->phone_number : $customer->phone_number,
                    'shipping_email' => $request->email_address ? $request->email_address : $customer->email,
                    'shipping_address' => $request->address_details,
                    'shipping_postal_code' => $request->order_zip ? $request->order_zip : '0000',
                    'billing_name' => $request->first_name ? $request->first_name : $customer->name,
                    'billing_surname' => $request->last_name ? $request->last_name : $customer->surname,
                    'billing_state' => $request->country ? $request->country : 5,
                    'billing_city' => $request->order_city ? $request->order_city : 140,
                    'billing_phone_no' => $request->phone_number ? $request->phone_number : $customer->phone_number,
                    'billing_email' => $request->email_address ? $request->email_address : $customer->email,
                    'billing_address' => $request->address_details,
                    'billing_postal_code' => $request->order_zip ? $request->order_zip : '0000',
                    'coupon_id' => $coupon ? $coupon->id : '',
                ]);

                $order_id = $orders->id;

                $sale_valid = false;
                if ($product->attributes &&
                    isset($product->attributes->date_sale_start) &&
                    isset($product->attributes->date_sale_end)) {
                    $temp_date_start = new DateTime($product->attributes->date_sale_start);
                    $temp_date_end = new DateTime($product->attributes->date_sale_end);
                    $temp_date_now = new DateTime();
                    $sale_valid = $temp_date_now > $temp_date_start && $temp_date_now < $temp_date_end;
                }
//                $is_higher = $temp_date_now > $temp_date_start;
//                $is_lower = $temp_date_now < $temp_date_end;
//                $sale_valid = $is_higher && $is_lower;

                OrderProduct::create([
                    'order_id' => $order_id,
                    'product_id' => $product->id,
                    'variation_id' => $product->attributes->variation,
                    'product_quantity' => $product->quantity,
                    'product_total_price' => 10,
                    'product_discount_price' => 0,
                ]);
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

                            $newCustomer->assignRole('client');

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

                        $newCustomer->assignRole('client');

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

//                $postal_price = PostalPricing::where('city_id', $request->city)
//                    ->join('postals', 'postals.id', '=', 'postal_pricing.postal_id')
//                    ->where('postal_id', '2')->first();
//
//                if (!$postal_price) {
//                    return response()->json(['message' => 'Postal pricing not found'], 404);
//                }

                $USERT = User::where('email', 'griseldgituser@gmail.com')->first();
                $orders = Order::create([
                    'customer_id' => Customer::where('user_id', $USERT->id)->first()->id,
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
                    'coupon_id' => $coupon ? $coupon->id : null,
                ]);

                $order_id = $orders->id;

                foreach ($order_products as $productData) {
                    $product = Product::find($productData['id']);
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

                return response()->json([
                    'status' => 'success',
                    'payment_url' => $paymentInfo['url'],
                    'payment_data' => $paymentInfo['data']
                ]);
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
        $app_key = $request->input('app_key');
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
}
