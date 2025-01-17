<?php
namespace App\Http\Controllers\v1;
use App\Enums\DeliveryRequestStatus;
use App\Enums\InventoryActivityCategory;
use App\Enums\OrderStatus;
use App\Events\InventoryLevelUpdated;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryProvider;
use App\Models\DeliveryProviderRestaurant;
use App\Models\Gallery;
use App\Models\Guest;
use App\Models\Inventory;
use App\Models\InventoryActivity;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderProduct;
use App\Models\PaymentMethod;
use App\Models\Photo;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\ScanActivity;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\StoreSetting;
use App\Models\Supplier;
use App\Models\InventoryRetail;
use App\Models\InventoryAlert;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use stdClass;
use function event;
use function response;

/**
 * @OA\Info(
 *   title="Retail API",
 *   version="1.0",
 *   description="This API allows use Retail Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Retail",
 *   description="Operations related to Retail"
 * )
 */


class RetailController extends Controller
{
    public function cuStoreSettings(Request $request): \Illuminate\Http\JsonResponse
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

        // Validation
        $validator = Validator::make($request->all(), [
            'currency' => [
                'nullable',
                Rule::in(['ALL', 'USD', 'EUR'])
            ],
            'new_order_email_recipient' => 'nullable|email',
            'enable_coupon' => 'nullable | boolean',
            'enable_cash_payment_method' => 'nullable | boolean',
            'enable_card_payment_method' => 'nullable | boolean',
            "selling_location" => [
                'nullable',
                Rule::in(['all', 'except', 'specific'])
            ],

            "shipping_location" => [
                'nullable',
                Rule::in(['all', 'except', 'specific'])
            ],
            "selling_countries" => 'nullable | array',
            "shipping_countries" => 'nullable | array',
            "payment_options" => 'nullable | array',
            "tags" => 'nullable | array',
            "additional" => 'nullable|string',
            "neighborhood" => 'nullable|string',
            "description" => 'nullable|string',
            "main_tag" => 'nullable|string',
        ]);



        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Fetch existing store settings for the venue or create new
        $storeSettings = $venue->storeSettings()->firstOrCreate(
            ['venue_id' => $venue->id],
        );

        // Update the data
        $storeSettings->enable_coupon = $request->enable_coupon ?? $storeSettings->enable_coupon;
        $storeSettings->enable_cash_payment_method = $request->enable_cash_payment_method ?? $storeSettings->enable_cash_payment_method ?? false;
        $storeSettings->enable_card_payment_method = $request->enable_card_payment_method ?? $storeSettings->enable_card_payment_method ?? false;
        $storeSettings->currency = $request->currency ?? $storeSettings->currency;
        $storeSettings->new_order_email_recipient = $request->new_order_email_recipient ?? $storeSettings->new_order_email_recipient;
        $storeSettings->selling_locations = json_encode($request->selling_countries) ?? $storeSettings->selling_locations;
        $storeSettings->shipping_locations = json_encode($request->shipping_countries) ?? $storeSettings->shipping_locations;
        $storeSettings->selling_location = $request->selling_location ?? $storeSettings->selling_location;
        $storeSettings->shipping_location = $request->shipping_location ?? $storeSettings->shipping_location;

        $storeSettings->payment_options = json_encode($request->payment_options) ?? $storeSettings->payment_options;
        $storeSettings->tags = json_encode($request->tags) ?? $storeSettings->tags;
        $storeSettings->additional = $request->additional ?? $storeSettings->additional;
        $storeSettings->neighborhood = $request->neighborhood ?? $storeSettings->neighborhood;
        $storeSettings->description = $request->description ?? $storeSettings->description;
        $storeSettings->main_tag = $request->main_tag ?? $storeSettings->main_tag;


        $storeSettings->save();

