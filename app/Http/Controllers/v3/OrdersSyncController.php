<?php

namespace App\Http\Controllers\v3;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\BBLegacyCoupon;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Order;
use App\Models\OrderSource;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\VbStoreProductVariant;
use App\Models\User;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Promise;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Jobs\UploadCollectionPhotoJob;
use App\Jobs\UploadPhotoJob;


class OrdersSyncController extends Controller
{
    private $bybestApiUrl = 'https://bybest.shop/api/V1/';
    private $bybestApiKey = 'crm.pixelbreeze.xyz-dbz';

    private $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function couponsSync(Request $request)
    {
        $venue = $this->venueService->adminAuthCheck();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;

        do {
            try {
                $start = microtime(true);
                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'coupons-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    break; // No more data to process
                }

                $coupons = $bybestData['data'];


                foreach (array_chunk($coupons, $batchSize) as $batch) {
                    DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($batch as $item) {
                            \Log::info('Processing coupons', ['item' => $item]);

                            // Make sure the required fields are available
                            if (!isset($item['id'])) {
                                \Log::error('coupons missing id', ['item' => $item]);
                                $skippedCount++;
                                continue;
                            }



                            BBLegacyCoupon::updateOrCreate(
                                ['bybest_id' => $item['id']],
                                [
                                    'coupon_code' => $item['coupon'],
                                    'coupon_amount' => $item['coupon_amount'],
                                    'data' => json_encode($item),
                                    'bybest_id' => $item['id'],
                                ]
                            );

                            $processedCount++;
                        }
                    });
                }

                \Log::info("Processed {$processedCount} coupons so far.");

