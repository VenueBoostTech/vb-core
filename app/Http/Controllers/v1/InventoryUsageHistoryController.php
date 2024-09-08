<?php
namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryActivity;
use App\Models\InventoryUsageHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function response;

/**
 * @OA\Info(
 *   title="Inventory Management API",
 *   version="1.0",
 *   description="This API allows for CRUD operations for Inventory Management Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Inventory",
 *   description="Operations related to Inventory Management"
 * )
 */

class InventoryUsageHistoryController extends Controller
{
    /**
     * Retrieve the inventory usage history.
     *
     * @param int $inventoryId The ID of the inventory.
     * @return JsonResponse
     *
     * @OA\Get(
     *     path="/inventory/{inventoryId}/usage-history",
     *     tags={"Inventory"},
     *     summary="Retrieve the inventory usage history",
     *     @OA\Parameter(
     *         name="inventoryId",
     *         in="path",
     *         description="The ID of the inventory",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
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
     *                         property="date",
     *                         type="string",
     *                         format="date",
     *                         description="The date of inventory usage"
     *                     ),
     *                     @OA\Property(
     *                         property="quantity_used",
     *                         type="integer",
     *                         description="The quantity of the inventory used on the date"
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
     *         response=404,
     *         description="Inventory not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function index(int $inventoryId): JsonResponse
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

        $inventory = Inventory::where('restaurant_id', $venue->id)
            ->find($inventoryId);

        if (!$inventory) {
            return response()->json(['error' => 'Inventory not found'], 404);
        }

        $usageHistory = InventoryActivity::where('inventory_id', $inventoryId)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(quantity) as quantity_used'))
            ->groupBy('date')
            ->get();

        return response()->json(['data' => $usageHistory]);
    }


}