        return response()->json(['message' => 'Store settings updated successfully']);
    }


    public function show(Request $request): \Illuminate\Http\JsonResponse
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

        $checkStoreSettings = $venue->storeSettings()->first();


        $storeSettings = new StdClass();

        $suppliers = Supplier::where('venue_id', $venue->id)->get();
        $shippingZones = $venue->shippingZones()->with(['shippingMethods' => function($query) {
            $query->withPivot('has_minimum_order_amount', 'flat_rate_cost', 'minimum_order_amount');
        }])->orderBy('created_at', 'desc')->get();


        $formattedZones = [];

        foreach ($shippingZones as $zone) {
            $shippingMethods = [];
            $shippingDescs = [];

            foreach ($zone->shippingMethods as $method) {
                $shippingMethods[] = [
                    'method_id' => $method->id,
                    'method_type' => $method->type,
                    'flat_rate_cost' => $method->pivot->flat_rate_cost,
                    'has_minimum_order_amount' => $method->pivot->has_minimum_order_amount,
                    'minimum_order_amount' => $method->pivot->minimum_order_amount
                ];

                if ($method->type === 'flat_rate') {
                    $shippingDescs[] = "Flat rate (" . ($venue->storeSettings()->first()->currency ?? "") . $method->pivot->flat_rate_cost . ")";
                } elseif ($method->type === 'free_shipping' && $method->pivot->has_minimum_order_amount) {
                    $shippingDescs[] = "Free shipping (Minimum Order: " .($venue->storeSettings()->first()->currency ?? "") . $method->pivot->minimum_order_amount . ")";
                } else {
                    $shippingDescs[] = "Free shipping";
                }
            }

            $formattedZones[] = [
                'zone_id' => $zone->id,
                'zone_name' => $zone->zone_name,
                'region' => $zone->region,
                'shipping_methods' => $shippingMethods,
                'shipping_method_desc' => implode(', ', $shippingDescs)
            ];
        }

        if ($checkStoreSettings) {
            $storeSettings->currency = $venue->storeSettings()->first()->currency;
            $storeSettings->enable_coupon  =  $venue->storeSettings()->first()->enable_coupon ?? false;
            $storeSettings->enable_cash_payment_method =  $venue->storeSettings()->first()->enable_cash_payment_method ?? false;
            $storeSettings->enable_card_payment_method =  $venue->storeSettings()->first()->enable_card_payment_method ?? false;
            $storeSettings->new_order_email_recipient =  $venue->storeSettings()->first()->new_order_email_recipient ?? null;
            $storeSettings->selling_countries = json_decode( $venue->storeSettings()->first()->selling_locations) ?? [];
            $storeSettings->shipping_countries = json_decode( $venue->storeSettings()->first()->shipping_locations) ?? [];
            $storeSettings->selling_location  = $venue->storeSettings()->first()->selling_location ?? 'all';
            $storeSettings->shipping_location  = $venue->storeSettings()->first()->shipping_location ?? 'all';
            $storeSettings->suppliers = $suppliers;
            $storeSettings->shipping_zones = $formattedZones;
            $storeSettings->description = $venue->storeSettings()->first()->description ?? null;
            $storeSettings->neighborhood = $venue->storeSettings()->first()->neighborhood ?? null;
            $storeSettings->additional = $venue->storeSettings()->first()->additional ?? null;
            $storeSettings->main_tag = $venue->storeSettings()->first()->main_tag ?? null;
            $storeSettings->tags = json_decode($venue->storeSettings()->first()->tags, true);
            $storeSettings->payment_options = json_decode($venue->storeSettings()->first()->payment_options, true);
        }
        else {
            $storeSettings->currency = null;
            $storeSettings->enable_coupon = false;
            $storeSettings->enable_cash_payment_method = false;
            $storeSettings->enable_card_payment_method = false;
            $storeSettings->new_order_email_recipient = null;
            $storeSettings->suppliers = $suppliers;
            $storeSettings->shipping_zones = $formattedZones;
            $storeSettings->selling_location  = 'all';
            $storeSettings->shipping_location  = 'all';
            $storeSettings->selling_countries = [];
            $storeSettings->shipping_countries = [];
            $storeSettings->description = null;
            $storeSettings->neighborhood = null;
            $storeSettings->additional = null;
            $storeSettings->main_tag = null;
            $storeSettings->tags = null;
            $storeSettings->payment_options = null;
        }


        $address = $venue->addresses ?? null;
        $storeSettings->address = $address;

        $gallery = Gallery::where('venue_id', $venue->id)->with('photo')->get();

        $modifiedGallery = $gallery->map(function ($item) {
            return [
                'photo_id' => $item->photo_id,
                'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
            ];
        });

        $storeSettings->gallery = $modifiedGallery;

        $urlDefinition = $venue->venueType->definition;
        if ($venue->venueType->name == 'Pharmacy') {
            $urlDefinition = 'pharmacy';
        }
        $url = 'https://venueboost.io/venue/'.$urlDefinition.'/'.$venue->app_key;

        return response()->json([
            'store_settings' => $storeSettings,
            'url' => $url,
            'message' => 'Store settings retrieved successfully'
        ]);
    }

    public function getCustomers(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $search = $request->input('search');

        $customers = Customer::where('venue_id', $venue->id)
            ->with(['customerAddresses.address', 'user.member'])
            ->withCount('orders');
            
            if($search) {
                $customers = $customers->whereRaw('LOWER(name) LIKE ?', ["%" . strtolower($search) . "%"]);
            }

              // If pagination is requested, apply pagination, otherwise fetch all results
            if ($request->has('per_page') && $request->has('page')) {
                $customers = $customers->orderBy('created_at', 'desc')->paginate($perPage);
            } else {
                // If no pagination is requested, retrieve all products
                $customers = $customers->orderBy('created_at', 'DESC')->get();
            }

        $result = $customers->map(function ($customer) {
            return [
                'first_order' => $customer->orders()->orderBy('created_at', 'asc')->first()?->created_at->format('F d, Y h:i A') ?? '-',
                'register_date' => $customer->user && $customer->user->created_at ? $customer->user->created_at->format('F d, Y h:i A') : 'N/A',
                'customer_register_date' => $customer->created_at->format('F d, Y h:i A'), // '2021-09-01 12:00:00
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'user' => $customer->user,
                'is_member' => $customer->user && $customer->user->member ? true : false,
                'total_orders' => $customer->orders_count,
            ];
        });

        // Only include pagination if the per_page and page parameters are provided
        if ($request->has('per_page') && $request->has('page')) {
            $response['pagination'] = [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total()
            ];
        }

        $response = [
            'customers' => $result,
        ];

        return response()->json($response, 200);
    }

    public function getSearchCustomers(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $search = $request->input('search');

        $customers = Customer::where('venue_id', $venue->id)->with(['user' => function ($query) {
            $query->select('id');
        }]);
            
        if($search) {
            $customers = $customers->whereRaw('LOWER(name) LIKE ?', ["%" . strtolower($search) . "%"]);
        }

        $customers = $customers->select('id', 'name', 'user_id')->orderBy('created_at', 'DESC')->get();

        $response = [
            'customers' => $customers,
        ];

        return response()->json($response, 200);
    }


    public function fetchRevenueData(Request $request): \Illuminate\Http\JsonResponse
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

        $type = $request->input('type');
        if (!$type || !in_array($type, ['sales', 'coupons', 'detailed'])) {
            return response()->json(['error' => 'Invalid type provided'], 400);
        }

        switch ($type) {
            case 'sales':
                $data = $this->fetchSalesData($venue);
                break;
            case 'coupons':
                $data = $this->fetchCouponsData($venue);
                break;
            case 'detailed':
                $data = $this->fetchDetailedRevenueData($venue);
                break;
        }

        return response()->json($data, 200);
    }

    private function fetchSalesData($venue): array
    {
        // Fetching only completed orders
        $sales = Order::where('status', 'order_completed')
            ->where('restaurant_id', $venue->id)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_amount) as total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get();

        $dates = $sales->pluck('date');

        // Convert totals to float values
        $totals = $sales->pluck('total')->map(function ($total) {
            return floatval($total);
        });

        return [
            'series' => [
                [
                    'name' => 'Sales',
                    'data' => $totals->toArray()
                ]
            ],
            'xaxis' => [
                'categories' => $dates->toArray()
            ],
            'currency' => $venue->storeSettings()->first()->currency ?? null
        ];
    }

    private function fetchCouponsData($venue): array
    {
        // Fetching coupon data associated with completed orders
        $couponsUsed = DB::table('order_coupons')
            ->join('orders', 'orders.id', '=', 'order_coupons.order_id')
            ->where('orders.restaurant_id', $venue->id)
            ->where('orders.status', 'order_completed')
            ->select(DB::raw('DATE(orders.created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(orders.created_at)'))
            ->get();

        $dates = $couponsUsed->pluck('date');
        $counts = $couponsUsed->pluck('count');

        return [
            'series' => [
                [
                    'name' => 'Coupons Used',
                    'data' => $counts->toArray()
                ]
            ],
            'xaxis' => [
                'categories' => $dates->toArray()
            ]
        ];
    }

    private function fetchDetailedRevenueData($venue): array
    {
        $startDate = $venue->storeSettings()->first()->created_at ?? now();

        $orders = Order::where('status', 'order_completed')
            ->where('restaurant_id', $venue->id)
            ->where('created_at', '>=', $startDate)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as order_count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get();

        $couponCounts = DB::table('order_coupons')
            ->join('orders', 'orders.id', '=', 'order_coupons.order_id')
            ->where('orders.restaurant_id', $venue->id)
            ->where('orders.status', 'order_completed')
            ->where('orders.created_at', '>=', $startDate)
            ->select(DB::raw('DATE(orders.created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(orders.created_at)'))
            ->pluck('count', 'date');  // Get counts keyed by date

        $result = [];

        foreach ($orders as $order) {
            $result[] = [
                'date' => $order->date,
                'order_count' => $order->order_count,
                'sales' => floatval($order->total),
                'coupons_used' => $couponCounts[$order->date] ?? 0,  // Use the coupon count for the date or 0 if not set
                'currency' => $venue->storeSettings()->first()->currency ?? ''
            ];
        }

        return $result;
    }

    public function scanHistory(Request $request)
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $query = ScanActivity::with(['product', 'venue'])
            ->where('venue_id', $venue->id);

        // Apply additional filters if provided
        if ($request->has('scan_type')) {
            $query->where('scan_type', $request->scan_type);
        }

        if ($request->has('start_date')) {
            $query->whereDate('scan_time', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('scan_time', '<=', $request->end_date);
        }

        // Paginate results
        $perPage = $request->get('per_page', 15);
        $scanActivities = $query->orderBy('scan_time', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $scanActivities->items(),
            'current_page' => $scanActivities->currentPage(),
            'last_page' => $scanActivities->lastPage(),
            'per_page' => $scanActivities->perPage(),
            'total' => $scanActivities->total(),
        ]);
    }

    public function fetchDashboardData(Request $request): \Illuminate\Http\JsonResponse
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

        $dates = $this->getDateRange($request);
        $startDate = $dates['start'];
        $endDate = $dates['end'];
        $startOfLastMonth = $dates['start_lastmonth'];
        $endOfLastMonth = $dates['end_lastmonth'];

        // block Data
        $orders = Order::where('restaurant_id', $venue->id)
            ->where('status', 'order_completed');

         if($startDate && $endDate){
            $orders->whereBetween('created_at', [$startDate, $endDate]);
         }
         $orders = $orders->get();
         $groupedOrders = $orders->groupBy('customer_id');
         $nCustomers = count($groupedOrders);

         $ordersLastMonth = Order::where('restaurant_id', $venue->id)
         ->where('status', 'order_completed')
         ->whereNotNull('status')
         ->whereNotNull('order_number')
         ->where('created_at', '>=', $startOfLastMonth)
         ->where('created_at', '<=', $endOfLastMonth)
         ->get();



         $groupedOrdersLastMonth = $ordersLastMonth->groupBy('customer_id');
         $nCustomersLastMonth = count($groupedOrdersLastMonth);


         $salesAmount = Order::where('restaurant_id', $venue->id)
         ->where('status', 'order_completed');

        if($startDate && $endDate){
            $salesAmount = $salesAmount->whereBetween('created_at', [$startDate, $endDate]);
        }

        $salesAmount = $salesAmount->sum('total_amount');


        $salesAmountLastMonth = Order::where('restaurant_id', $venue->id)
            ->where('status', 'order_completed')
            ->where('created_at', '>=', $startOfLastMonth)
            ->where('created_at', '<=', $endOfLastMonth)
            ->sum('total_amount');

            // revenue statistics
            $revenueStatistics = [];

            $month = date('m', strtotime($startDate));
            $year = date('Y', strtotime($startDate));
            $weeks = $this->getWeeksInMonth( $year, $month);
            foreach ($weeks as $key => $week) {
                $amount = Order::where('restaurant_id', $venue->id)
                ->where('status', 'order_completed')
                ->whereNotNull('status')
                ->whereNotNull('order_number')
                ->where('created_at', '>=', $week['start'])
                ->where('created_at', '<=', $week['end'])
                ->sum('total_amount');
                $revenueStatistics[] = [
                    'week' => 'Week' . ($key + 1),
                    'start' => $week['start'],
                    'end' => $week['end'],
                    'value' => $amount
                ];
            }

            // orders by cities
            $orders_city = Order::join('addresses', 'addresses.id', '=', 'orders.address_id')
                ->join('cities', 'cities.id', '=', 'addresses.city_id')
                ->select('cities.name', DB::raw('COUNT(*) as order_count'))
                ->where('orders.restaurant_id', $venue->id)
                ->where('orders.status', 'order_completed');

            if($startDate && $endDate) {
                $orders_city = $orders_city->whereBetween('orders.created_at', [$startDate, $endDate]);
            }

            $orders_city = $orders_city->groupBy('cities.name')->get();

            // orders
            $orders = Order::join('customers', 'customers.id', '=', 'orders.customer_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'orders.payment_method_id' )
            ->select('orders.*', DB::raw('customers.name as customer_name'), 'payment_methods.name')
            ->whereNotNull('orders.status')
            ->where('status', '!=', '1')
            ->whereNotNull('orders.order_number')
            ->where('orders.restaurant_id', $venue->id);


            if($startDate && $endDate){
                $orders = $orders->whereBetween('orders.created_at', [$startDate, $endDate]);
            }

            $orders = $orders->OrderBy('orders.created_at', 'desc')->get();

            $orders = $orders->map(function ($order) {
                return [
                    'order_number' => $order->order_number,
                    'id' => $order->id,
                    'ip'=>$order->ip,
                    'customer_full_name' => $order->customer->name ?? '',
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'currency' => $order->currency,
                    'order_for' => $order->is_for_self ? 'Self' : $order->other_person_name,
                    'stripe_payment_id' => $order->stripe_payment_id,
                    'created_at' => $order->created_at->format('F d, Y h:i A'),
                ];
            });

            // orders by date
            $orders_by_date = Order::where('restaurant_id', $venue->id)
            ->where('status', 'order_completed')
            ->whereNotNull('status')
            ->whereNotNull('order_number')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as order_count'))
            ->groupBy(DB::raw('DATE(created_at)'));

            if($startDate && $endDate){
                $orders_by_date = $orders_by_date->whereBetween('created_at', [$startDate, $endDate]);
            }

            $orders_by_date = $orders_by_date->get();



        // best seller products
        $bestseller_products = DB::table('orders')
            ->join('order_products', 'orders.id', '=', 'order_products.order_id')
            ->join('products', 'order_products.product_id', '=', 'products.id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->where('orders.status', 'order_completed')
            ->where('orders.restaurant_id', $venue->id)
            ->whereNotNull('orders.status')
            ->whereNotNull('orders.order_number')
            // ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->select('products.title', 'products.image_path', DB::raw('brands.title as brand_title'), DB::raw('COUNT(DISTINCT orders.id) as order_count'))
            ->groupBy('products.title', 'products.image_path', 'brand_title')
            ->orderBy('order_count', 'desc')
            ->limit(5)
            ->get();

        $bestseller_products = $bestseller_products->map(function ($item) {
            return [
                'title' => $item->title,
                'order_count' => $item->order_count,
                'brand_title' => $item->brand_title,
                'image_path' =>  $item->image_path ? Storage::disk('s3')->temporaryUrl($item->image_path, '+5 minutes') : null,
            ];
        });

        return response()->json([
            'block_data' => [
                'num_clients' => $nCustomers,
                'num_clients_last_month' => $nCustomersLastMonth,
                'num_orders' => count($orders),
                'num_orders_last_month' => count($ordersLastMonth),
                'sales_amount' => $salesAmount,
                'sales_amount_last_month' => $salesAmountLastMonth,
                'weeks' => $weeks,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'startOfLastMonth' => $startOfLastMonth,
                'endOfLastMonth' => $endOfLastMonth
            ],
            'revenue_statistics' => $revenueStatistics,
            'orders_city' => $orders_city,
            'bestseller_products' => $bestseller_products,
            'orders_by_date' => $orders_by_date,
            'orders' => $orders
        ]);
    }

    public function fetchInventoryAnalyticsData(Request $request): \Illuminate\Http\JsonResponse
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

        // block Data
        $nProducts = Product::where('restaurant_id', $venue->id)->count();
        $nVariableProducts = Product::where('restaurant_id', $venue->id)->where('product_type', 'variable')->count();
        $totalStock = InventoryRetail
            ::leftJoin('products', 'inventory_retail.product_id', '=', 'products.id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->where('inventory_retail.venue_id', $venue->id)
            ->sum('inventory_retail.stock_quantity');

        $nAlerts = InventoryAlert::leftJoin('inventory_retail', 'inventory_retail.id', '=', 'inventory_alerts.inventory_retail_id')
            ->where('inventory_retail.venue_id', $venue->id)
            ->count();

        // stocks over time
        $stock_over_time_brand = request()->get('stock_over_time_brand');


        $stocks = [];

        $curStock = InventoryRetail
            ::leftJoin('products', 'inventory_retail.product_id', '=', 'products.id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->where('inventory_retail.venue_id', $venue->id);
        if ($stock_over_time_brand) {
            $curStock = $curStock->where('brands.id', $stock_over_time_brand);
        }
        $curStock = $curStock->sum('inventory_retail.stock_quantity');

        for ($i = 0; $i < 7; $i ++) {
            $date_start = Carbon::now()->subDays($i + 1)->toDateString();
            $date_end = Carbon::now()->subDays($i)->toDateString();

            $stocks[] = [
                'date' => $date_end,
                'stock' => $curStock
            ];

            $add_quantity = InventoryActivity
                ::leftJoin('products', 'inventory_activities.product_id', '=', 'products.id')
                ->leftJoin('brands', 'brands.bybest_id', '=', 'products.brand_id')
                ->where('inventory_activities.created_at', '>=', $date_start)
                ->where('inventory_activities.created_at', '<', $date_end)
                ->where('activity_type', 'add');

            if ($stock_over_time_brand) {
                $add_quantity = $add_quantity->where('brands.id', $stock_over_time_brand);
            }
            $add_quantity = $add_quantity->sum('quantity');

            $deduct_quantity = InventoryActivity
                ::leftJoin('products', 'inventory_activities.product_id', '=', 'products.id')
                ->leftJoin('brands', 'brands.bybest_id', '=', 'products.brand_id')
                ->where('inventory_activities.created_at', '>=', $date_start)
                ->where('inventory_activities.created_at', '<', $date_end)
                ->where('activity_type', 'deduct');

            if ($stock_over_time_brand) {
                $deduct_quantity = $deduct_quantity->where('brands.id', $stock_over_time_brand);
            }
            $deduct_quantity = $deduct_quantity->sum('quantity');

            $curStock = $curStock - $add_quantity + $deduct_quantity;
        }

        $lowStockProducts = InventoryRetail
            ::leftJoin('products', 'inventory_retail.product_id', '=', 'products.id')
            ->leftJoin('brands', 'brands.bybest_id', '=', 'products.brand_id')
            ->leftJoin('order_products', 'order_products.product_id', '=', 'products.id')
            ->leftJoin('orders', function($join) {
                $join->on('order_products.order_id', '=', 'orders.id')
                     ->where('orders.status', '=', 'order_completed');
                    })
            ->select(DB::raw('inventory_retail.stock_quantity as stock') , 'products.id', 'products.title', DB::raw('brands.title as brand_title'), DB::raw('orders.created_at as order_date'))
            ->where('inventory_retail.venue_id', $venue->id)
            ->orderBy('stock', 'ASC')
            ->limit(5)
            ->get();

        $topStockProducts = InventoryRetail
            ::leftJoin('products', 'inventory_retail.product_id', '=', 'products.id')
            ->leftJoin('brands', 'brands.bybest_id', '=', 'products.brand_id')
            ->leftJoin('order_products', 'order_products.product_id', '=', 'products.id')
            ->leftJoin('orders', function($join) {
                $join->on('order_products.order_id', '=', 'orders.id')
                     ->where('orders.status', '=', 'order_completed');
            })
            ->select(DB::raw('inventory_retail.stock_quantity as stock') , 'products.id', 'products.title', DB::raw('brands.title as brand_title'), DB::raw('orders.created_at as order_date'))
            ->where('inventory_retail.venue_id', $venue->id)
            ->orderBy('stock', 'DESC')
            ->limit(5)
            ->get();

        $productDistributionBrand = Product
            ::join('brands', 'brands.bybest_id', '=', 'products.brand_id')
            ->where('brands.venue_id', $venue->id)
            ->select(DB::raw('brands.title as name'), DB::raw('COUNT(*) as value'))
            ->groupBy('name')
            ->get();

        return response()->json([
            'block_data' => [
                'num_products' => $nProducts,
                'num_products_variable' => $nVariableProducts,
                'total_stock' => $totalStock,
                'num_alerts' => $nAlerts,
            ],
            'lowStockProducts' => $lowStockProducts,
            'topStockProducts' => $topStockProducts,
            'productDistributionBrand' => $productDistributionBrand,
            'stocks_over_time' => $stocks
        ]);
    }

    private function fetchOrdersByCategory($venue): array
    {
        $categories = DB::table('orders')
            ->join('order_products', 'orders.id', '=', 'order_products.order_id')
            ->join('products', 'order_products.product_id', '=', 'products.id')
            ->join('product_category', 'products.id', '=', 'product_category.product_id')
            ->join('categories', 'product_category.category_id', '=', 'categories.id')
            ->where('orders.status', 'order_completed')
            ->where('orders.restaurant_id', $venue->id)
            ->select('categories.title', DB::raw('COUNT(DISTINCT orders.id) as order_count'))
            ->groupBy('categories.title')
            ->get();

        return $categories->toArray();
    }

    private function fetchOrdersByProduct($venue): array
    {
        $products = DB::table('orders')
            ->join('order_products', 'orders.id', '=', 'order_products.order_id')
            ->join('products', 'order_products.product_id', '=', 'products.id')
            ->where('orders.status', 'order_completed')
            ->where('orders.restaurant_id', $venue->id)
            ->select('products.title', DB::raw('COUNT(DISTINCT orders.id) as order_count'))
            ->groupBy('products.title')
            ->get();

        return $products->toArray();
    }

    private function fetchOrdersByDate($venue): array
    {
        $dateFrom = $venue->storeSettings()->first()->created_at ?? now();

        $orders = Order::where('restaurant_id', $venue->id)
            ->where('status', 'order_completed')
            ->whereDate('created_at', '>=', $dateFrom)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as order_count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get();

        $dates = $orders->pluck('date');
        $orderCounts = $orders->pluck('order_count');

        return [
            'series' => [
                [
                    'name' => 'Orders',
                    'data' => $orderCounts->toArray()
                ]
            ],
            'xaxis' => [
                'categories' => $dates->toArray()
            ]
        ];
    }


    private function fetchSalesByDate($venue): array
    {
        $dateFrom = $venue->storeSettings()->first()->created_at ?? now();

        $sales = Order::where('restaurant_id', $venue->id)
            ->where('status', 'order_completed')
            ->whereDate('created_at', '>=', $dateFrom)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_amount) as sales_amount'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get();

        $dates = $sales->pluck('date');

        // Cast sales amounts to integers
        $salesAmounts = $sales->pluck('sales_amount')->map(function ($amount) {
            return floatval($amount);
        });

        return [
            'series' => [
                [
                    'name' => 'Sales',
                    'data' => $salesAmounts->toArray()
                ]
            ],
            'xaxis' => [
                'categories' => $dates->toArray()
            ],
            'currency' => $venue->storeSettings()->first()->currency ?? ''
        ];
    }

    private function getDateRange(Request $request)
    {
        $startDate = $request->query('start');
        $endDate = $request->query('end');

        if (!$startDate || !$endDate) {
            // current month start date
            $startDate = Carbon::now()->startOfMonth()->toDateString();
            $endDate = Carbon::now()->endOfMonth()->toDateString();
        }

        $startOfLastMonth = Carbon::now()->startOfMonth()->subMonthsNoOverflow()->toDateString();
        $endOfLastMonth = Carbon::now()->subMonthsNoOverflow()->endOfMonth()->toDateString();

        return [
            'start' => $startDate,
            'end' => $endDate,
            'start_lastmonth' => $startOfLastMonth,
            'end_lastmonth' => $endOfLastMonth,
        ];
    }

    private function getWeeksInMonth($year, $month) {
        $startDate = Carbon::createFromDate($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();

        $weeks = [];

        while ($startDate->lt($endDate)) {
            $weekStart = $startDate->copy()->startOfWeek(); // Get the start of the week
            $weekEnd = $weekStart->copy()->endOfWeek(); // Get the end of the week

            if ($weekStart->lt($startDate)) {
                $weekStart = $startDate; // Adjust to the start date of the month if it falls within the month
            }

            if ($weekEnd->gt($endDate)) {
                $weekEnd = $endDate; // Adjust to the end date of the month if it exceeds the end of the month
            }

            $weeks[] = [
                'start' => $weekStart->format('Y-m-d'),
                'end' => $weekEnd->format('Y-m-d')
            ];

            $startDate->addWeek(); // Move to the next week
        }

        return $weeks;
    }

    public function createSupplier(Request $request): \Illuminate\Http\JsonResponse
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
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $data['name'] = $request->input('name');
        $data['venue_id'] = $venue->id;

        $supplier = Supplier::create($data);

        return response()->json(['message' => 'Supplier created successfully', 'supplier' => $supplier]);
    }

    public function updateSupplier(Request $request): \Illuminate\Http\JsonResponse
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
            'name' => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $supplier = Supplier::where('id', $request->id)
            ->where('venue_id', $venue->id)
            ->first();

        if (!$supplier) {
            return response()->json(['error' => 'Supplier not found'], 404);
        }

        // Update the supplier attributes
        $supplier->name = $request->name ?? $supplier->name;
        $supplier->save();


        return response()->json(['message' => 'Supplier updated successfully', 'supplier' => $supplier]);
    }

    public function listSuppliers(): \Illuminate\Http\JsonResponse
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

        $suppliers = Supplier::where('venue_id', $venue->id)->get();

        return response()->json(['suppliers' => $suppliers]);
    }

    public function deleteSupplier($supplierId): \Illuminate\Http\JsonResponse
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

        $supplier = Supplier::where('id', $supplierId)->where('venue_id', $venue->id)->first();
        if (!$supplier) {
            return response()->json(['message' => 'The requested supplier does not exist'], 404);
        }

        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted successfully']);
    }


    // Brands CRUD
    public function createBrand(Request $request): \Illuminate\Http\JsonResponse
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
            'title' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $data['title'] = $request->input('title');
        $data['description'] = $request->input('description');
        $data['url'] = $request->input('url');
        $data['total_stock'] = $request->input('total_stock');
        $data['parent_id'] = $request->input('parent_id');

        $path = null;
        if ($request->file('image')) {

            $requestType = 'other';

            $logoBrand = $request->file('image');

            // Decode base64 image data
            $photoFile = $logoBrand;
            $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();


            // Upload photo to AWS S3
            $path = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $venue->venueType->short_name . '/' . $requestType . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)), $photoFile, $filename);

            // Save photo record in the database
            $photo = new Photo();
            $photo->venue_id = $venue->id;
            $photo->image_path = $path;
            $photo->type = $requestType;
            $photo->save();

        }

        $data['logo_path'] = $path;

        $path = null;
        if ($request->file('white_logo')) {

            $requestType = 'other';
            $logoBrand = $request->file('white_logo');

            // Decode base64 image data
            $photoFile = $logoBrand;
            $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();

            // Upload photo to AWS S3
            $path = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $venue->venueType->short_name . '/' . $requestType . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)), $photoFile, $filename);

            // Save photo record in the database
            $photo = new Photo();
            $photo->venue_id = $venue->id;
            $photo->image_path = $path;
            $photo->type = $requestType;
            $photo->save();

        }

        $data['white_logo_path'] = $path;
        $data['venue_id'] = $venue->id;

        $brand = Brand::create($data);

        return response()->json(['message' => 'Brand created successfully', 'brand' => $brand]);
    }

    public function updateBrand(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $brand = Brand::find($request->id);
        if (!$brand) {
            return response()->json(['error' => 'Brand not found'], 404);
        }

        $brand->title = $request->input('title');
        $brand->description = $request->input('description');
        $brand->url = $request->input('url');
        $brand->total_stock = $request->input('total_stock');
        $brand->parent_id = $request->input('parent_id');

        if ($request->file('image')) {

            $requestType = 'other';

            $logoBrand = $request->file('image');

            // Decode base64 image data
            $photoFile = $logoBrand;
            $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();

            // Upload photo to AWS S3
            $path = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $venue->venueType->short_name . '/' . $requestType . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)), $photoFile, $filename);

            // Save photo record in the database
            $photo = new Photo();
            $photo->venue_id = $venue->id;
            $photo->image_path = $path;
            $photo->type = $requestType;
            $photo->save();

            // Update the brand logo path
            $brand->logo_path = $path;
        }

        if ($request->file('white_logo')) {

            $requestType = 'other';

            $logoBrand = $request->file('white_logo');

            // Decode base64 image data
            $photoFile = $logoBrand;
            $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();

            // Upload photo to AWS S3
            $path = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $venue->venueType->short_name . '/' . $requestType . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)), $photoFile, $filename);

            // Save photo record in the database
            $photo = new Photo();
            $photo->venue_id = $venue->id;
            $photo->image_path = $path;
            $photo->type = $requestType;
            $photo->save();

            // Update the brand logo path
            $brand->white_logo_path = $path;
        }

        $brand->save();

        return response()->json(['message' => 'Brand updated successfully', 'brand' => $brand]);
    }

    public function listBrands(): \Illuminate\Http\JsonResponse
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

        $brands = Brand::with('parent')->where('venue_id', $venue->id);
        $parent_only = request()->get('parent_only');
        if ($parent_only) {
            $brands = $brands->whereNull('parent_id');
        }
        $brands = $brands->get();

        $updatedBrands = $brands->map(function ($brand) {
            // if ($brand->logo_path !== null) {
            //     // Generate the new path and update the image_path attribute
            //     $newPath = Storage::disk('s3')->temporaryUrl($brand->logo_path, '+5 minutes');
            //     $brand->logo_path = $newPath;
            // }
            // if ($brand->white_logo_path !== null) {
            //     // Generate the new path and update the image_path attribute
            //     $newPath = Storage::disk('s3')->temporaryUrl($brand->white_logo_path, '+5 minutes');
            //     $brand->white_logo_path = $newPath;
            // }

            $totalStock = InventoryRetail
                ::leftJoin('products', 'inventory_retail.product_id', '=', 'products.id')
                ->where('products.brand_id', $brand->id)
                ->sum('inventory_retail.stock_quantity');

            $singleCnt = Product::where('brand_id', $brand->id)->where('product_type', 'single')->count();
            $variableCnt = Product::where('brand_id', $brand->id)->where('product_type', 'variable')->count();

            $brand->products = [
                'single' =>  $singleCnt,
                'variable' =>  $variableCnt,
            ];
            $brand->total_stock = $totalStock;
            return $brand;
        });

        return response()->json(['brands' => $updatedBrands]);
    }

    public function deleteBrand($brandId): \Illuminate\Http\JsonResponse
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

        $brand = Brand::where('id', $brandId)->where('venue_id', $venue->id)->first();
        if (!$brand) {
            return response()->json(['message' => 'The requested brand does not exist'], 404);
        }

        $brand->delete();

        return response()->json(['message' => 'Brand deleted successfully']);
    }


    // Collections CRUD
    public function createCollection(Request $request): \Illuminate\Http\JsonResponse
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
            'name' => 'required|string',
            'description' => 'nullable|string',
            'slug' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $data['name'] = $request->input('name');
        $data['description'] = $request->input('description');

        // if slug not provided, generate one
        if ($request->input('slug')) {
            $data['slug'] = $request->input('slug');
        } else {
            $data['slug'] = Str::slug($request->input('name'));
        }


        $path = null;
        if ($request->file('image')) {

            $requestType = 'other';

            $logoCollection = $request->file('image');

            // Decode base64 image data
            $photoFile = $logoCollection;
            $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();


            // Upload photo to AWS S3
            $path = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $venue->venueType->short_name . '/' . $requestType . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)), $photoFile, $filename);

            // Save photo record in the database
            $photo = new Photo();
            $photo->venue_id = $venue->id;
            $photo->image_path = $path;
            $photo->type = $requestType;
            $photo->save();

        }

        $data['logo_path'] = $path;

        $data['venue_id'] = $venue->id;

        $collection = Collection::create($data);

        return response()->json(['message' => 'Collection created successfully', 'collection' => $collection]);
    }

    public function updateCollection(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $collection = Collection::find($request->id);
        if (!$collection) {
            return response()->json(['error' => 'Collection not found'], 404);
        }

        $collection->name = $request->input('name');
        $collection->description = $request->input('description');

        if ($request->input('slug')) {
            $collection->slug = $request->input('slug');
        } else {
            $collection->slug = Str::slug($request->input('name'));
        }

        if ($request->file('image')) {

            $requestType = 'other';

            $logoCollection = $request->file('image');

            // Decode base64 image data
            $photoFile = $logoCollection;
            $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();

            // Upload photo to AWS S3
            $path = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $venue->venueType->short_name . '/' . $requestType . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)), $photoFile, $filename);

            // Save photo record in the database
            $photo = new Photo();
            $photo->venue_id = $venue->id;
            $photo->image_path = $path;
            $photo->type = $requestType;
            $photo->save();

            // Update the collection logo path
            $collection->logo_path = $path;
        }



        $collection->save();

        return response()->json(['message' => 'Collection updated successfully', 'collection' => $collection]);
    }

    public function listCollections(Request $request): \Illuminate\Http\JsonResponse
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

        $perPage = request()->get('per_page', 15); // Default to 15 items per page
        $collections = Collection::where('venue_id', $venue->id);
        if ($request->has('search')) {
            $search_item = '%' . $request->search . '%';  // Add wildcards for partial match

            $collections = $collections->where(function ($query) use ($search_item) {
                $query->where('name', 'like', $search_item)
                      ->orWhere('name_al', 'like', $search_item);
            });
        }


        $collections = $collections->paginate($perPage);


        $updatedCollections = $collections->map(function ($collection) {
            // if ($collection->logo_path !== null) {
            //     // Generate the new path and update the image_path attribute
            //     $newPath = Storage::disk('s3')->temporaryUrl($collection->logo_path, '+5 minutes');
            //     $collection->logo_path = $newPath;
            // }

            // Decode JSON strings
            $nameArray = json_decode($collection->name, true);
            $descriptionArray = json_decode($collection->description, true);

            // Get 'en' version, fallback to 'sq' if 'en' doesn't exist
            $collection->name = $nameArray['en'] ?? $nameArray['sq'] ?? $collection->name;
            $collection->description = $descriptionArray['en'] ?? $descriptionArray['sq'] ?? $collection->description;

            return $collection;
        });

        return response()->json([
            'collections' => $updatedCollections,
            'pagination' => [
                'total' => $collections->total(),
                'per_page' => $collections->perPage(),
                'current_page' => $collections->currentPage(),
                'last_page' => $collections->lastPage(),
                'from' => $collections->firstItem(),
                'to' => $collections->lastItem(),
            ],
        ]);
    }

    public function deleteCollection($collectionId): \Illuminate\Http\JsonResponse
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

        $collection = Collection::where('id', $collectionId)->where('venue_id', $venue->id)->first();
        if (!$collection) {
            return response()->json(['message' => 'The requested collection does not exist'], 404);
        }

        $collection->delete();

        return response()->json(['message' => 'Collection deleted successfully']);
    }

    // Shipping Zones
    public function createShippingZone(Request $request): \Illuminate\Http\JsonResponse
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
            'zone_name' => 'required|string',
            'region' => 'required|string',
            'shipping_methods' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }



        $methodsData = [];
        foreach ($request->input('shipping_methods') as $method) {

            $methodType = $method['method_type'];
            $methodModel = ShippingMethod::firstOrCreate(['type' => $methodType]);

            switch ($methodType) {

                // if method type is not one of supported, return error
                default:
                    return response()->json(['message' => 'Invalid shipping method provided'], 400);
                case 'free_shipping':

                    if (!isset($method['has_minimum_order_amount'])) {
                        return response()->json(['message' => 'has_minimum_order_amount is required for free_shipping method'], 400);
                    }

                    // if it has minimum order amount, check if the amount is provided
                    if ($method['has_minimum_order_amount']) {
                        if (!isset($method['minimum_order_amount'])) {
                            return response()->json(['message' => 'minimum_order_amount is required for free_shipping method'], 400);
                        }
                    }

                    $methodsData[$methodModel->id] = [
                        'has_minimum_order_amount' => $method['has_minimum_order_amount'] ?? false,
                        'minimum_order_amount' => $method['minimum_order_amount'] ?? 0
                    ];
                    break;

                case 'flat_rate':
                    $methodsData[$methodModel->id] = [
                        'flat_rate_cost' => $method['flast_rate_cost'] ?? 0
                    ];
                    break;
            }
        }

        $zone = ShippingZone::create([
            'zone_name' => $request->input('zone_name'),
            'region' => $request->input('region'),
            'venue_id' => $venue->id,
        ]);
        $zone->shippingMethods()->sync($methodsData);


        return response()->json(['message' => 'Shipping zone created successfully', 'zone' => $zone]);
    }

    public function deleteShippingZone($zoneId): \Illuminate\Http\JsonResponse
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

        $zone = ShippingZone::where('id', $zoneId)->where('venue_id', $venue->id)->first();
        if (!$zone) {
            return response()->json(['message' => 'The requested zone does not exist'], 404);
        }

        // Detach the relationships with shipping methods
        $zone->shippingMethods()->detach();

        // Delete the zone itself
        $zone->delete();

        return response()->json(['message' => 'Shipping zone deleted successfully']);

    }


}
