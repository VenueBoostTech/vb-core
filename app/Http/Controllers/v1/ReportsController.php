<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\TableReservations;
use App\Models\Waitlist;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use function response;

/**
 * @OA\Info(
 *   title="Reports API",
 *   version="1.0",
 *   description="This API allows use Reports Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Reports",
 *   description="Operations related to Reports"
 * )
 */
class ReportsController extends Controller
{
    /**
     * Generate the sales by product report.
     *
     * @return JsonResponse
     *
     * @OA\Get(
     *     path="/reports/sales-by-product-report",
     *     tags={"Reports"},
     *     summary="Generate the sales by product report",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         description="The product object",
     *                     ),
     *                     @OA\Property(
     *                         property="total_sales",
     *                         type="integer",
     *                         description="The total sales of the product"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function salesByProductReport(): JsonResponse
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

        $salesByProductReport = Product::whereHas('orderProducts', function ($query) use ($venue) {
            $query->whereHas('order', function ($query) use ($venue) {
                $query->where('restaurant_id', $venue->id);
            });
        })
            ->withCount(['orderProducts as total_sales' => function ($query) use ($venue) {
                $query->whereHas('order', function ($query) use ($venue) {
                    $query->where('restaurant_id', $venue->id);
                });
            }])
            ->get();

        $data = [];
        foreach ($salesByProductReport as $product) {
            $data[] = [
                'product' => $product,
                'total_sales' => $product->total_sales,
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Generate the inventory turnover report.
     *
     * @return JsonResponse
     *
     * @OA\Get(
     *     path="/reports/inventory-turnover-report",
     *     tags={"Reports"},
     *     summary="Generate the inventory turnover report",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         description="The product object"
     *                     ),
     *                     @OA\Property(
     *                         property="inventory_value",
     *                         type="decimal",
     *                         format="float",
     *                         description="The total value of the inventory for the product"
     *                     ),
     *                     @OA\Property(
     *                         property="turnover_rate",
     *                         type="decimal",
     *                         format="float",
     *                         description="The turnover rate of the inventory for the product"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function inventoryTurnoverReport(): JsonResponse
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

        $inventoryTurnoverReport = Product::whereHas('inventories', function ($query) use ($venue) {
            $query->where('restaurant_id', $venue->id);
        })
            ->with(['inventories' => function ($query) use ($venue) {
                $query->where('restaurant_id', $venue->id);
            }])
            ->get();

        $data = [];
        foreach ($inventoryTurnoverReport as $product) {
            $inventoryValue = $product->inventories->sum(function ($inventory) {
                return $inventory->pivot->quantity * $inventory->pivot->unit_cost;
            });

            $turnoverRate = ($inventoryValue != 0) ? $product->inventories->count() / $inventoryValue : 0;

            $data[] = [
                'product' => $product,
                'inventory_value' => $inventoryValue,
                'turnover_rate' => $turnoverRate,
            ];
        }

        return response()->json(['data' => $data]);
    }


    /**
     * Generate the stock aging report.
     *
     * @return JsonResponse
     *
     * @OA\Get(
     *     path="/reports/stock-aging-report",
     *     tags={"Reports"},
     *     summary="Generate the stock aging report",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         description="The product object"
     *                     ),
     *                     @OA\Property(
     *                         property="quantity",
     *                         type="integer",
     *                         description="The quantity of the product in stock"
     *                     ),
     *                     @OA\Property(
     *                         property="days_in_stock",
     *                         type="integer",
     *                         description="The number of days the product has been in stock"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function stockAgingReport(): JsonResponse
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

        $stockAgingReport = Product::whereHas('inventories', function ($query) use ($venue) {
            $query->where('restaurant_id', $venue->id);
        })
            ->with(['inventories' => function ($query) use ($venue) {
                $query->where('restaurant_id', $venue->id);
            }])
            ->get();

        $data = [];
        foreach ($stockAgingReport as $product) {
            foreach ($product->inventories as $inventory) {
                $daysInStock = $inventory->created_at->diffInDays(now());
                $data[] = [
                    'product' => $product,
                    'quantity' => $inventory->pivot->quantity,
                    'days_in_stock' => $daysInStock,
                ];
            }
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Generate the reorder point report.
     *
     * @return JsonResponse
     *
     * @OA\Get(
     *     path="/reports/reorder-point-report",
     *     tags={"Reports"},
     *     summary="Generate the reorder point report",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         description="The product object"
     *                     ),
     *                     @OA\Property(
     *                         property="current_quantity",
     *                         type="integer",
     *                         description="The current quantity of the product in inventory"
     *                     ),
     *                     @OA\Property(
     *                         property="reorder_point",
     *                         type="integer",
     *                         description="The reorder point of the product"
     *                     ),
     *                     @OA\Property(
     *                         property="needs_reorder",
     *                         type="boolean",
     *                         description="Indicates if the product needs to be reordered"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function reorderPointReport(): JsonResponse
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

        $reorderPointReport = Product::whereHas('inventories', function ($query) use ($venue) {
            $query->where('restaurant_id', $venue->id);
        })
            ->with(['inventories' => function ($query) use ($venue) {
                $query->where('restaurant_id', $venue->id);
            }])
            ->get();

        $data = [];
        foreach ($reorderPointReport as $product) {
            $inventory = $product->inventories->first();

            $currentQuantity = $inventory->pivot->quantity ?? 0;
            $reorderPoint = $inventory->pivot->reorder_point ?? 0;
            $needsReorder = $currentQuantity <= $reorderPoint;

            $data[] = [
                'product' => $product,
                'current_quantity' => $currentQuantity,
                'reorder_point' => $reorderPoint,
                'needs_reorder' => $needsReorder,
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Generate the cost of goods sold report.
     *
     * @return JsonResponse
     *
     * @OA\Get(
     *     path="/reports/cost-of-goods-sold",
     *     tags={"Reports"},
     *     summary="Generate the cost of goods sold report",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(
     *                         property="product",
     *                         type="string",
     *                         description="The name of the product"
     *                     ),
     *                     @OA\Property(
     *                         property="quantity_sold",
     *                         type="integer",
     *                         description="The total quantity of the product sold"
     *                     ),
     *                     @OA\Property(
     *                         property="total_cost",
     *                         type="number",
     *                         format="float",
     *                         description="The total cost of the product sold"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function costOfGoodsSoldReport(): JsonResponse
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

        $costOfGoodsSold = OrderProduct::whereHas('order', function ($query) use ($venue) {
            $query->where('restaurant_id', $venue->id);
        })
            ->with('product')
            ->select('product_id', DB::raw('SUM(product_quantity) as quantity_sold'), DB::raw('SUM(product_total_price) as total_cost'))
            ->groupBy('product_id')
            ->get();

        return response()->json(['data' => $costOfGoodsSold]);
    }



    /**
     * @OA\Get(
     *   path="/reports/waitlist",
     *   summary="Generate a report on waitlisted guests",
     *   tags={"Reports"},
     *   @OA\Response(
     *       response=200,
     *       description="Successful operation",
     *       @OA\JsonContent(
     *           type="object",
     *           @OA\Property(property="total_waitlisted", type="integer"),
     *           @OA\Property(property="total_seated", type="integer"),
     *           @OA\Property(property="total_left", type="integer"),
     *           @OA\Property(property="average_wait_time", type="integer")
     *       )
     *   ),
     * ),
     * @OA\Response(
     *   response=404,
     *   description="Waitlist guests not found",
     *  ),
     * )
     */
    public function generateWaitlistReport(): JsonResponse
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

