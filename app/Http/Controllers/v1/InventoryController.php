<?php
namespace App\Http\Controllers\v1;
use App\Enums\InventoryActivityCategory;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryActivity;
use App\Models\InventoryAlert;
use App\Models\InventoryRetail;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use function response;

/**
 * @OA\Info(
 *   title="Inventory Management API",
 *   version="1.0",
 *   description="This API allows use Inventory Management Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Inventory",
 *   description="Operations related to Inventory Management"
 * )
 */


class InventoryController extends Controller
{
    /**
     * @OA\Post(
     *     path="/inventory",
     *     summary="Add new inventory",
     *     tags={"Inventory"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="label", type="string", example="Drinks Inventory"),
     *             @OA\Property(
     *                 property="options",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", example=10)
     *                 ),
     *                 example={{"product_id": 1, "quantity": 10}, {"product_id": 2, "quantity": 5}}
     *             )
     *         )
     *     ),
     *     @OA\Response(response="200", description="Inventory created successfully"),
     *     @OA\Response(response="400", description="Validation errors"),
     *     @OA\Response(response="404", description="Category not found"),
     *     @OA\Response(response="422", description="Invalid ID")
     * )
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
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
            'category_id' => 'required|exists:categories,id',
            'products' => 'nullable|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $category = Category::where('restaurant_id', $venue->id)->find($request->category_id);


        if (!$category) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        $label = $request->label ?? $category->title . ' Inventory';

        $inventory = new Inventory();
        $inventory->label = $label;
        $inventory->restaurant_id = $venue->id;
        $inventory->save();

        $inventory->categories()->sync([$category->id]);


        if ($request->has('products')) {
            foreach ($request->products as $productInventory) {
                $product = Product::find($productInventory['product_id']);

                if ($product) {
                    $inventory->products()->attach($product, ['quantity' => $productInventory['quantity']]);
                }
            }
        }

        return response()->json(['message' => 'Inventory created successfully', 'inventory' => $inventory]);
    }

    /**
     * @OA\Get(
     *     path="/inventory",
     *     tags={"Inventory"},
     *     summary="List all inventories with related categories and products",
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
     *                                 property="quantity",
     *                                 type="integer",
     *                                 description="The quantity of the product in the inventory"
     *                             )
     *                         )
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
    public function index(): \Illuminate\Http\JsonResponse
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

        $inventories = Inventory::where('restaurant_id', $venue->id)->with('categories', 'products')->get();

        $data = [];
        foreach ($inventories as $inventory) {
            $category = $inventory->categories->first();
            $products = $inventory->products;

            $productData = [];
            foreach ($products as $product) {
                $productData[] = [
                    'name' => $product->title,
                    'quantity' => $product->pivot->quantity,
                ];
            }

            $data[] = [
                'id' => $inventory->id,
                'category' => $category->title,
                'label' => $inventory->label,
                'products' => $productData,
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * @OA\Get(
     *     path="/inventory/{id}",
     *     tags={"Inventory"},
     *     summary="Get a single inventory by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the inventory",
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
     *                 type="object",
     *                 @OA\Property(
     *                     property="category",
     *                     type="string",
     *                     description="The name of the inventory category"
     *                 ),
     *                 @OA\Property(
     *                     property="label",
     *                     type="string",
     *                     description="The label of the inventory"
     *                 ),
     *                 @OA\Property(
     *                     property="products",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(
     *                             property="name",
     *                             type="string",
     *                             description="The name of the product"
     *                         ),
     *                         @OA\Property(
     *                             property="quantity",
     *                             type="integer",
     *                             description="The quantity of the product in the inventory"
     *                         )
     *                     )
     *                 )
     *             )
     *         )
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
    public function show($id): \Illuminate\Http\JsonResponse
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
            ->with('categories', 'products')
            ->find($id);

        if (!$inventory) {
            return response()->json(['error' => 'Inventory not found'], 404);
        }

        $category = $inventory->categories->first();
        $products = $inventory->products;

        $productData = [];
        foreach ($products as $product) {
            $productData[] = [
                'id' => $product->id,
                'name' => $product->title,
                'quantity' => $product->pivot->quantity,
            ];
        }

        $data = [
            'category' => $category->title,
            'label' => $inventory->label,
            'products' => $productData,
        ];

        return response()->json(['data' => $data]);
    }

    /**
     * @OA\Put(
     *     path="/inventory/{id}",
     *     tags={"Inventory"},
     *     summary="Update an inventory",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the inventory",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="products",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(
     *                         property="product_id",
     *                         type="integer",
     *                         description="ID of the product"
     *                     ),
     *                     @OA\Property(
     *                         property="quantity",
     *                         type="integer",
     *                         description="Updated quantity of the product in the inventory"
     *                     )
     *                 ),
     *             ),
     *             @OA\Property(
     *                 property="label",
     *                 type="string",
     *                 description="Updated label of the inventory"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Inventory updated successfully"
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
    public function update(Request $request, $id): \Illuminate\Http\JsonResponse
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
            ->find($id);

        if (!$inventory) {
            return response()->json(['error' => 'Inventory not found'], 404);
        }

        // Update product quantities
        if ($request->has('products')) {
            foreach ($request->products as $productData) {
                $productId = $productData['product_id'];
                $quantity = $productData['quantity'];

                if ($quantity > 0) {
                    $existingQuantity = $inventory->products()->find($productId)->pivot->quantity ?? 0;

                    if ($quantity > $existingQuantity) {
                        $activityType = 'add';
                        $quantityDifference = $quantity - $existingQuantity;
                    } elseif ($quantity < $existingQuantity) {
                        $activityType = 'deduct';
                        $quantityDifference = $existingQuantity - $quantity;
                    } else {
                        continue; // Skip if the quantity is the same
                    }

                    $inventory->products()->syncWithoutDetaching([$productId => ['quantity' => $quantity]]);

                    // Create an inventory activity
                    $activity = new InventoryActivity();
                    $activity->product_id = $productId;
                    $activity->quantity = $quantityDifference;
                    $activity->activity_category = InventoryActivityCategory::INVENTORY_ITEM_UPDATE;
                    $activity->activity_type = $activityType;
                    $activity->inventory_id = $inventory->id;
                    $activity->order_id = null;
                    $activity->save();
                } else {
                    $inventory->products()->detach($productId);
                }
            }
        }

        // Update inventory label
        if ($request->has('label')) {
            $inventory->label = $request->label;
            $inventory->save();
        }

        return response()->json(['message' => 'Inventory updated successfully']);
    }

    /**
     * @OA\Get(
     *     path="/inventory/{inventoryId}/activities",
     *     tags={"Inventory"},
     *     summary="Retrieve activities for an inventory",
     *     @OA\Parameter(
     *         name="inventoryId",
     *         in="path",
     *         description="ID of the inventory",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="activities",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(
     *                         property="id",
     *                         type="integer",
     *                         description="The activity ID"
     *                     ),
     *                     @OA\Property(
     *                         property="description",
     *                         type="string",
     *                         description="The description of the activity"
     *                     ),
     *                     @OA\Property(
     *                         property="quantity",
     *                         type="integer",
     *                         description="The quantity involved in the activity"
     *                     ),
     *                     @OA\Property(
     *                         property="type",
     *                         type="string",
     *                         description="The type of the activity"
     *                     ),
     *                     @OA\Property(
     *                         property="order_id",
     *                         type="integer",
     *                         description="The ID of the related order"
     *                     ),
     *                     @OA\Property(
     *                         property="created_at",
     *                         type="string",
     *                         format="date-time",
     *                         description="The date and time the activity was created"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Restaurant not found"
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
    public function activities($inventoryId): \Illuminate\Http\JsonResponse
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

        $activities = InventoryActivity::where('inventory_id', $inventoryId)->with('product', 'order')->get();

        return response()->json(['activities' => $activities]);
    }


    public function createUpdateInventoryAlert(Request $request): \Illuminate\Http\JsonResponse
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
            'id' => 'nullable|exists:inventory_alerts,id', // if id is present, it means we are updating
            'inventory_retail_id' => 'nullable|exists:inventory_retail,id',
            'inventory_id' => 'nullable|exists:inventories,id',
            'product_id' => 'nullable|exists:products,id',
            'alert_level' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $iAlertId = $request->id;


        // check if request has id, do update
        if ($iAlertId) {
            $inventoryAlert = InventoryAlert::where('id', $request->id)->first();

            if (!$inventoryAlert) {
                return response()->json(['error' => 'Inventory alert not found'], 404);
            }

            $inventoryAlert->alert_level = $request->alert_level;
            $inventoryAlert->save();

            return response()->json(['message' => 'Inventory alert updated successfully']);
        } else {
                // check if request has inventory_retail_id
                if ($request->has('inventory_retail_id')) {
                    $inventoryRetail = InventoryRetail::where('venue_id', $venue->id)
                        ->find($request->inventory_retail_id);

                    if (!$inventoryRetail) {
                        return response()->json(['error' => 'Inventory retail not found'], 404);
                    }

                    // create inventory alert
                    $inventoryAlert = new InventoryAlert();
                    $inventoryAlert->inventory_retail_id = $inventoryRetail->id;
                    $inventoryAlert->alert_level = $request->alert_level;
                    $inventoryAlert->save();
                }

                if ($request->has('inventory_id') && $request->has('product_id')) {
                    $inventory = Inventory::where('restaurant_id', $venue->id)
                        ->find($request->inventory_id);

                    if (!$inventory) {
                        return response()->json(['error' => 'Inventory not found'], 404);
                    }

                    $product = Product::where('restaurant_id', $venue->id)
                        ->find($request->product_id);

                    if (!$product) {

                        // create inventory alert
                        $inventoryAlert = new InventoryAlert();
                        $inventoryAlert->inventory_id = $inventory->id;
                        $inventoryAlert->product_id = $product->id;
                        $inventoryAlert->alert_level = $request->alert_level;
                        $inventoryAlert->save();
                    }

                }
                    return response()->json(['message' => 'Inventory alert created successfully']);
            }
        }


    public function stockLevelReport(): \Illuminate\Http\JsonResponse
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

        $stockLevels = InventoryRetail::where('venue_id', $venue->id)
            ->with(['product', 'supplier'])
            ->get(['product_id', 'supplier_id', 'stock_quantity'])
            ->map(function ($inventory) {
                return [
                    'product' => $inventory->product->title,
                    'supplier' => $inventory->supplier->name ?? null,
                    'stock_quantity' => $inventory->stock_quantity
                ];
            });

        return response()->json($stockLevels);
    }

    public function inventoryTurnoverReport(Request $request): \Illuminate\Http\JsonResponse
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

        // validator
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $turnoverData = OrderProduct::whereHas('order', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        })
            ->with('product')
            ->get()
            ->groupBy('product_id')
            ->map(function ($group) {
                return [
                    'product' => $group->first()->product->title,
                    'sales_count' => $group->sum('product_quantity')
                ];
            })->values();

        return response()->json($turnoverData);
    }

    public function lowStockAlertReport(): \Illuminate\Http\JsonResponse
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
        $lowStockItems = InventoryRetail::where('venue_id', $venue->id)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->with('product')
            ->get()
            ->map(function ($inventory) {
                return [
                    'product' => $inventory->product->title,
                    'current_stock' => $inventory->stock_quantity,
                    'low_stock_threshold' => $inventory->low_stock_threshold
                ];
            });

        return response()->json($lowStockItems);
    }

    public function inventoryValuationReport(): \Illuminate\Http\JsonResponse
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
        $inventoryValue = InventoryRetail::where('venue_id', $venue->id)
            ->with('product')
            ->get()
            ->sum(function ($inventory) {
                return $inventory->stock_quantity * $inventory->product->price;
            });

        return response()->json(['inventory_value' => $inventoryValue]);
    }

    public function seasonalDemandAnalysis(Request $request): \Illuminate\Http\JsonResponse
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

        $year = $request->input('year', date('Y')); // Default to current year if not provided
        $month = $request->input('month', null); // Allow user to specify month

        $query = Order::query();

        $query->where('restaurant_id', $venue->id);
        // Filter by year
        $query->whereYear('created_at', $year);


        // Optionally filter by month
        if ($month) {
            $query->whereMonth('created_at', $month);
        }

        $salesData = $query->with('orderProducts.product')
            ->get()
            ->groupBy(function ($order) {
                // Grouping sales data by month
                return Carbon::parse($order->created_at)->format('m'); // grouping by month
            })
            ->map(function ($ordersInMonth) {
                // Summing up the total sales for each month
                return $ordersInMonth->sum(function ($order) {
                    return $order->total_amount;
                });
            });

        $seasonalTrends = $this->analyzeSeasonalData($salesData);

        return response()->json([
            'year' => $year,
            'month' => $month,
            'seasonal_trends' => $seasonalTrends
        ]);
    }


    private function analyzeSeasonalData($salesData) {

        $overallAverage = $salesData->average();

        $seasonalTrends = $salesData->mapWithKeys(function ($totalSales, $month) use ($overallAverage) {
            $significantDeviation = $overallAverage * 0.2;

            if ($totalSales > $overallAverage + $significantDeviation) {
                return [$month => 'Peak Season'];
            } elseif ($totalSales < $overallAverage - $significantDeviation) {
                return [$month => 'Off Season'];
            } else {
                return [$month => 'Normal'];
            }
        });

        return $seasonalTrends;
    }


    public function inventoryForecasting(Request $request): \Illuminate\Http\JsonResponse
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
            'product_id' => 'required|exists:products,id',
            'number_of_months' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }


        // check if product id belongs to the venue
        $productId = $request->input('product_id');
        $numberOfMonths = $request->input('number_of_months');

        // Fetch sales data for the given product over the specified number of months
        $salesData = OrderProduct::whereHas('order', function ($query) use ($venue) {
            $query->where('restaurant_id', $venue->id);
        })
            ->where('created_at', '>=', Carbon::now()->subMonths($numberOfMonths))
            ->get()
            ->groupBy(function($date) {
                // Group by month
                return Carbon::parse($date->created_at)->format('Y-m');
            })
            ->map(function($sales) {
                // Sum up the quantities for each month
                return $sales->sum('product_quantity');
            });

        // Calculate the average monthly sales
        $averageMonthlySales = $salesData->average();

        // Forecast for the next month could be the average monthly sales
        // You might want to adjust this logic based on your specific needs
        $forecastedSales = round($averageMonthlySales);

        return response()->json([
            'product_id' => $productId,
            'forecasted_sales_next_month' => $forecastedSales,
            'historical_sales_data' => $salesData
        ]);
    }

    public function delete($id, Request $request): \Illuminate\Http\JsonResponse
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
            'inventory_retail_id' => 'required|integer',
        ]);


        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }



        try {
            $alert = InventoryAlert::where(
                'inventory_retail_id', $request->input('inventory_retail_id')
            )->where('id', $id)->first();

            if (!$alert) {
                return response()->json(['message' => 'Not found alert'], 404);
            }
            $alert->delete();
            return response()->json(['message' => 'Alert is deleted successfully'], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



}
