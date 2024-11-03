<?php

namespace App\Http\Controllers\v3;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\EcommercePlatform;
use App\Models\InventorySync;
use App\Models\InventorySyncError;
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

/**
 * @OA\Tag(
 *     name="Inventory Reports",
 *     description="API endpoints for inventory reporting and analytics"
 * )
 *
 * @OA\Parameter(
 *     parameter="venue_short_code",
 *     name="venue_short_code",
 *     in="query",
 *     required=true,
 *     @OA\Schema(type="string"),
 *     description="Venue short code for filtering data"
 * )
 *
 * @OA\Parameter(
 *     parameter="brand_id",
 *     name="brand_id",
 *     in="query",
 *     required=true,
 *     @OA\Schema(type="integer"),
 *     description="Brand ID for filtering data"
 * )
 *
 * @OA\Parameter(
 *     parameter="report_type",
 *     name="type",
 *     in="query",
 *     @OA\Schema(
 *         type="string",
 *         enum={"daily", "weekly", "monthly", "yearly", "total"},
 *         default="total"
 *     ),
 *     description="Report aggregation type"
 * )
 */

/**
 * @OA\Tag(
 *     name="Inventory Reports",
 *     description="API endpoints for inventory reporting and analytics"
 * )
 *
 * @OA\Parameter(
 *     parameter="venue_short_code",
 *     name="venue_short_code",
 *     in="query",
 *     required=true,
 *     @OA\Schema(type="string"),
 *     description="Venue short code for filtering data"
 * )
 *
 * @OA\Parameter(
 *     parameter="brand_id",
 *     name="brand_id",
 *     in="query",
 *     required=true,
 *     @OA\Schema(type="integer"),
 *     description="Brand ID for filtering data"
 * )
 *
 * @OA\Parameter(
 *     parameter="report_type",
 *     name="type",
 *     in="query",
 *     @OA\Schema(
 *         type="string",
 *         enum={"daily", "weekly", "monthly", "yearly", "total"},
 *         default="total"
 *     ),
 *     description="Report aggregation type"
 * )
 *
 * @OA\Schema(
 *     schema="InventoryData",
 *     @OA\Property(
 *         property="products",
 *         type="object",
 *         @OA\Property(property="total", type="integer"),
 *         @OA\Property(property="active", type="integer")
 *     ),
 *     @OA\Property(
 *         property="categories",
 *         type="object",
 *         @OA\Property(property="total", type="integer"),
 *         @OA\Property(property="active", type="integer")
 *     ),
 *     @OA\Property(
 *         property="collections",
 *         type="object",
 *         @OA\Property(property="total", type="integer"),
 *         @OA\Property(property="active", type="integer")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="LocationsSummary",
 *     @OA\Property(
 *         property="warehouses",
 *         type="object",
 *         @OA\Property(property="total", type="integer"),
 *         @OA\Property(property="active", type="integer")
 *     ),
 *     @OA\Property(
 *         property="physical_stores",
 *         type="object",
 *         @OA\Property(property="total", type="integer"),
 *         @OA\Property(property="active", type="integer")
 *     ),
 *     @OA\Property(
 *         property="ecommerce_sites",
 *         type="object",
 *         @OA\Property(property="total", type="integer"),
 *         @OA\Property(property="active", type="integer")
 *     )
 * )
 */
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
            'brand_id' => 'nullable|exists:brands,id',
            'type' => 'nullable|in:daily,weekly,monthly,yearly', // Validate the 'type' parameter
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        return ['venue' => $venue, 'brand_id' => $request->brand_id, 'type' => $request->type ?? 'total'];
    }

    /**
     * @OA\Get(
     *     path="/inventory-reports/orders-by-brand",
     *     tags={"Inventory Reports"},
     *     summary="Get orders by brand",
     *     @OA\Parameter(ref="#/components/parameters/venue_short_code"),
     *     @OA\Parameter(ref="#/components/parameters/brand_id"),
     *     @OA\Parameter(ref="#/components/parameters/report_type"),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="nr_orders", type="integer"),
     *             @OA\Property(property="total_amount_eur", type="number"),
     *             @OA\Property(property="subtotal_eur", type="number"),
     *             @OA\Property(property="total_discount_used_eur", type="number"),
     *             @OA\Property(property="total_coupon_used_eur", type="number")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/inventory-reports/orders-by-brand-and-country",
     *     tags={"Inventory Reports"},
     *     summary="Get orders by brand and country",
     *     @OA\Parameter(ref="#/components/parameters/venue_short_code"),
     *     @OA\Parameter(ref="#/components/parameters/brand_id"),
     *     @OA\Parameter(ref="#/components/parameters/report_type"),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="country", type="string"),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="period", type="string"),
     *                         @OA\Property(property="order_count", type="integer")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
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


    /**
     * @OA\Get(
     *     path="/inventory-reports/orders-by-brand-and-city",
     *     tags={"Inventory Reports"},
     *     summary="Get orders by brand and city",
     *     description="Retrieves order statistics grouped by cities in Albania",
     *     @OA\Parameter(ref="#/components/parameters/venue_short_code"),
     *     @OA\Parameter(ref="#/components/parameters/brand_id"),
     *     @OA\Parameter(ref="#/components/parameters/report_type"),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="city", type="string"),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="period", type="string"),
     *                         @OA\Property(property="order_count", type="integer")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
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


    /**
     * @OA\Get(
     *     path="/inventory-reports/inventory-data",
     *     tags={"Inventory Reports"},
     *     summary="Get inventory data summary",
     *     description="Retrieves summary of products, categories, and collections",
     *     @OA\Parameter(ref="#/components/parameters/venue_short_code"),
     *     @OA\Parameter(ref="#/components/parameters/brand_id"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="products",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="active", type="integer")
     *             ),
     *             @OA\Property(
     *                 property="categories",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="active", type="integer")
     *             ),
     *             @OA\Property(
     *                 property="collections",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="active", type="integer")
     *             )
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/inventory-reports/locations",
     *     tags={"Inventory Reports"},
     *     summary="Get locations summary",
     *     description="Retrieves summary of warehouses, physical stores, and ecommerce sites",
     *     @OA\Parameter(ref="#/components/parameters/venue_short_code"),
     *     @OA\Parameter(ref="#/components/parameters/brand_id"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="warehouses",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="active", type="integer")
     *             ),
     *             @OA\Property(
     *                 property="physical_stores",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="active", type="integer")
     *             ),
     *             @OA\Property(
     *                 property="ecommerce_sites",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="active", type="integer")
     *             )
     *         )
     *     )
     * )
     */
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


    /**
     * @OA\Get(
     *     path="/inventory-reports/upcoming-launches",
     *     tags={"Inventory Reports"},
     *     summary="Get upcoming product launches",
     *     description="Retrieves list of upcoming product launches",
     *     @OA\Parameter(ref="#/components/parameters/venue_short_code"),
     *     @OA\Parameter(ref="#/components/parameters/brand_id"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="category", type="string"),
     *                 @OA\Property(property="launch_date", type="string", format="date"),
     *                 @OA\Property(property="initial_stock", type="integer"),
     *                 @OA\Property(property="pre_orders", type="integer")
     *             )
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/inventory-reports/inventory-distribution",
     *     tags={"Inventory Reports"},
     *     summary="Get inventory distribution",
     *     description="Retrieves inventory distribution across physical stores",
     *     @OA\Parameter(ref="#/components/parameters/venue_short_code"),
     *     @OA\Parameter(ref="#/components/parameters/brand_id"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="location", type="string"),
     *                 @OA\Property(property="value", type="integer")
     *             )
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/inventory-reports/channel-performance",
     *     tags={"Inventory Reports"},
     *     summary="Get channel performance",
     *     description="Retrieves performance metrics for different sales channels",
     *     @OA\Parameter(ref="#/components/parameters/venue_short_code"),
     *     @OA\Parameter(ref="#/components/parameters/brand_id"),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="channel", type="string"),
     *                 @OA\Property(property="sales_volume", type="integer"),
     *                 @OA\Property(property="revenue", type="number"),
     *                 @OA\Property(property="growth", type="number")
     *             )
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/inventory-reports/data-quality",
     *     tags={"Inventory Reports"},
     *     summary="Get data quality score",
     *     description="Retrieves data quality metrics for inventory data",
     *     @OA\Parameter(ref="#/components/parameters/venue_short_code"),
     *     @OA\Parameter(ref="#/components/parameters/brand_id"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="overallScore", type="integer"),
     *             @OA\Property(
     *                 property="factors",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="score", type="integer")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/inventory-reports/all-report-data",
     *     tags={"Inventory Reports"},
     *     summary="Get all report data",
     *     description="Retrieves comprehensive report including all inventory metrics",
     *     @OA\Parameter(ref="#/components/parameters/venue_short_code"),
     *     @OA\Parameter(ref="#/components/parameters/brand_id"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="inventory_data", ref="#/components/schemas/InventoryData"),
     *             @OA\Property(property="locations", ref="#/components/schemas/LocationsSummary"),
     *             @OA\Property(
     *                 property="sync_status",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="venue_id", type="integer"),
     *                     @OA\Property(property="sync_type", type="integer"),
     *                     @OA\Property(property="method", type="string"),
     *                     @OA\Property(property="third_party", type="string"),
     *                     @OA\Property(property="created_at", type="string", format="datetime"),
     *                     @OA\Property(property="completed_at", type="string", format="datetime", nullable=true)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="upcoming_launches",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="category", type="string"),
     *                     @OA\Property(property="launch_date", type="string", format="date"),
     *                     @OA\Property(property="initial_stock", type="integer"),
     *                     @OA\Property(property="pre_orders", type="integer")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="inventory_distribution",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="location", type="string"),
     *                     @OA\Property(property="value", type="integer")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="channel_performance",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="channel", type="string"),
     *                     @OA\Property(property="sales_volume", type="integer"),
     *                     @OA\Property(property="revenue", type="number"),
     *                     @OA\Property(property="growth", type="number")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="sync_health",
     *                 type="object",
     *                 @OA\Property(property="overview", type="object",
     *                     @OA\Property(property="total_syncs", type="integer"),
     *                     @OA\Property(property="successful_syncs", type="integer"),
     *                     @OA\Property(property="failed_syncs", type="integer"),
     *                     @OA\Property(property="success_rate", type="number"),
     *                     @OA\Property(property="average_duration", type="string")
     *                 ),
     *                 @OA\Property(property="by_type", type="array",
     *                     @OA\Items(type="object")
     *                 ),
     *                 @OA\Property(property="error_summary", type="array",
     *                     @OA\Items(type="object")
     *                 ),
     *                 @OA\Property(property="performance_trend", type="array",
     *                     @OA\Items(type="object")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="data_quality",
     *                 type="object",
     *                 @OA\Property(property="overallScore", type="integer"),
     *                 @OA\Property(
     *                     property="factors",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="score", type="integer")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/synchronizations",
     *     tags={"Synchronizations"},
     *     summary="Get all synchronizations",
     *     @OA\Parameter(
     *         name="venue_short_code",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="date", type="string", example="2024-07-15"),
     *                 @OA\Property(property="time_completed", type="string", example="16:40 PM"),
     *                 @OA\Property(property="type", type="object",
     *                     @OA\Property(property="name", type="string", example="Prices Sync"),
     *                     @OA\Property(property="color", type="string", example="#cd8438")
     *                 ),
     *                 @OA\Property(property="method", type="string", example="Manual - API"),
     *                 @OA\Property(property="errors_count", type="integer", example=0),
     *                 @OA\Property(property="third_party", type="string", example="BookMaster"),
     *                 @OA\Property(property="status", type="string", example="completed")
     *             )
     *         )
     *     )
     * )
     */
    public function getSyncronizations(Request $request): \Illuminate\Http\JsonResponse
    {
        $validation = $this->validateRequest($request);

        if ($validation instanceof \Illuminate\Http\JsonResponse) {
            return $validation;
        }

        $venue = $validation['venue'];
        $perPage = $request->input('per_page', 15);
        $type = $request->input('sync_type');

        $query = InventorySynchronization::with(['syncType', 'errors'])
            ->where('venue_id', $venue->id);

        if ($type) {
            $syncTypeId = InventorySync::where('slug', $type)->value('id');
            $query->where('sync_type', $syncTypeId);
        }

        $query->orderBy('created_at', 'desc');
        $syncs = $query->paginate($perPage);

        $formattedSyncs = $syncs->map(function ($sync) {
            $completedAt = $sync->completed_at ? Carbon::parse($sync->completed_at) : null;

            return [
                'date' => $sync->created_at->format('Y-m-d'),
                'time_completed' => $completedAt ? $completedAt->format('H:i A') : null,
                'type' => [
                    'name' => $sync->syncType->name,
                    'color' => $this->getSyncTypeColor($sync->syncType->slug)
                ],
                'method' => $this->formatSyncMethod($sync->method),
                'errors_count' => $sync->errors->count(),
                'third_party' => $sync->third_party ?? 'None',
                'status' => $this->getSyncStatus($sync),
                'id' => $sync->id
            ];
        });

        return response()->json([
            'synchronizations' => [
                'current_page' => $syncs->currentPage(),
                'data' => $formattedSyncs,
                'first_page_url' => $syncs->url(1),
                'from' => $syncs->firstItem(),
                'last_page' => $syncs->lastPage(),
                'last_page_url' => $syncs->url($syncs->lastPage()),
                'next_page_url' => $syncs->nextPageUrl(),
                'path' => $syncs->path(),
                'per_page' => $syncs->perPage(),
                'prev_page_url' => $syncs->previousPageUrl(),
                'to' => $syncs->lastItem(),
                'total' => $syncs->total(),
            ],
            'current_page' => $syncs->currentPage(),
            'per_page' => $syncs->perPage(),
            'total' => $syncs->total(),
            'total_pages' => $syncs->lastPage(),
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/synchronizations/{sync}/errors",
     *     tags={"Synchronizations"},
     *     summary="Get errors for a specific sync",
     *     @OA\Parameter(
     *         name="sync",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="message", type="string"),
     *                 @OA\Property(property="created_at", type="string"),
     *                 @OA\Property(property="context", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function getSyncErrors(Request $request, int $syncId): \Illuminate\Http\JsonResponse
    {
        $validation = $this->validateRequest($request);
        if (isset($validation['error'])) {
            return $validation['error'];
        }

        $errors = InventorySyncError::where('synchronization_id', $syncId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($error) {
                return [
                    'type' => $error->error_type,
                    'message' => $error->error_message,
                    'created_at' => $error->created_at->format('Y-m-d H:i:s'),
                    'context' => $error->error_context
                ];
            });

        return response()->json($errors);
    }

    private function getSyncTypeColor(string $type): string
    {
        return [
            'price-sync' => '#cd8438',
            'sku-sync' => '#F1C332',
            'stock-sync' => '#240b3b',
            'sales-sync' => '#D3D3D3'
        ][$type] ?? '#gray';
    }

    private function formatSyncMethod(string $method): string
    {
        $type = str_contains($method, 'api') ? 'API' : 'Staff';
        $mode = str_contains($method, 'manual') || str_contains($method, 'csv_import')  ? 'Manual' : 'Automatic';

        return "$mode - $type";
    }

    /**
     * @OA\Get(
     *     path="/synchronizations/health",
     *     tags={"Synchronizations"},
     *     summary="Get synchronization health metrics",
     *     @OA\Parameter(
     *         name="venue_short_code",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="date_range",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"24h", "7d", "30d"}, default="24h")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="overview", type="object",
     *                 @OA\Property(property="total_syncs", type="integer", example=150),
     *                 @OA\Property(property="successful_syncs", type="integer", example=142),
     *                 @OA\Property(property="failed_syncs", type="integer", example=8),
     *                 @OA\Property(property="success_rate", type="number", format="float", example=94.67),
     *                 @OA\Property(property="average_duration", type="string", example="2m 30s")
     *             ),
     *             @OA\Property(property="by_type", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="type", type="string", example="Price Sync"),
     *                     @OA\Property(property="total", type="integer", example=50),
     *                     @OA\Property(property="successful", type="integer", example=48),
     *                     @OA\Property(property="failed", type="integer", example=2),
     *                     @OA\Property(property="success_rate", type="number", example=96),
     *                     @OA\Property(property="average_duration", type="string", example="1m 45s"),
     *                     @OA\Property(property="last_sync_status", type="string", example="successful"),
     *                     @OA\Property(property="last_sync_time", type="string", example="2024-03-27 14:30:00")
     *                 )
     *             ),
     *             @OA\Property(property="error_summary", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="type", type="string", example="API"),
     *                     @OA\Property(property="count", type="integer", example=5),
     *                     @OA\Property(property="percentage", type="number", example=62.5)
     *                 )
     *             ),
     *             @OA\Property(property="performance_trend", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="date", type="string", example="2024-03-27"),
     *                     @OA\Property(property="success_rate", type="number", example=95),
     *                     @OA\Property(property="average_duration", type="string", example="2m 15s"),
     *                     @OA\Property(property="total_syncs", type="integer", example=10)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getSyncHealth(Request $request): \Illuminate\Http\JsonResponse
    {
        $validation = $this->validateRequest($request);
        if (isset($validation['error'])) {
            return $validation['error'];
        }

        $venue = $validation['venue'];
        $dateRange = $request->get('date_range', '24h');

        // Calculate date range
        $startDate = match($dateRange) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24)
        };

        // Get syncs within date range
        $syncs = InventorySynchronization::with(['syncType', 'errors'])
            ->where('venue_id', $venue->id)
            ->where('created_at', '>=', $startDate)
            ->get();

        // Calculate overview metrics
        $overview = $this->calculateOverviewMetrics($syncs);

        // Calculate metrics by sync type
        $byType = $this->calculateMetricsByType($syncs);

        // Analyze errors
        $errorSummary = $this->analyzeErrors($syncs);

        // Calculate performance trend
        $performanceTrend = $this->calculatePerformanceTrend($syncs, $dateRange);

        return response()->json([
            'overview' => $overview,
            'by_type' => $byType,
            'error_summary' => $errorSummary,
            'performance_trend' => $performanceTrend
        ]);
    }

    private function calculateOverviewMetrics($syncs): array
    {
        $total = $syncs->count();
        $successful = $syncs->filter->isCompleted()->count();
        $failed = $syncs->filter->isFailed()->count();

        $avgDuration = $syncs
            ->filter->isCompleted()
            ->map(function ($sync) {
                return $sync->completed_at->diffInSeconds($sync->created_at);
            })
            ->average();

        return [
            'total_syncs' => $total,
            'successful_syncs' => $successful,
            'failed_syncs' => $failed,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'average_duration' => $this->formatDuration($avgDuration)
        ];
    }

    private function calculateMetricsByType($syncs): array
    {
        $byType = [];
        $types = $syncs->groupBy('sync_type');

        foreach ($types as $typeId => $typeSyncs) {
            $syncType = $typeSyncs->first()->syncType;
            $total = $typeSyncs->count();
            $successful = $typeSyncs->filter->isCompleted()->count();

            $avgDuration = $typeSyncs
                ->filter->isCompleted()
                ->map(function ($sync) {
                    return $sync->completed_at->diffInSeconds($sync->created_at);
                })
                ->average();

            $lastSync = $typeSyncs->sortByDesc('created_at')->first();

            $byType[] = [
                'type' => $syncType->name,
                'total' => $total,
                'successful' => $successful,
                'failed' => $total - $successful,
                'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
                'average_duration' => $this->formatDuration($avgDuration),
                'last_sync_status' => $this->getSyncStatus($lastSync),
                'last_sync_time' => $lastSync->created_at->format('Y-m-d H:i:s')
            ];
        }

        return $byType;
    }

    private function analyzeErrors($syncs): array
    {
        $errors = $syncs->pluck('errors')->flatten();
        $totalErrors = $errors->count();

        $errorTypes = $errors->groupBy('error_type')
            ->map(function ($typeErrors) use ($totalErrors) {
                $count = $typeErrors->count();
                return [
                    'count' => $count,
                    'percentage' => $totalErrors > 0 ? round(($count / $totalErrors) * 100, 2) : 0
                ];
            });

        return $errorTypes->map(function ($data, $type) {
            return [
                'type' => $type,
                'count' => $data['count'],
                'percentage' => $data['percentage']
            ];
        })->values()->toArray();
    }

    private function calculatePerformanceTrend($syncs, $dateRange): array
    {
        $grouping = match($dateRange) {
            '30d' => 'Y-m-d',
            '7d' => 'Y-m-d',
            default => 'Y-m-d H:00' // Hourly for 24h
        };

        return $syncs->groupBy(function ($sync) use ($grouping) {
            return $sync->created_at->format($grouping);
        })->map(function ($daySyncs) {
            $total = $daySyncs->count();
            $successful = $daySyncs->filter->isCompleted()->count();

            $avgDuration = $daySyncs
                ->filter->isCompleted()
                ->map(function ($sync) {
                    return $sync->completed_at->diffInSeconds($sync->created_at);
                })
                ->average();

            return [
                'date' => $daySyncs->first()->created_at->format('Y-m-d H:i:s'),
                'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
                'average_duration' => $this->formatDuration($avgDuration),
                'total_syncs' => $total
            ];
        })->values()->toArray();
    }

    private function formatDuration(?float $seconds): string
    {
        if (!$seconds) return '0s';

        $minutes = floor($seconds / 60);
        $remainingSeconds = round($seconds % 60);

        if ($minutes > 0) {
            return "{$minutes}m {$remainingSeconds}s";
        }

        return "{$remainingSeconds}s";
    }

    private function getSyncStatus($sync): string
    {
        if ($sync->isCompleted()) return 'successful';
        if ($sync->isFailed()) return 'failed';
        return 'in_progress';
    }
}