        $waitlistedGuests = Waitlist::where('restaurant_id', $venue->id)->whereNotNull('arrival_time')->get();
        $seatedGuests = Waitlist::where('restaurant_id', $venue->id)->whereNotNull('seat_time')->get();
        $leftBeforeBeingSeated = Waitlist::where('restaurant_id', $venue->id)->whereNotNull('left_time')->get();

        $report = [
            'total_waitlisted' => $waitlistedGuests->count(),
            'total_seated' => $seatedGuests->count(),
            'total_left' => $leftBeforeBeingSeated->count(),
            'average_wait_time' => $this->calculateAverageWaitTime($seatedGuests)
        ];

        return response()->json($report);
    }

    private function calculateAverageWaitTime($seatedGuests)
    {
        $totalWaitTime = 0;
        $seatedGuests->transform(function ($guest) {
            $guest->arrival_time = Carbon::parse($guest->arrival_time);
            $guest->seat_time = Carbon::parse($guest->seat_time);
            return $guest;
        });

        foreach ($seatedGuests as $guest) {
            $totalWaitTime += $guest->seat_time->diffInMinutes($guest->arrival_time);
        }
        if ($seatedGuests->count() === 0) {
            return 0;
        }

        return round($totalWaitTime / $seatedGuests->count());
    }

    /**
     * @OA\Get(
     *     path="/reports/table-metrics",
     *     summary="View metrics and trends related to table management",
     *     tags={"Reports"},
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not Found"),
     *     security={
     *         {"api_key": {}}
     *     }
     * )
     */
    public function generateTableMetricsReport(): JsonResponse
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

        /// Retrieve table occupancy rates
        $occupancyRates = TableReservations::join('reservations', 'table_reservations.reservation_id', '=', 'reservations.id')
            ->selectRaw('SUM(CASE WHEN table_reservations.end_time is null THEN 1 ELSE 0 END) / COUNT(*) * 100 as occupancy_rate')
            ->where('reservations.restaurant_id', '=', $venue->id)
            ->first()
            ->occupancy_rate;

        // Retrieve revenue generated from tables
        $revenue = Order::where('restaurant_id', $venue->id)->whereHas('reservation.table_reservation', function($query) {
            $query->where('end_time', null);
        })->sum('total_amount');

        // Retrieve average table turn time
        // Retrieve average table turn time
        $tableTurnTimes = TableReservations::join('reservations', 'table_reservations.reservation_id', '=', 'reservations.id')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, table_reservations.start_time, table_reservations.end_time)) as avg_turn_time')
            ->where('reservations.restaurant_id', '=', $venue->id)
            ->first()
            ->avg_turn_time;

        return response()->json([
            'occupancy_rates' => $occupancyRates,
            'revenue' => $revenue,
            'average_table_turn_time' => $tableTurnTimes,
        ]);
    }

}
