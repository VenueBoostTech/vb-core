<?php

namespace App\Http\Controllers\v3;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\EcommercePlatform;
use App\Models\InventorySynchronization;
use App\Models\InventoryWarehouse;
use App\Models\PhysicalStore;
use App\Models\Product;
use App\Models\StoreInventory;
use App\Models\Variation;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Validator;
use App\Models\Country;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryReportController extends Controller
{
    private function validateRequest(Request $request): \Illuminate\Http\JsonResponse|array
    {
        // Check if user has associated restaurants
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        // Get and validate venue short code
        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // Validate other request parameters
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'brand_id' => 'required|exists:brands,id',
            'type' => 'in:daily,weekly,monthly,yearly', // Validate the 'type' parameter
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        return ['venue' => $venue, 'brand_id' => $request->brand_id, 'type' => $request->type ?? 'total'];
    }

    public function ordersByBrand(Request $request): \Illuminate\Http\JsonResponse
    {
        $validation = $this->validateRequest($request);
        if (isset($validation['error'])) {
            return $validation['error'];
        }

        $venue = $validation['venue'];
        $brandId = $validation['brand_id'];
        $type = $request->get('type', 'total'); // Default to 'total' if not provided

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $query = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('restaurant_id', $venue->id)
            ->whereHas('orderProducts.product', function ($query) use ($brandId) {
                $query->where('brand_id', $brandId);
            });

        $totalOrders = $query->count();
        $totalAmount = $query->sum('total_amount_eur');
        $subtotal = $query->sum('subtotal_eur');
        $discountTotal = $query->sum('discount_total');
        $couponTotal = DB::table('order_coupons')
            ->whereIn('order_id', $query->pluck('id'))
            ->sum('discount_value_eur');

        if ($type === 'total') {
            return response()->json([
                'nr_orders' => $totalOrders,
                'total_amount_eur' => $totalAmount,
                'subtotal_eur' => $subtotal,
                'total_discount_used_eur' => $discountTotal,
                'total_coupon_used_eur' => $couponTotal,
            ]);
        }

        // If type is not 'total', proceed with period-based aggregation
        $groupBy = $this->getGroupByClause($type);
        $orders = $query->select(
            DB::raw("$groupBy as period"),
            DB::raw('COUNT(*) as nr_orders'),
            DB::raw('SUM(total_amount_eur) as total_amount_eur'),
            DB::raw('SUM(subtotal_eur) as subtotal_eur'),
            DB::raw('SUM(discount_total) as total_discount_used_eur')
        )
            ->groupBy(DB::raw($groupBy))
            ->get();

        $result = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $periodKey = $currentDate->format($this->getDateFormat($type));
            $orderData = $orders->firstWhere('period', $periodKey);

            $result[] = [
                'period' => $periodKey,
                'nr_orders' => $orderData ? $orderData->nr_orders : 0,
                'total_amount_eur' => $orderData ? $orderData->total_amount_eur : 0,
                'subtotal_eur' => $orderData ? $orderData->subtotal_eur : 0,
                'total_discount_used_eur' => $orderData ? $orderData->total_discount_used_eur : 0,
                'total_coupon_used_eur' => $this->getCouponTotalForPeriod($brandId, $periodKey, $type),
            ];

            $currentDate = $this->incrementDate($currentDate, $type);
        }

        return response()->json($result);
    }

    private function getGroupByClause($type)
    {
        switch ($type) {
            case 'daily':
                return 'DATE(created_at)';
            case 'weekly':
                return 'YEARWEEK(created_at)';
            case 'monthly':
                return 'DATE_FORMAT(created_at, "%Y-%m")';
            case 'yearly':
                return 'YEAR(created_at)';
            default:
                return 'DATE(created_at)';
        }
    }

    private function getDateFormat($type)
    {
        switch ($type) {
            case 'daily':
                return 'Y-m-d';
            case 'weekly':
                return 'Y-\WW';
            case 'monthly':
                return 'Y-m';
            case 'yearly':
                return 'Y';
            default:
                return 'Y-m-d';
        }
    }

    private function incrementDate($date, $type)
    {
        switch ($type) {
            case 'daily':
                return $date->addDay();
            case 'weekly':
                return $date->addWeek();
            case 'monthly':
                return $date->addMonth();
            case 'yearly':
                return $date->addYear();
            default:
                return $date->addDay();
        }
    }

    private function getCouponTotalForPeriod($brandId, $period, $type)
    {
        return DB::table('order_coupons')
            ->join('orders', 'order_coupons.order_id', '=', 'orders.id')
            ->join('order_products', 'orders.id', '=', 'order_products.order_id')
            ->join('products', 'order_products.product_id', '=', 'products.id')
            ->where('products.brand_id', $brandId)
            ->where($this->getPeriodCondition($type), $period)
            ->sum('order_coupons.discount_value_eur');
    }

    private function getPeriodCondition($type)
    {
        switch ($type) {
            case 'daily':
                return DB::raw('DATE(orders.created_at)');
            case 'weekly':
                return DB::raw('YEARWEEK(orders.created_at)');
            case 'monthly':
                return DB::raw('DATE_FORMAT(orders.created_at, "%Y-%m")');
            case 'yearly':
                return DB::raw('YEAR(orders.created_at)');
            default:
                return DB::raw('DATE(orders.created_at)');
        }
    }

    public function ordersByBrandAndCountry(Request $request): \Illuminate\Http\JsonResponse
    {
        $validation = $this->validateRequest($request);
        if (isset($validation['error'])) {
            return $validation['error'];
        }

        $venue = $validation['venue'];
        $brandId = $validation['brand_id'];
        $type = $request->get('type', 'total'); // Default to 'total' if not provided

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        // Fetch brand details to check if the name includes "Swarovski"
        $brand = $venue->brands->where('id', $brandId)->first();

        if (!$brand) {
            return response()->json(['error' => 'Brand not found'], 404);
        }

        $isSwarovski = stripos($brand->name, 'Swarovski') !== false;

        // Get all countries
        $allCountries = Country::pluck('name', 'id')->toArray();

        // Define the base query with table aliases
        $query = DB::table('countries as c')
            ->leftJoin('addresses as a', 'c.id', '=', 'a.country_id')
            ->leftJoin('orders as o', 'a.id', '=', 'o.address_id')
            ->leftJoin('order_products as op', 'o.id', '=', 'op.order_id')
            ->leftJoin('products as p', 'op.product_id', '=', 'p.id')
            ->select('c.id as country_id', 'c.name as country', DB::raw('DATE(o.created_at) as period'), DB::raw('COUNT(o.id) as order_count'))
            ->whereBetween('o.created_at', [$startDate, $endDate])
            ->where('p.brand_id', $brandId)
            ->groupBy('c.id', 'c.name', DB::raw('DATE(o.created_at)'));

        // If the brand name includes "Swarovski", filter to return only Albania
        if ($isSwarovski) {
            $albaniaId = Country::where('name', 'Albania')->value('id');

            if (!$albaniaId) {
                return response()->json(['error' => 'Albania not found in the database'], 404);
            }

            $query->where('c.id', $albaniaId);
        }

        // Modify the query based on the type of aggregation
        switch ($type) {
            case 'daily':
                $query->groupBy('c.id', 'c.name', DB::raw('DATE(o.created_at)'));
                break;
            case 'weekly':
                $query->select('c.id as country_id', 'c.name as country', DB::raw('YEARWEEK(o.created_at) as period'), DB::raw('COUNT(o.id) as order_count'))
                    ->groupBy('c.id', 'c.name', DB::raw('YEARWEEK(o.created_at)'));
                break;
            case 'monthly':
                $query->select('c.id as country_id', 'c.name as country', DB::raw('YEAR(o.created_at) as year, MONTH(o.created_at) as month'), DB::raw('COUNT(o.id) as order_count'))
                    ->groupBy('c.id', 'c.name', DB::raw('YEAR(o.created_at)'), DB::raw('MONTH(o.created_at)'));
                break;
            case 'yearly':
                $query->select('c.id as country_id', 'c.name as country', DB::raw('YEAR(o.created_at) as year'), DB::raw('COUNT(o.id) as order_count'))
                    ->groupBy('c.id', 'c.name', DB::raw('YEAR(o.created_at)'));
                break;
        }

        $ordersByCountry = $query->get();

        // Prepare data for all periods within the date range
        $periods = [];
        foreach ($allCountries as $countryId => $countryName) {
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $periodKey = $currentDate->format($this->getDateFormat($type));
                $periods[$countryId][$periodKey] = 0;
                $currentDate->add($this->getDateInterval($type));
            }
        }

        foreach ($ordersByCountry as $order) {
            $periods[$order->country_id][$order->period] = $order->order_count;
        }

        $result = [];
        foreach ($allCountries as $countryId => $countryName) {
            $countryData = array_map(function ($period, $count) {
                return [
                    'period' => $period,
                    'order_count' => $count
                ];
            }, array_keys($periods[$countryId]), $periods[$countryId]);

            $result[] = [
                'country' => $countryName,
                'data' => $countryData
            ];
        }

        // Filter result to only Albania if the brand is Swarovski
        if ($isSwarovski) {
            $result = array_filter($result, function ($item) {
                return $item['country'] === 'Albania';
            });
        }

        return response()->json($result);
    }


    public function ordersByBrandAndCity(Request $request): \Illuminate\Http\JsonResponse
    {
        $validation = $this->validateRequest($request);
        if (isset($validation['error'])) {
            return $validation['error'];
        }

        $venue = $validation['venue'];
        $brandId = $validation['brand_id'];
        $type = $request->get('type', 'total'); // Default to 'total' if not provided
        $type = $validation['type'];

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $albania = Country::where('name', 'Albania')->first();

        if (!$albania) {
            return response()->json(['error' => 'Albania not found in the database'], 404);
        }

        // Get all cities in Albania
        $cities = DB::table('cities')
            ->join('states', 'cities.states_id', '=', 'states.id')
            ->where('states.country_id', $albania->id)
            ->select('cities.id', 'cities.name')
            ->get();

        // Define the base query with table aliases
        $query = DB::table('orders as o')
            ->join('addresses as a', 'o.address_id', '=', 'a.id')
            ->leftJoin('order_products as op', 'o.id', '=', 'op.order_id')
            ->leftJoin('products as p', 'op.product_id', '=', 'p.id')
            ->select('a.city_id', DB::raw('DATE(o.created_at) as period'), DB::raw('COUNT(o.id) as order_count'))
            ->whereBetween('o.created_at', [$startDate, $endDate])
            ->where('o.restaurant_id', $venue->id)
            ->where('p.brand_id', $brandId)
            ->groupBy('a.city_id', DB::raw('DATE(o.created_at)'));

        // Modify the query based on the type of aggregation
        switch ($type) {
            case 'daily':
                $query->groupBy('a.city_id', DB::raw('DATE(o.created_at)'));
                break;
            case 'weekly':
                $query->select('a.city_id', DB::raw('YEARWEEK(o.created_at) as period'), DB::raw('COUNT(o.id) as order_count'))
                    ->groupBy('a.city_id', DB::raw('YEARWEEK(o.created_at)'));
                break;
            case 'monthly':
                $query->select('a.city_id', DB::raw('YEAR(o.created_at) as year, MONTH(o.created_at) as month'), DB::raw('COUNT(o.id) as order_count'))
                    ->groupBy('a.city_id', DB::raw('YEAR(o.created_at)'), DB::raw('MONTH(o.created_at)'));
                break;
            case 'yearly':
                $query->select('a.city_id', DB::raw('YEAR(o.created_at) as year'), DB::raw('COUNT(o.id) as order_count'))
                    ->groupBy('a.city_id', DB::raw('YEAR(o.created_at)'));
                break;
        }

        $ordersByCity = $query->get();

        // Prepare data for all periods within the date range
        $periods = [];
        foreach ($cities as $city) {
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $periodKey = $currentDate->format($this->getDateFormat($type));
                $periods[$city->id][$periodKey] = 0;
                $currentDate->add($this->getDateInterval($type));
            }
        }

        foreach ($ordersByCity as $order) {
            $periods[$order->city_id][$order->period] = $order->order_count;
        }

        $result = [];
        foreach ($cities as $city) {
            $cityData = array_map(function ($period, $count) {
                return [
                    'period' => $period,
                    'order_count' => $count
                ];
            }, array_keys($periods[$city->id]), $periods[$city->id]);

            $result[] = [
                'city' => $city->name,
                'data' => $cityData
            ];
        }

        return response()->json($result);
    }


    private function getDateInterval($type)
    {
        switch ($type) {
            case 'daily':
                return '1 day';
            case 'weekly':
                return '1 week';
            case 'monthly':
                return '1 month';
            case 'yearly':
                return '1 year';
            default:
                return '1 day';
        }
    }


    public function getInventoryData(Request $request): \Illuminate\Http\JsonResponse
    {
        $validation = $this->validateRequest($request);
        if (isset($validation['error'])) {
            return $validation['error'];
        }

        $venue = $validation['venue'];
        $brandId = $validation['brand_id'];
        $type = $request->get('type', 'total'); // Default to 'total' if not provided

        $data = [
            'products' => [
                'total' => Product::where('brand_id', $brandId)->count(),
                'active' => Product::where('brand_id', $brandId)->where('available', 1)->count(),
            ],
            'categories' => [
                'total' => Category::where('restaurant_id', $venue->id)->count(),
                'active' => Category::where('restaurant_id', $venue->id)->where('available', 1)->count(),
            ],
//            'variations' => [
//                'total' => Variation::whereHas('product', function ($query) use ($brandId) {
//                    $query->where('brand_id', $brandId);
//                })->count(),
//            ],
            'collections' => [
                'total' => Collection::where('venue_id', $venue->id)->count(),
                'active' => Collection::where('venue_id', $venue->id)->count(),
//                'active' => Collection::where('venue_id', $venue->id)->where('available', 1)->count(),
            ],
//            'groups' => [
//                'total' => Group::where('brand_id', $brandId)->count(),
//                'active' => Group::where('brand_id', $brandId)->where('status', 'active')->count(),
//            ],
        ];

        return response()->json($data);
    }

    public function getLocationsSummary(Request $request): \Illuminate\Http\JsonResponse
    {
        $validation = $this->validateRequest($request);
        if (isset($validation['error'])) {
            return $validation['error'];
        }

        $venue = $validation['venue'];
        $brandId = $validation['brand_id'];
        $type = $request->get('type', 'total'); // Default to 'total' if not provided

        $data = [
            'warehouses' => [
                'total' => InventoryWarehouse::where('venue_id', $venue->id)->count(),
                'active' => InventoryWarehouse::where('venue_id', $venue->id)->count(),
            ],
            'physical_stores' => [
                'total' => PhysicalStore::where('venue_id', $venue->id)->count(),
                'active' => PhysicalStore::where('venue_id', $venue->id)->count(),
            ],
            'ecommerce_sites' => [
                'total' => EcommercePlatform::where('venue_id', $venue->id)->count(),
                'active' => EcommercePlatform::where('venue_id', $venue->id)->count(),
            ],
        ];

        return response()->json($data);
    }

    public function getSyncStatus(Request $request)
    {
        $validation = $this->validateRequest($request);
        if (isset($validation['error'])) {
            return $validation['error'];
        }

        $venue = $validation['venue'];
        $brandId = $validation['brand_id'];
        $type = $request->get('type', 'total'); // Default to 'total' if not provided

        $syncStatus = InventorySynchronization::where('venue_id', $venue->id)
//            ->where('brand_id', $brandId)
//            ->where('entity_type', 'product
//            ->select('entity_type', 'status', DB::raw('COUNT(*) as count'))
//            ->groupBy('entity_type', 'status')
            ->get()
//            ->groupBy('entity_type')
            ->map(function ($group) {
                return $group->pluck('count', 'status');
            });

        return response()->json($syncStatus);
    }

    public function getUpcomingLaunches(Request $request): \Illuminate\Http\JsonResponse
    {
        $validation = $this->validateRequest($request);
        if (isset($validation['error'])) {
            return $validation['error'];
        }

        $venue = $validation['venue'];
        $brandId = $validation['brand_id'];
        $type = $request->get('type', 'total'); // Default to 'total' if not provided

        $now = Carbon::now();

        $upcomingLaunches = Product::where('brand_id', $brandId)
//            ->where('launch_date', '>', $now)
            ->with('category')
//            ->select('id', 'name', 'category_id', 'launch_date', 'initial_stock', 'pre_orders')
            ->select('id', 'title')
//            ->orderBy('launch_date')
            ->get()
            ->map(function ($product) {
                return [
                    'title' => $product->title,
                    'category' => $product->category->name,
//                    'launch_date' => $product->launch_date->format('Y-m-d'),
                    'launch_date' => '2024-12-31', // '2024-12-31' is a placeholder for 'launch_date' as it is not available in the 'products' table
//                    'initial_stock' => $product->initial_stock,
                    'initial_stock' => 0, // '0' is a placeholder for 'initial_stock' as it is not available in the 'products' table
                    'pre_orders' => 0,
//                    'pre_orders' => $product->pre_orders
                ];
            });

        return response()->json($upcomingLaunches);
    }

    public function getInventoryDistribution(Request $request)
    {
        $validation = $this->validateRequest($request);
        if (isset($validation['error'])) {
            return $validation['error'];
        }

        $venue = $validation['venue'];
        $brandId = $validation['brand_id'];
        $type = $request->get('type', 'total'); // Default to 'total' if not provided

        $distribution = StoreInventory::join('products', 'store_inventories.product_id', '=', 'products.id')
            ->join('physical_stores', 'store_inventories.physical_store_id', '=', 'physical_stores.id')
            ->where('products.brand_id', $brandId)
            ->select('physical_stores.name as location', DB::raw('SUM(store_inventories.quantity) as value'))
            ->groupBy('physical_stores.id', 'physical_stores.name')
            ->get();

        return response()->json($distribution);
    }

    public function getChannelPerformance(Request $request): \Illuminate\Http\JsonResponse
    {
        $validation = $this->validateRequest($request);
        if (isset($validation['error'])) {
            return $validation['error'];
        }

        $venue = $validation['venue'];
        $brandId = $validation['brand_id'];
        $type = $request->get('type', 'total'); // Default to 'total' if not provided

        $startDate = $request->input('start_date', now()->subMonth());
        $endDate = $request->input('end_date', now());

//        $performance = Order::join('products', 'orders.product_id', '=', 'products.id')
//            ->where('products.brand_id', $brandId)
//            ->whereBetween('orders.created_at', [$startDate, $endDate])
//            ->select(
//                'orders.channel',
//                DB::raw('COUNT(*) as sales_volume'),
//                DB::raw('SUM(orders.total_amount) as revenue'),
//                DB::raw('(SUM(orders.total_amount) - LAG(SUM(orders.total_amount)) OVER (PARTITION BY orders.channel ORDER BY YEAR(orders.created_at), MONTH(orders.created_at))) / LAG(SUM(orders.total_amount)) OVER (PARTITION BY orders.channel ORDER BY YEAR(orders.created_at), MONTH(orders.created_at)) * 100 as growth')
//            )
//            ->groupBy('orders.channel')
//            ->get();

        // dummy data
        $performance = [
            [
                'channel' => 'Online',
                'sales_volume' => 100,
                'revenue' => 1000,
                'growth' => 10
            ],
            [
                'channel' => 'Offline',
                'sales_volume' => 50,
                'revenue' => 500,
                'growth' => 5
            ]
        ];

        return response()->json($performance);
    }

    public function getSyncHealth(Request $request): \Illuminate\Http\JsonResponse
    {
        $validation = $this->validateRequest($request);
        if (isset($validation['error'])) {
            return $validation['error'];
        }

        $venue = $validation['venue'];
        $brandId = $validation['brand_id'];
        $type = $request->get('type', 'total'); // Default to 'total' if not provided

//        $syncHealth = InventorySynchronization::where('brand_id', $brandId)
//            ->select(
//                'entity_type',
//                DB::raw('COUNT(*) as total'),
//                DB::raw('SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as synced')
//            )
//            ->groupBy('entity_type')
//            ->get()
//            ->map(function ($item) {
//                $item['syncedPercentage'] = ($item['total'] > 0) ? ($item['synced'] / $item['total']) * 100 : 0;
//                return $item;
//            });
//
//        $overallPercentage = $syncHealth->avg('syncedPercentage');

        // dummy data
        $syncHealth = [
            [
                'entity_type' => 'Product',
                'total' => 100,
                'synced' => 90,
                'syncedPercentage' => 90
            ],
            [
                'entity_type' => 'Store',
                'total' => 50,
                'synced' => 40,
                'syncedPercentage' => 80
            ]
        ];

        $overallPercentage = 85;

        return response()->json([
            'overallPercentage' => $overallPercentage,
            'categories' => $syncHealth
        ]);
    }

    public function getDataQualityScore(Request $request): \Illuminate\Http\JsonResponse
    {
        $validation = $this->validateRequest($request);
        if (isset($validation['error'])) {
            return $validation['error'];
        }

        $venue = $validation['venue'];
        $brandId = $validation['brand_id'];
        $type = $request->get('type', 'total'); // Default to 'total' if not provided

        $products = Product::where('brand_id', $brandId)->get();

//        $descriptionScore = $products->avg(function ($product) {
//            return strlen($product->description) > 100 ? 100 : strlen($product->description);
//        });
//
//        $stockAccuracyScore = StoreInventory::join('products', 'store_inventories.product_id', '=', 'products.id')
//                ->where('products.brand_id', $brandId)
//                ->whereRaw('ABS(store_inventories.quantity - products.stock) / products.stock <= 0.1')
//                ->count() / $products->count() * 100;
//
//        $pricingUpdatesScore = Product::where('brand_id', $brandId)
//                ->where('price_updated_at', '>', now()->subDays(30))
//                ->count() / $products->count() * 100;
//
//        $imageQualityScore = $products->avg(function ($product) {
//            return $product->image && $product->image->width >= 1000 && $product->image->height >= 1000 ? 100 : 0;
//        });
//
//        $factors = [
//            ['name' => 'Product Descriptions', 'score' => round($descriptionScore)],
//            ['name' => 'Stock Accuracy', 'score' => round($stockAccuracyScore)],
//            ['name' => 'Pricing Updates', 'score' => round($pricingUpdatesScore)],
//            ['name' => 'Image Quality', 'score' => round($imageQualityScore)]
//        ];
//
//        $overallScore = collect($factors)->avg('score');

        // dummy data
        $factors = [
            ['name' => 'Product Descriptions', 'score' => 80],
            ['name' => 'Stock Accuracy', 'score' => 90],
            ['name' => 'Pricing Updates', 'score' => 70],
            ['name' => 'Image Quality', 'score' => 100]
        ];

        $overallScore = 85;

        return response()->json([
            'overallScore' => round($overallScore),
            'factors' => $factors
        ]);
    }

    public function getAllReportData(Request $request): \Illuminate\Http\JsonResponse
    {
        $validation = $this->validateRequest($request);
        if (isset($validation['error'])) {
            return $validation['error'];
        }

        $venue = $validation['venue'];
        $brandId = $validation['brand_id'];
        $type = $request->get('type', 'total'); // Default to 'total' if not provided

        $data = [
            'inventory_data' => $this->getInventoryData($request)->original,
            'locations' => $this->getLocationsSummary($request)->original,
            'sync_status' => $this->getSyncStatus($request)->original,
            'upcoming_launches' => $this->getUpcomingLaunches($request)->original,
            'inventory_distribution' => $this->getInventoryDistribution($request)->original,
            'channel_performance' => $this->getChannelPerformance($request)->original,
            'sync_health' => $this->getSyncHealth($request)->original,
            'data_quality' => $this->getDataQualityScore($request)->original,
        ];

        return response()->json($data);
    }
}