                $page++;
            } catch (\Throwable $th) {

                \Log::error('Error in coupons sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
                 return response()->json([
                     "message" => "Error in coupons sync",
                     "error" => $th->getMessage()
                 ], 503);
            }
        } while (count($coupons) == $perPage);

        return response()->json([
            'message' => 'coupons sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount
        ], 200);
    }

    public function ordersSync(Request $request)
    {
        $venue = $this->venueService->adminAuthCheck();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;

        try {
            $paymethods = ['PayPal', 'Credit\/Debit Card', 'Not Specified', 'Not Specified 2', 'Online Pago Payment'];
            DB::transaction(function () use ($paymethods) {
                foreach ($paymethods as $item) {
                    PaymentMethod::firstOrCreate(
                        ['name' => $item],
                        ['name' => $item]
                    );
                }
            });
        } catch (\Throwable $th) {
            \Log::error('Error in payment methods sync', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            return response()->json([
                "message" => "Error in payment methods sync",
                "error" => $th->getMessage()
            ], 503);
        }

        try {
            $sources = ['Web', 'Mobile App', 'Quick Checkout'];
            DB::transaction(function () use ($sources) {
                foreach ($sources as $item) {
                    OrderSource::firstOrCreate(
                        ['source' => $item],
                        ['source' => $item]
                    );
                }
            });
        } catch (\Throwable $th) {
            \Log::error('Error in sources sync', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            return response()->json([
                "message" => "Error in sources sync",
                "error" => $th->getMessage()
            ], 503);
        }


        do {
            try {
                $start = microtime(true);
                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'orders-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    break; // No more data to process
                }

                $orders = $bybestData['data'];

                foreach (array_chunk($orders, $batchSize) as $batch) {
                    DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($batch as $item) {
                            \Log::info('Processing orders', ['item' => $item]);

                            // Make sure the required fields are available
                            if (!isset($item['id'])) {
                                \Log::error('orders missing id', ['item' => $item]);
                                $skippedCount++;
                                continue;
                            }


                            $status = OrderStatus::NEW_ORDER;
                            if ($item['status_id'] == 1) {
                                $status = OrderStatus::NEW_ORDER;
                            }
                            else if ($item['status_id'] == 2) {
                                $status = OrderStatus::ON_HOLD;
                            }
                            else if ($item['status_id'] == 3) {
                                $status = OrderStatus::ORDER_CONFIRMED;
                            }
                            else if ($item['status_id'] == 4) {
                                $status = OrderStatus::PROCESSING;
                            }
                            else if ($item['status_id'] == 5) {
                                $status = OrderStatus::ORDER_ON_DELIVERY;
                            }
                            else if ($item['status_id'] == 6) {
                                $status = OrderStatus::ORDER_COMPLETED;
                            }
                            else if ($item['status_id'] == 8) {
                                $status = OrderStatus::ORDER_CANCELLED;
                            }

                            // find customer based on bybest_id =  item['customer_id']
                            $userId = User::where('old_platform_user_id', $item['customer_id'])->first();
                            $customer = Customer::where('user_id', $userId?->id)->first();
                           Order::updateOrCreate(
                                ['bybest_id' => $item['id']],
                                [
                                    'total_amount' => $item['total'],
                                    'total_amount_eur' => $item['total_eur'],
                                    'customer_id' => $customer?->id,
                                    'restaurant_id' => $venue->id,
                                    'status' => $status,
                                    'discount_total' => $item['discount'],
                                    'discount_total_eur' => 0,
                                    'payment_method_id' => $item['payment_id'],
                                    'payment_status' => $item['payment_id'] ? 'paid' : 'unpaid',
                                    'subtotal' => $item['subtotal'],
                                    'subtotal_eur' => 0,
                                    'notes' => $item['meta'],
                                    'order_number' => $item['tracking_number'] ? $item['tracking_number'] : null,
                                    'delivery_fee' => $item['postal'],
                                    'delivery_fee_eur' => 0,
                                    'source_id' => $item['source_id'],
                                    'postal' => $item['postal'],
                                    'ip' => $item['ip'],
                                    'shipping_id' => $item['shipping_id'],
                                    'shipping_name' => $item['shipping_name'],
                                    'shipping_surname' => $item['shipping_surname'],
                                    'bb_shipping_state' => $item['shipping_state'] ? $item['shipping_state'] : null,
                                    'bb_shipping_city' => $item['shipping_city'] ? $item['shipping_city'] : null,
                                    'shipping_phone_no' => $item['shipping_phone_no'] ? $item['shipping_phone_no'] : null,
                                    'shipping_email' => $item['shipping_email'] ? $item['shipping_email'] : null,
                                    'shipping_address' => $item['shipping_address'] ? $item['shipping_address'] : null,
                                    'shipping_postal_code' => $item['shipping_postal_code'] ? $item['shipping_postal_code'] : null,
                                    'billing_name' => $item['billing_name'] ? $item['billing_name'] : null,
                                    'billing_surname' => $item['billing_surname'] ? $item['billing_surname'] : null,
                                    'bb_billing_state' => $item['billing_state'] ? $item['billing_state'] : null,
                                    'bb_billing_city' => $item['billing_city'] ? $item['billing_city'] : null,
                                    'billing_phone_no' => $item['billing_phone_no'] ? $item['billing_phone_no'] : null,
                                    'billing_email' => $item['billing_email'] ? $item['billing_email'] : null,
                                    'billing_address' => $item['billing_address'] ? $item['billing_address'] : null,
                                    'billing_postal_code' => $item['billing_postal_code'] ? $item['billing_postal_code'] : null,
                                    'exchange_rate_eur' => $item['exchange_rate_eur'],
                                    'exchange_rate_all' => $item['exchange_rate_all'],
                                    'has_postal_invoice' => $item['has_postal_invoice'],
                                    'tracking_latitude' => $item['tracking_latitude'],
                                    'tracking_longtitude' => $item['tracking_longtitude'],
                                    'tracking_countryCode' => $item['tracking_countryCode'],
                                    'tracking_cityName' => $item['tracking_cityName'],
                                    'internal_note' => $item['internal_note'],
                                    'bb_coupon_id' => $item['coupon_id'] ? $item['coupon_id'] : null,
                                    'bybest_id' => $item['id'],
                                    'created_at' => $item['created_at'],
                                    'updated_at' => $item['updated_at'],
                                ]
                            );

                            $processedCount++;
                        }
                    });
                }

                \Log::info("Processed {$processedCount} orders so far.");

                $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in orders sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
                 return response()->json([
                     "message" => "Error in orders sync",
                     "error" => $th->getMessage()
                 ], 503);
            }
        } while (count($orders) == $perPage);

        return response()->json([
            'message' => 'orders sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount
        ], 200);
    }

    public function orderProductsSync(Request $request)
    {
        $venue = $this->venueService->adminAuthCheck();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;

        do {
            try {
                $start = microtime(true);
                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'orderproducts-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    break; // No more data to process
                }

                $orderProducts = $bybestData['data'];

                foreach (array_chunk($orderProducts, $batchSize) as $batch) {
                    DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($batch as $item) {
                            \Log::info('Processing orderProducts', ['item' => $item]);

                            // Make sure the required fields are available
                            if (!isset($item['id'])) {
                                \Log::error('orderProducts missing id', ['item' => $item]);
                                $skippedCount++;
                                continue;
                            }

                            $order = Order::where('bybest_id', $item['order_id'])->first();
                            $product = Product::where('bybest_id', $item['product_id'])->first();

                            if (!$product) {
                                \Log::error('products beeing null', ['item' => $item]);
                                $skippedCount++;
                                continue;
                            }

                            $variation_id = null;
                            if ($item['variation_id']) {
                                $productVariant = VbStoreProductVariant::where('bybest_id', $item['variation_id'])->first();
                                if ($productVariant) {
                                    $variation_id = $productVariant->id;
                                }
                            }

                            OrderProduct::updateOrCreate(
                                ['bybest_id' => $item['id']],
                                [
                                    'order_id' => $order?->id,
                                    'product_id' => $product->id,
                                    'variation_id' => $variation_id,
                                    'product_quantity' => $item['quantity'],
                                    'product_total_price' => $item['total'],
                                    'product_total_price_eur' => 0,
                                    'product_discount_price' => $item['discount'],
                                    'product_discount_price_eur' => 0,
                                    'bybest_id' => $item['id'],
                                    'created_at' => $item['created_at'],
                                    'updated_at' => $item['updated_at']
                                ]
                            );

                            $processedCount++;
                        }
                    });
                }

                \Log::info("Processed {$processedCount} orderProducts so far.");

                $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in orderProducts sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
                 return response()->json([
                     "message" => "Error in orderProducts sync",
                     "error" => $th->getMessage()
                 ], 503);
            }
        } while (count($orderProducts) == $perPage);

        return response()->json([
            'message' => 'orderProducts sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount
        ], 200);
    }
}
