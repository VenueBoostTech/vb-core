<?php

namespace App\Http\Controllers\v1;
use App\Enums\InventoryActivityCategory;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Carbon\Carbon;
use function response;

/**
 * @OA\Info(
 *   title="Analytics API",
 *   version="1.0",
 *   description="This API allows use Analytics Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Analytics",
 *   description="Operations related to Analytics"
 * )
 */
class AnalyticsController extends Controller
{
    /**
     * @OA\Get(
     *   path="/analytics/inventory-usage-over-time",
     *   tags={"Analytics"},
     *     summary="Get inventory usage over time",
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date (YYYY-MM-DD)",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date (YYYY-MM-DD)",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
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
     *                         property="category",
     *                         type="string",
     *                         description="The name of the inventory category"
     *                     ),
     *                     @OA\Property(
     *                         property="label",
     *                         type="string",
     *                         description="The label of the inventory"
     *                     ),
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(
     *                                 property="name",
     *                                 type="string",
     *                                 description="The name of the product"
     *                             ),
     *                             @OA\Property(
     *                                 property="total_quantity_used",
     *                                 type="integer",
     *                                 description="The total quantity of the product used within the specified time range"
     *                             ),
     *                             @OA\Property(
     *                                 property="times_used",
     *                                 type="integer",
     *                                 description="The number of times the product is used within the specified time range"
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function inventoryUsageOverTime(Request $request): \Illuminate\Http\JsonResponse
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
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $inventories = Inventory::where('restaurant_id', $venue->id)->with('categories', 'products')->get();

        $data = [];
        foreach ($inventories as $inventory) {
            $category = $inventory->categories->first();
            $products = $inventory->products;

            $productData = [];
            foreach ($products as $product) {
                $totalQuantityUsed = $product->inventoryActivities()
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->where('activity_category', InventoryActivityCategory::ORDER_SALE)
                    ->sum('quantity');

                $timesUsed = $product->inventoryActivities()
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->where('activity_category', InventoryActivityCategory::ORDER_SALE)
                    ->count();

                $productData[] = [
                    'name' => $product->title,
                    'total_quantity_usage' => $totalQuantityUsed,
                    'times_used' => $timesUsed,
                ];
            }

            $data[] = [
                'category' => $category->title,
                'label' => $inventory->label,
                'products' => $productData,
            ];
        }

        // return response
        return response()->json(['data' => $data]);
    }
}
