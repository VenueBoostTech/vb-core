<?php

namespace App\Http\Controllers\v3\Synchronization;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Currency;
use App\Models\InventorySync;
use App\Models\InventorySyncError;
use App\Models\InventorySynchronization;
use App\Models\Restaurant;
use App\Services\VenueService;
use Carbon\Carbon;
use Google\Cloud\Storage\Connection\Rest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Alpha Synchronization",
 *     description="API endpoints for syncing data with Alpha system"
 * )
 */
class AlphaSyncController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    /**
     * @OA\Post(
     *     path="/synchronizations/do-sync/price",
     *     tags={"Alpha Synchronization"},
     *     summary="Synchronize prices from Alpha system",
     *     description="Fetches and updates product prices from Alpha system",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="sync_date",
     *                 type="string",
     *                 format="date",
     *                 example="2024-10-26",
     *                 description="Date to sync from"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful synchronization",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Synchronization completed successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="products_synced", type="integer", example=100),
     *                 @OA\Property(property="variants_synced", type="integer", example=150),
     *                 @OA\Property(property="sync_time", type="string", example="2024-10-26 12:00:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Invalid sync_date format")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Failed to connect to Alpha system")
     *         )
     *     )
     * )
     */
    public function syncPriceAlpha(Request $request): \Illuminate\Http\JsonResponse
    {
        // Check if we're in bypass mode (for testing purposes)
        $bypassMode = $request->input('bypass') === 'true'; // You can pass this in the request

        if ($bypassMode) {
            // Directly retrieve the venue by ID from the request for testing
            $venueId = $request->input('venue_id'); // Pass the venue ID in the request
            $venue =  Restaurant::where('id', $venueId)->first();

            if (!$venue) {
                return response()->json(['error' => 'Venue not found'], 404);
            }

            $syncMethod = InventorySynchronization::METHOD_API_CRONJOB;
        } else {
            // Perform the regular admin auth check
            $venue = $this->venueService->adminAuthCheck();
    
            if (method_exists($venue, 'getStatusCode') && $venue->getStatusCode() != 200) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $syncMethod = InventorySynchronization::METHOD_MANUAL;
        }

        // Start synchronization record
        $sync = InventorySynchronization::create([
            'venue_id' => $venue->id,
            'sync_type' => InventorySync::where('slug', 'price-sync')->first()->id,
            'method' => $syncMethod,
            'third_party' => 'Alpha',
            'created_at' => now()
        ]);

        try {
            // Validate request
            $request->validate([
                'sync_date' => 'required|date_format:Y-m-d'
            ]);

            // Configure PHP settings
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', 3000000);

            $before_sync = now();
            $currency_to_convert = Currency::where('currency_alpha', '=', 'LEK')->firstOrFail();

            // Prepare request data
            $data_string = [
                "cmime" => [[
                    "NRSEL" => "",
                    "NRCHUNK" => "",
                    "MARRE" => $request->sync_date . "T00:00:00",
                    "PERDORUES" => "ecom",
                ]]
            ];

            // Make API request
            $response = Http::timeout(1260)
                ->withHeaders([
                    "Connection" => "keep-alive",
                    "Content-Type" => "application/json",
                    "Accept-Encoding" => "gzip, deflate, br",
                    "ndermarrjaserver" => "Alpha Web",
                    "connectionstringname" => "by-best-duty-free",
                    "authorization" => "Basic ZWNvbTo4MzQ4ODE2ZjI0YTk2ZDdlMTRjMjIwYzFjYzQxOTJlYWNiNTFhMGM3YjE1YzI5NTkyNzQyODViNDdlOTM0YjYz"
                ])
                ->post('http://node.alpha.al/cmimipost', $data_string);

            if ($response->successful()) {
                $response_data = $response->json();
                $prices = $response_data['entiteteTeReja']['cmimeReja'] ?? [];

                DB::beginTransaction();
                try {
                    foreach ($prices as $price) {
                        if ($price['CMIMIMETVSH'] > 0 && $price['MONEDHAKOD'] == 'LEK') {
                            $this->updateProductAndVariant($price, $currency_to_convert);
                        }
                    }

                    // Update products from variants
                    $this->syncProductsFromVariants($currency_to_convert);

                    DB::commit();

                    // Mark sync as completed
                    $sync->update(['completed_at' => now()]);

                    // Get sync counts
                    $sync_products = DB::table('products')
                        ->where('syncronize_at', '>=', $before_sync)
                        ->count();
                    $sync_variants = DB::table('vb_store_products_variants')
                        ->where('synchronize_at', '>=', $before_sync)
                        ->count();

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Alpha Price Synchronization completed successfully',
                        'data' => [
                            'products_synced' => $sync_products,
                            'variants_synced' => $sync_variants,
                            'sync_time' => now()->toDateTimeString(),
                            'sync_id' => $sync->id
                        ]
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->logSyncError(
                        $sync->id,
                        $venue->id,
                        InventorySyncError::ERROR_TYPE_DATABASE,
                        $e->getMessage(),
                        [
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString()
                        ]
                    );
                    $sync->update(['failed_at' => now()]);
                    throw $e;
                }
            } else {
                $this->logSyncError(
                    $sync->id,
                    $venue->id,
                    InventorySyncError::ERROR_TYPE_API,
                    'Failed to get data from Alpha system',
                    [
                        'response' => $response->body(),
                        'status_code' => $response->status(),
                        'request_data' => $data_string
                    ]
                );
                $sync->update(['failed_at' => now()]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to get data from Alpha system',
                    'error' => $response->body(),
                    'sync_id' => $sync->id
                ], 500);
            }
        } catch (ValidationException $e) {
            $this->logSyncError(
                $sync->id,
                $venue->id,
                InventorySyncError::ERROR_TYPE_VALIDATION,
                'Validation failed',
                ['errors' => $e->errors()]
            );
            $sync->update(['failed_at' => now()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'sync_id' => $sync->id
            ], 400);
        } catch (\Exception $e) {
            $this->logSyncError(
                $sync->id,
                $venue->id,
                InventorySyncError::ERROR_TYPE_SYSTEM,
                $e->getMessage(),
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            );
            $sync->update(['failed_at' => now()]);
            Log::error('Price sync failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
                'sync_id' => $sync->id
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/synchronizations/do-sync/sku",
     *     tags={"Alpha Synchronization"},
     *     summary="Synchronize SKUs from Alpha system",
     *     description="Fetches and updates product SKUs from Alpha system",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="sync_date",
     *                 type="string",
     *                 format="date",
     *                 example="2024-10-26",
     *                 description="Date to sync from"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful synchronization",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Synchronization completed successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="products_synced", type="integer", example=100),
     *                 @OA\Property(property="variants_synced", type="integer", example=150),
     *                 @OA\Property(property="sync_time", type="string", example="2024-10-26 12:00:00"),
     *                 @OA\Property(property="sync_id", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Invalid sync_date format")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Failed to connect to Alpha system")
     *         )
     *     )
     * )
     */
    public function syncSkuAlpha(Request $request): \Illuminate\Http\JsonResponse
    {
        // Check if we're in bypass mode (for testing purposes)
        $bypassMode = $request->input('bypass') === 'true'; // You can pass this in the request

        if ($bypassMode) {
            // Directly retrieve the venue by ID from the request for testing
            $venueId = $request->input('venue_id'); // Pass the venue ID in the request
            $venue =  Restaurant::where('id', $venueId)->first();

            if (!$venue) {
                return response()->json(['error' => 'Venue not found'], 404);
            }

            $syncMethod = InventorySynchronization::METHOD_API_CRONJOB;
        } else {
            // Perform the regular admin auth check
            $venue = $this->venueService->adminAuthCheck();

            if (method_exists($venue, 'getStatusCode') && $venue->getStatusCode() != 200) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $syncMethod = InventorySynchronization::METHOD_MANUAL;
        }

        // Start synchronization record
        $sync = InventorySynchronization::create([
            'venue_id' => $venue->id,
            'sync_type' => InventorySync::where('slug', 'sku-sync')->first()->id,
            'method' => $syncMethod,
            'third_party' => 'Alpha',
            'created_at' => now()
        ]);

        try {
            // Validate request
            $request->validate([
                'sync_date' => 'required|date_format:Y-m-d'
            ]);

            // Configure PHP settings
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', 3000000);

            $before_sync = now();

            // Prepare request data
            $data_string = [
                "barkodArtikulli" => [[
                    "NRSEL" => "",
                    "NRCHUNK" => "",
                    "MARRE" => $request->sync_date . "T00:00:00",
                    "PERDORUES" => "ecom",
                ]]
            ];

            // Make API request
            $response = Http::timeout(120)
                ->withHeaders([
                    "Content-Type" => "application/json",
                    "ndermarrjaserver" => "Alpha Web",
                    "connectionstringname" => "by-best-duty-free",
                    "authorization" => "Basic ZWNvbTo4MzQ4ODE2ZjI0YTk2ZDdlMTRjMjIwYzFjYzQxOTJlYWNiNTFhMGM3YjE1YzI5NTkyNzQyODViNDdlOTM0YjYz"
                ])
                ->post('http://node.alpha.al/barkodArt', $data_string);

            if ($response->successful()) {
                DB::beginTransaction();
                try {
                    $response_data = $response->json();
                    $skus = $response_data['entiteteTeReja']['barkodArtikulliRi'] ?? [];

                    foreach ($skus as $sku) {
                        $this->updateProductAndVariantSku($sku);
                    }

                    DB::commit();

                    // Mark sync as completed
                    $sync->update(['completed_at' => now()]);

                    // Get sync counts
                    $sync_products = DB::table('products')
                        ->where('syncronize_at', '>=', $before_sync)
                        ->count();
                    $sync_variants = DB::table('vb_store_products_variants')
                        ->where('synchronize_at', '>=', $before_sync)
                        ->count();


                    return response()->json([
                        'status' => 'success',
                        'message' => 'Alpha SKU Synchronization completed successfully',
                        'data' => [
                            'products_synced' => $sync_products,
                            'variants_synced' => $sync_variants,
                            'sync_time' => now()->toDateTimeString(),
                            'sync_id' => $sync->id
                        ]
                    ]);

                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->logSyncError(
                        $sync->id,
                        $venue->id,
                        InventorySyncError::ERROR_TYPE_DATABASE,
                        $e->getMessage(),
                        [
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString()
                        ]
                    );
                    $sync->update(['failed_at' => now()]);
                    throw $e;
                }
            } else {
                $this->logSyncError(
                    $sync->id,
                    $venue->id,
                    InventorySyncError::ERROR_TYPE_API,
                    'Failed to get data from Alpha system',
                    [
                        'response' => $response->body(),
                        'status_code' => $response->status(),
                        'request_data' => $data_string
                    ]
                );
                $sync->update(['failed_at' => now()]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to get data from Alpha system',
                    'error' => $response->body(),
                    'sync_id' => $sync->id
                ], 500);
            }
        } catch (ValidationException $e) {
            $this->logSyncError(
                $sync->id,
                $venue->id,
                InventorySyncError::ERROR_TYPE_VALIDATION,
                'Validation failed',
                ['errors' => $e->errors()]
            );
            $sync->update(['failed_at' => now()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'sync_id' => $sync->id
            ], 400);
        } catch (\Exception $e) {
            $this->logSyncError(
                $sync->id,
                $venue->id,
                InventorySyncError::ERROR_TYPE_SYSTEM,
                $e->getMessage(),
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            );
            $sync->update(['failed_at' => now()]);
            Log::error('SKU sync failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
                'sync_id' => $sync->id
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/synchronizations/do-sync/stocks",
     *     tags={"Alpha Synchronization"},
     *     summary="Synchronize stock quantities from Alpha system",
     *     description="Fetches and updates product stock levels from Alpha system",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="sync_date",
     *                 type="string",
     *                 format="date",
     *                 example="2024-10-26",
     *                 description="Date to sync from"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful synchronization",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Stock synchronization completed successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="products_synced", type="integer"),
     *                 @OA\Property(property="variants_synced", type="integer"),
     *                 @OA\Property(property="zero_stock", type="integer"),
     *                 @OA\Property(property="without_stock", type="integer"),
     *                 @OA\Property(property="sync_time", type="string"),
     *                 @OA\Property(property="sync_id", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function syncStockAlpha(Request $request): \Illuminate\Http\JsonResponse
    {
        // Check if we're in bypass mode (for testing purposes)
        $bypassMode = $request->input('bypass') === 'true'; // You can pass this in the request

        if ($bypassMode) {
            // Directly retrieve the venue by ID from the request for testing
            $venueId = $request->input('venue_id'); // Pass the venue ID in the request
            $venue =  Restaurant::where('id', $venueId)->first();

            if (!$venue) {
                return response()->json(['error' => 'Venue not found'], 404);
            }
            $syncMethod = InventorySynchronization::METHOD_API_CRONJOB;
        } else {
            // Perform the regular admin auth check
            $venue = $this->venueService->adminAuthCheck();

            if (method_exists($venue, 'getStatusCode') && $venue->getStatusCode() != 200) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $syncMethod = InventorySynchronization::METHOD_MANUAL;
        }

        // Start synchronization record
        $sync = InventorySynchronization::create([
            'venue_id' => $venue->id,
            'sync_type' => InventorySync::where('slug', 'stock-sync')->first()->id,
            'method' => $syncMethod,
            'third_party' => 'Alpha',
            'created_at' => now()
        ]);

        try {
            $request->validate([
                'sync_date' => 'required|date_format:Y-m-d'
            ]);

            // Configure PHP settings
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', 3000000);

            $before_sync = now();

            $data_string = [
                "artikujGjendje" => [[
                    "NRSEL" => "",
                    "NRCHUNK" => "",
                    "MARRE" => $request->sync_date . "T00:00:00",
                    "PERDORUES" => "ecom",
                ]]
            ];

            $response = Http::timeout(1260)
                ->withHeaders([
                    "Connection" => "keep-alive",
                    "Content-Type" => "application/json",
                    "Accept-Encoding" => "gzip, deflate, br",
                    "ndermarrjaserver" => "Alpha Web",
                    "connectionstringname" => "by-best-duty-free",
                    "authorization" => "Basic ZWNvbTo4MzQ4ODE2ZjI0YTk2ZDdlMTRjMjIwYzFjYzQxOTJlYWNiNTFhMGM3YjE1YzI5NTkyNzQyODViNDdlOTM0YjYz"
                ])
                ->post('http://node.alpha.al/artikujGjendje', $data_string);

            if ($response->successful()) {
                DB::beginTransaction();
                try {
                    $response_data = $response->json();
                    $stocks = $response_data['entiteteTeReja']['artikujGjendjeRi'];
                    $extracted = $this->groupByArticleCode($stocks);

                    // Update stock for each warehouse
                    foreach ($stocks as $product) {
                        if ($product['KODARTIKULLI']) {
                            $this->updateOrInsertStock($product, $venue->id);
                        }
                    }

                    DB::commit();
                    $sync->update(['completed_at' => now()]);

                    // Get sync stats
                    $stats = $this->calculateSyncStats($before_sync);

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Stock synchronization completed successfully',
                        'data' => array_merge($stats, [
                            'sync_time' => now()->toDateTimeString(),
                            'sync_id' => $sync->id
                        ])
                    ]);

                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->logSyncError(
                        $sync->id,
                        $venue->id,
                        InventorySyncError::ERROR_TYPE_DATABASE,
                        $e->getMessage(),
                        [
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString()
                        ]
                    );
                    $sync->update(['failed_at' => now()]);
                    throw $e;
                }
            } else {
                $this->logSyncError(
                    $sync->id,
                    $venue->id,
                    InventorySyncError::ERROR_TYPE_API,
                    'Failed to get stock data from Alpha system',
                    [
                        'response' => $response->body(),
                        'status_code' => $response->status(),
                        'request_data' => $data_string
                    ]
                );
                $sync->update(['failed_at' => now()]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to get stock data from Alpha system',
                    'error' => $response->body(),
                    'sync_id' => $sync->id
                ], 500);
            }
        } catch (\Exception $e) {
            if (isset($sync)) {
                $this->logSyncError(
                    $sync->id,
                    $venue->id,
                    InventorySyncError::ERROR_TYPE_SYSTEM,
                    $e->getMessage(),
                    [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]
                );
                $sync->update(['failed_at' => now()]);
            }
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
                'sync_id' => $sync->id ?? null
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/synchronizations/do-sync/calculate-stock",
     *     tags={"Alpha Synchronization"},
     *     summary="Calculate total stock for products and variants",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="sync_date",
     *                 type="string",
     *                 format="date"
     *             ),
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 enum={"variants", "single"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success"
     *     )
     * )
     */
    public function calculateStock(Request $request): \Illuminate\Http\JsonResponse
    {
        // Check if we're in bypass mode (for testing purposes)
        $bypassMode = $request->input('bypass') === 'true'; // You can pass this in the request

        if ($bypassMode) {
            // Directly retrieve the venue by ID from the request for testing
            $venueId = $request->input('venue_id'); // Pass the venue ID in the request
            $venue =  Restaurant::where('id', $venueId)->first();

            if (!$venue) {
                return response()->json(['error' => 'Venue not found'], 404);
            }
        } else {
            // Perform the regular admin auth check
            $venue = $this->venueService->adminAuthCheck();

            if (!$venue) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        // Create a new stock calculation entry
        $stockCalculation = DB::table('stock_calculations')->insertGetId([
            'venue_id' => $venue->id,
            'sync_date' => $request->sync_date,
            'calculation_type' => $request->type,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        try {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', 3000000);

            $before_sync = now();

            DB::beginTransaction();
            try {
                if ($request->type === 'variants') {
                    $this->calculateVariantsStock($request->sync_date);
                } else {
                    $this->calculateSingleProductsStock($request->sync_date);
                }

                DB::commit();
                // Update stock calculation status to 'completed'
                DB::table('stock_calculations')
                    ->where('id', $stockCalculation)
                    ->update(['status' => 'completed', 'updated_at' => now()]);

                $stats = $this->calculateSyncStats($before_sync);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Stock calculation completed successfully',
                    'data' => array_merge($stats, [
                        'calculation_time' => now()->toDateTimeString(),
                        'stock_calculation_id' => $stockCalculation
                    ])
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                $this->logSyncError(
                    null,
                    $venue->id,
                    InventorySyncError::ERROR_TYPE_DATABASE,
                    $e->getMessage(),
                    [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ],
                    $stockCalculation
                );
                DB::table('stock_calculations')
                    ->where('id', $stockCalculation)
                    ->update(['status' => 'failed_at', 'updated_at' => now()]);
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logSyncError(
                null,
                $venue->id,
                InventorySyncError::ERROR_TYPE_SYSTEM,
                $e->getMessage(),
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ],
                $stockCalculation
            );
            DB::table('stock_calculations')
                ->where('id', $stockCalculation)
                ->update(['status' => 'failed_at', 'updated_at' => now()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Stock calculation failed',
                'error' => $e->getMessage(),
                'sync_id' => $stockCalculation
            ], 500);
        }
    }

    private function groupByArticleCode(array $data): array
    {
        $result = [];
        foreach ($data as $val) {
            if (array_key_exists('KODARTIKULLI', $val)) {
                $result[$val['KODARTIKULLI']][] = $val;
            } else {
                $result[""][] = $val;
            }
        }
        return $result;
    }

    private function updateOrInsertStock(array $product, int $venue_id): void
    {
        DB::table('product_stock')->updateOrInsert(
            [
                'article_no' => $product['KODARTIKULLI'],
                'alpha_warehouse' => $product['KODI'],
                'venue_id' => $venue_id
            ],
            [
                'stock_quantity' => $product['gjendje'],
                'alpha_date' => Carbon::parse($product['DTMODIFIKIM']),
                'updated_at' => now(),
                'synchronize_at' => now(),
                'venue_id' => $venue_id
            ]
        );
    }

    private function calculateSyncStats($before_sync): array
    {
        $sync_products = DB::table('products')
            ->where('syncronize_at', '>=', $before_sync)
            ->count();

        $sync_variations = DB::table('vb_store_products_variants')
            ->where('synchronize_at', '>=', $before_sync)
            ->count();

        $without_sync_products = DB::table('products')
            ->whereNull('deleted_at')
            ->whereNull('stock_quantity')
            ->where('product_type', '=', 1)
            ->count();

        $without_sync_variations = DB::table('vb_store_products_variants')
            ->whereNull('deleted_at')
            ->whereNull('stock_quantity')
            ->count();

        $zero_stock_products = DB::table('products')
            ->whereNull('deleted_at')
            ->orWhere('stock_quantity', '=', 0)
            ->where('product_type', '=', 1)
            ->count();

        $zero_stock_variations = DB::table('vb_store_products_variants')
            ->whereNull('deleted_at')
            ->orWhere('stock_quantity', '=', 0)
            ->count();

        return [
            'products_synced' => $sync_products,
            'variants_synced' => $sync_variations,
            'without_stock' => $without_sync_products + $without_sync_variations,
            'zero_stock' => $zero_stock_products + $zero_stock_variations
        ];
    }

    private function calculateVariantsStock(string $sync_date): void
    {
        $variants = DB::select(
            'SELECT DISTINCT `article_no` FROM `product_stock`
        WHERE `article_no` IN (SELECT `sku_alpha` FROM `vb_store_products_variants` WHERE deleted_at IS NULL)
        AND updated_at >= ?',
            [$sync_date]
        );

        foreach ($variants as $variant) {
            $stockData = $this->calculateWarehouseStock($variant->article_no);

            DB::table('vb_store_products_variants')
                ->where('sku_alpha', $variant->article_no)
                ->update([
                    'stock_quantity' => $stockData->quantity === -1 ? 0 : $stockData->quantity,
                    'warehouse_alpha' => $stockData->warehouse,
                    'synchronize_at' => now()
                ]);

            $this->updateParentProductStock($variant->article_no);
        }
    }

    private function calculateSingleProductsStock(string $sync_date): void
    {
        $products = DB::select(
            'SELECT DISTINCT `article_no` FROM `product_stock`
        WHERE `article_no` IN (SELECT `sku_alpha` FROM `products` WHERE deleted_at IS NULL)
        AND updated_at >= ?',
            [$sync_date]
        );

        foreach ($products as $product) {
            $stockData = $this->calculateWarehouseStock($product->article_no);

            DB::table('products')
                ->where('sku_alpha', $product->article_no)
                ->update([
                    'stock_quantity' => $stockData->quantity === -1 ? 0 : $stockData->quantity,
                    'warehouse_alpha' => $stockData->warehouse,
                    'syncronize_at' => now()
                ]);
        }
    }

    private function calculateWarehouseStock(string $article_no): object
    {
        $warehouses = ['MQ', '01', '02', '03', 'MPB'];

        // Update the query to include GROUP BY for aggregated fields
        $stockData = DB::table('product_stock')
            ->select(
                'article_no',
                DB::raw('SUM(stock_quantity) as quantity'),
                'alpha_warehouse as warehouse'
            )
            ->where('article_no', $article_no)
            ->whereIn('alpha_warehouse', $warehouses)
            ->groupBy('article_no', 'alpha_warehouse') // Group by the fields
            ->first();

        // Check if stock data is null
        if (!$stockData) {
            $stockData = new \stdClass();
            $stockData->quantity = 0; // Default quantity
            $stockData->warehouse = 'TEMP'; // Default warehouse
        } else {
            // Check if warehouse is not set in stock data
            if (!$stockData->warehouse) {
                $warehouseWithStock = DB::table('product_stock')
                    ->select('alpha_warehouse')
                    ->where('stock_quantity', '>', 0)
                    ->whereIn('alpha_warehouse', $warehouses)
                    ->first();

                $stockData->warehouse = $warehouseWithStock ? $warehouseWithStock->alpha_warehouse : 'TEMP';
            }
        }

        return $stockData;
    }


    private function updateParentProductStock(string $variant_sku_alpha): void
    {
        $parent = DB::table('vb_store_products_variants')
            ->select('product_id')
            ->where('sku_alpha', $variant_sku_alpha)
            ->first();

        if ($parent) {
            $totalStock = DB::table('vb_store_products_variants')
                ->select(
                    'product_id',
                    'warehouse_alpha',
                    DB::raw('SUM(stock_quantity) as total')
                )
                ->where('product_id', $parent->product_id)
                ->groupBy('product_id', 'warehouse_alpha')
                ->first();

            $warehouseWithStock = DB::table('product_stock')
                ->select('alpha_warehouse')
                ->where('stock_quantity', '>', 0)
                ->whereIn('alpha_warehouse', ['MQ', '01', '02', '03', 'MPB'])
                ->first();

            DB::table('products')
                ->where('id', $parent->product_id)
                ->update([
                    'stock_quantity' => $totalStock->total === -1 ? 0 : $totalStock->total,
                    'warehouse_alpha' => $warehouseWithStock ? $warehouseWithStock->alpha_warehouse : 'TEMP',
                    'syncronize_at' => now()
                ]);
        }
    }
    /**
     * Update product and variant SKUs
     */
    private function updateProductAndVariantSku(array $sku): void
    {
        // Update product
        DB::table('products')
            ->where('product_sku', '=', $sku['BARKODI'])
            ->update([
                'sku_alpha' => $sku['KODARTIKULLI'],
                'syncronize_at' => now()
            ]);

        // Update variant
        DB::table('vb_store_products_variants')
            ->where('variation_sku', '=', $sku['BARKODI'])
            ->update([
                'sku_alpha' => $sku['KODARTIKULLI'],
                'synchronize_at' => now()
            ]);
    }

    /**
     * Update product and variant prices
     */
    private function updateProductAndVariant(array $price, Currency $currency_to_convert): void
    {
        $bb_points = $this->calculateBBPoints($price['CMIMIMETVSH'], $price['MONEDHAKOD'], $currency_to_convert->exchange_rate);
        $updateData = [
            'price' => $price['CMIMIMETVSH'],
            'currency_alpha' => $price['MONEDHAKOD'],
            'tax_code_alpha' => $price['TVSHKODI'],
            'price_without_tax_alpha' => $price['CMIMI'],
            'unit_code_alpha' => $price['KODNJESIA1'],
            'bb_points' => $bb_points,
        ];

        // Update product
        DB::table('products')
            ->where('sku_alpha', '=', $price['KODARTIKULLI'])
            ->update($updateData + ['syncronize_at' => now()]);

        // Update variant
        DB::table('vb_store_products_variants')
            ->where('sku_alpha', '=', $price['KODARTIKULLI'])
            ->update($updateData + ['synchronize_at' => now()]);
    }

    /**
     * Sync products from variants
     */
    private function syncProductsFromVariants(Currency $currency_to_convert): void
    {
        $variants = DB::table('vb_store_products_variants')
            ->select('product_id', 'price', 'sale_price', 'currency_alpha', 'warehouse_alpha')
            ->whereNotNull('currency_alpha')
            ->get();

        foreach ($variants as $variant) {
            DB::table('products')
                ->where('id', '=', $variant->product_id)
                ->update([
                    'price' => $variant->price,
                    'sale_price' => $variant->sale_price,
                    'currency_alpha' => $variant->currency_alpha,
                    'warehouse_alpha' => $variant->warehouse_alpha,
                    'bb_points' => $this->calculateBBPoints($variant->price, $variant->currency_alpha, $currency_to_convert->exchange_rate),
                    'syncronize_at' => now()
                ]);
        }
    }

    /**
     * Calculate BB points
     */
    private function calculateBBPoints(float $price, string $currency, float $exchange): float
    {
        return $currency === 'LEK' ? ($price / 100) : (($price * $exchange) / 100);
    }

    private function logSyncError(int $sync_id = null, int $venue_id, string $type, string $message, array $context = [], int $stock_calculation_id = null): void
    {
        InventorySyncError::create([
            'synchronization_id' => $sync_id,
            'stock_calculation_id' => $stock_calculation_id,
            'venue_id' => $venue_id,
            'error_type' => $type,
            'error_message' => $message,
            'error_context' => $context,
            'created_at' => now()
        ]);

        Log::error("Sync Error: [$sync_id] $type - $message", $context);
    }
}
