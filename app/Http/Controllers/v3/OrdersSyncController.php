<?php

namespace App\Http\Controllers\v3;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\BBLegacyCoupon;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Address;
use App\Models\CustomerAddress;
use App\Models\City;
use App\Models\State;
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
        // $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;

        // do {
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
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }

                $coupons = $bybestData['data'];

                // foreach (array_chunk($coupons, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($coupons as $item) {
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
                    // });
                // }

                \Log::info("Processed {$processedCount} coupons so far.");

                // $page++;
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
        // } while (count($coupons) == $perPage);

        return response()->json([
            'message' => 'coupons sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }

    private array $countryNameMapping = [
        1 => "Albania",
        2 => "Kosovo",
        4 => "North Macedonia",
        5 => "Unspecified"
    ];

    private array $cityMapping = [
        // Albania (1)
        1 => "Elbasan",
        4 => "Tiranë",
        5 => "Durrës",
        6 => "Vlorë",
        7 => "Fier",
        8 => "Gjirokastër",
        9 => "Tepelenë",
        10 => "Kukës",
        11 => "Shkodër",
        12 => "Berat",
        13 => "Dibër",
        14 => "Korçë",
        15 => "Lezhë",
        16 => "Librazhd",
        17 => "Pogradec",
        18 => "Sarandë",
        19 => "Përmet",
        20 => "Lushnjë",
        21 => "Mat",
        22 => "Mirditë",
        23 => "Tropojë",
        24 => "Pukë",
        25 => "Skrapar",
        26 => "Delvinë",
        27 => "Peqin",
        28 => "Kavajë",
        29 => "Bulqizë",
        30 => "Krujë",
        31 => "Laç",
        32 => "Has",
        33 => "Malesi e Madhe",
        34 => "Belsh",
        35 => "Cerrik",
        36 => "Delvine",
        37 => "Devoll",
        38 => "Divjake",
        39 => "Dropull",
        40 => "Finiq",
        41 => "Fushë-Arrëz",
        42 => "Gramsh",
        43 => "Himarë",
        44 => "Kamëz",
        45 => "Këlcyrë",
        46 => "Klos",
        47 => "Ersekë",
        48 => "Konispol",
        49 => "Kuçovë",
        50 => "Libohovë",
        51 => "Maliq",
        52 => "Ballsh",
        53 => "Memaliaj",
        54 => "Patos",
        55 => "Përmet",
        56 => "Poliçan",
        57 => "Përrenjas",
        58 => "Pustec",
        59 => "Roskovec",
        60 => "Rrogozhinë",
        61 => "Selenicë",
        62 => "Shijak",
        63 => "Ura Vajgurore",
        64 => "Vau i Dejës",
        65 => "Vorë",
        66 => "Fushë Krujë",
        67 => "Burrel",
        68 => "Mamurras",

        // Kosovo (2)
        2 => "Prishtinë",
        69 => "Peje",
        70 => "Prizren",
        77 => "Gjakova",
        78 => "Decan",
        79 => "Vitia",
        80 => "Ferizaj",
        81 => "Gjilan",
        82 => "Kamenica",
        83 => "Zvecan",
        84 => "Hani i Elezit",
        85 => "Suhareka",
        86 => "Leposaviq",
        87 => "Podujeva",
        88 => "Klina",
        89 => "Fushë-Kosovë",
        90 => "Zubin Potok",
        91 => "Skenderaj",
        92 => "Lipjan",
        93 => "Shtime",
        94 => "Obiliq",
        95 => "Drenas",
        96 => "Rahovec",
        97 => "Istog",
        98 => "Gllogoc",
        99 => "Dragash",
        100 => "Kaçanik",
        101 => "Novobërdë",
        102 => "Shtërpcë",
        103 => "Vushtrri",
        104 => "Partesh",
        105 => "Malisheva",
        106 => "Junik",
        107 => "Mamushë",
        108 => "Graçanica",
        109 => "Ranillug",
        110 => "Kllokot",

        // North Macedonia (4)
        71 => "Tetovo",
        72 => "Skopje",
        73 => "Kumanovo",
        74 => "Ohrid",
        75 => "Struga",
        111 => "Berovo",
        112 => "Bitola",
        113 => "Bogdanci",
        114 => "Debar",
        115 => "Delčevo",
        116 => "Demir Kapija",
        117 => "Demir Hisar",
        118 => "Gevgelija",
        119 => "Gostivar",
        120 => "Kavadarci",
        121 => "Kičevo",
        122 => "Kočani",
        123 => "Kratovo",
        124 => "Kriva Palanka",
        125 => "Kruševo",
        126 => "Makedonski Brod",
        127 => "Makedonska Kamenica",
        128 => "Negotino",
        129 => "Pehčevo",
        130 => "Prilep",
        131 => "Probištip",
        132 => "Radoviš",
        133 => "Resen",
        134 => "Sveti Nikole",
        135 => "Strumica",
        136 => "Štip",
        137 => "Valandovo",
        138 => "Veles",
        139 => "Vinica",

        // Unspecified (5)
        140 => "Unspecified"
    ];

    public function ordersSync(Request $request): \Illuminate\Http\JsonResponse
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

        try {
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
                return response()->json(['message' => 'No more data to process'], 500);
            }

            $orders = $bybestData['data'];

            foreach ($orders as $item) {
                DB::beginTransaction();
                try {
                    \Log::info('Processing orders', ['item' => $item]);

                    if (!isset($item['id'])) {
                        \Log::error('orders missing id', ['item' => $item]);
                        $skippedCount++;
                        continue;
                    }

                    $status = OrderStatus::NEW_ORDER;
                    if ($item['status_id'] == 1) {
                        $status = OrderStatus::NEW_ORDER;
                    } else if ($item['status_id'] == 2) {
                        $status = OrderStatus::ON_HOLD;
                    } else if ($item['status_id'] == 3) {
                        $status = OrderStatus::ORDER_CONFIRMED;
                    } else if ($item['status_id'] == 4) {
                        $status = OrderStatus::PROCESSING;
                    } else if ($item['status_id'] == 5) {
                        $status = OrderStatus::ORDER_ON_DELIVERY;
                    } else if ($item['status_id'] == 6) {
                        $status = OrderStatus::ORDER_COMPLETED;
                    } else if ($item['status_id'] == 8) {
                        $status = OrderStatus::ORDER_CANCELLED;
                    }

                    $userId = User::where('old_platform_user_id', $item['customer_id'])->first();
                    $customer = Customer::where('user_id', $userId?->id)->first();

                    $venuePrefix = strtoupper(substr($venue->name, 0, 2));
                    $randomFourDigits = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                    $order_number = $venuePrefix . '-' . $item['id'] . $randomFourDigits;

                    $order = Order::updateOrCreate(
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
                            'order_number' => $item['tracking_number'] ? $item['tracking_number'] : $order_number,
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
                            'deleted_at' => $item['deleted_at'],
                        ]
                    );

                    $order->created_at = $item['created_at'];
                    $order->updated_at = $item['updated_at'];
                    $order->deleted_at = $item['deleted_at'];
                    $order->save();

                    if($customer) {
                        // Delete existing addresses
                        DB::transaction(function() use ($customer) {
                            $addressIds = CustomerAddress::where('customer_id', $customer->id)
                                ->pluck('address_id');

                            CustomerAddress::where('customer_id', $customer->id)->delete();
                            Address::whereIn('id', $addressIds)->delete();
                        });

                        if (isset($item['shipping_state']) && isset($item['shipping_city'])) {
                            // Get names from our mapping
                            $cityName = $this->cityMapping[$item['shipping_city']];

                            // Find city by handling different variations
                            $city = City::where('name', 'LIKE', $cityName)
                                ->orWhere('name', 'LIKE', str_replace('ë', 'e', $cityName))
                                ->orWhere('name', 'LIKE', $cityName . 'e')
                                ->orWhere('name', 'LIKE', rtrim($cityName, 'ë'))
                                ->first();

                            if ($city) {
                                $address = Address::create([
                                    'address_line1' => $item['shipping_address'] ?? '',
                                    'city_id' => $city->id,
                                    'state_id' => $city->states_id,
                                    'country_id' => $city->state->country_id,
                                    'postcode' => $item['shipping_postal_code'] ?? ''
                                ]);

                                CustomerAddress::create([
                                    'customer_id' => $customer->id,
                                    'address_id' => $address->id
                                ]);

                                $order->address_id = $address->id;
                                $order->save();
                            }
                        }
                    }

                    DB::commit();
                    $processedCount++;

                } catch (\Exception $e) {
                    DB::rollBack();
                    \Log::error('Error processing order', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'item' => $item
                    ]);
                }
            }

            \Log::info("Processed {$processedCount} orders so far.");

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

        return response()->json([
            'message' => 'orders sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
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

        // do {
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
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }

                $orderProducts = $bybestData['data'];

                // foreach (array_chunk($orderProducts, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($orderProducts as $item) {
                            DB::beginTransaction();
                            try {
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

                                $orderProduct = OrderProduct::updateOrCreate(
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

                                $orderProduct->created_at = $item['created_at'];
                                $orderProduct->updated_at = $item['updated_at'];
                                $orderProduct->save();

                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                \Log::info("Processed {$processedCount} orderProducts so far.");

                // $page++;
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
        // } while (count($orderProducts) == $perPage);

        return response()->json([
            'message' => 'orderProducts sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }
}
