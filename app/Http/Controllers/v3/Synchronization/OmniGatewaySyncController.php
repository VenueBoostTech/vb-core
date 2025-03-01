<?php

// app/Http/Controllers/v3/Synchronization/OmniGatewayController.php

namespace App\Http\Controllers\v3\Synchronization;

use App\Http\Controllers\Controller;
use App\Models\InventorySync;
use App\Models\InventorySyncError;
use App\Models\InventorySynchronization;
use App\Models\Restaurant;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="OmniGateway Synchronization",
 *     description="API endpoints for syncing data with OmniGateway system"
 * )
 */
class OmniGatewaySyncController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    /**
     * @OA\Post(
     *     path="/synchronizations/omni/sync-price",
     *     tags={"OmniGateway Synchronization"},
     *     summary="Synchronize prices from OmniGateway",
     *     description="Fetches and updates product prices from OmniGateway",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="sync_date",
     *                 type="string",
     *                 format="date"
     *             )
     *         )
     *     )
     * )
     */
    public function syncPrice(Request $request)
    {
        $venue = $this->getVenue($request);

        // Create sync record
        $sync = $this->createSyncRecord($request, $venue->id, 'price-sync', 'OmniGateway');

        try {
            $request->validate(['sync_date' => 'required|date_format:Y-m-d']);

            $before_sync = now();

            // Make API request to OmniGateway
            $response = Http::withHeaders([
                'x-api-key' => $venue->omnigateway_api_key,
                'client-x-api-key' => config('services.omnigateway.api_key'),
            ])
            ->get(config('services.omnigateway.base_url') . '/brands', [
                'sync_date' => $request->input('sync_date'),
            ]);

            if ($response->successful()) {
                DB::beginTransaction();
                try {
                    $data = $response->json();
                    dd($data);
                    // Process prices update logic here
                    foreach ($data['items'] as $item) {
                        $this->updatePrices($item);
                    }

                    DB::commit();
                    $sync->update(['completed_at' => now()]);

                    // Calculate sync stats
                    $stats = $this->calculateSyncStats($before_sync);

                    return response()->json([
                        'status' => 'success',
                        'message' => 'OmniGateway Price Sync completed successfully',
                        'data' => array_merge($stats, [
                            'sync_time' => now()->toDateTimeString(),
                            'sync_id' => $sync->id
                        ])
                    ]);

                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            } else {
                throw new \Exception('Failed to get data from OmniGateway');
            }

        } catch (\Exception $e) {
            dd($e);
            $this->logSyncError($sync->id, $venue->id, 'ERROR', $e->getMessage());
            $sync->update(['failed_at' => now()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Sync failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Similar methods for stock and product sync
     */

    private function getVenue(Request $request)
    {
        $bypassMode = $request->input('bypass') === 'true';

        if ($bypassMode) {
            $venue = Restaurant::where('id', $request->input('venue_id'))->first();
            if (!$venue) {
                throw new \Exception('Venue not found');
            }
            return $venue;
        }

        $venue = $this->venueService->adminAuthCheck();
        if (method_exists($venue, 'getStatusCode') && $venue->getStatusCode() != 200) {
            throw new \Exception('Unauthorized');
        }

        return $venue;
    }

    private function createSyncRecord($request, $venueId, $type, $thirdParty)
    {
        return InventorySynchronization::create([
            'venue_id' => $venueId,
            'sync_type' => InventorySync::where('slug', $type)->first()->id,
            'method' => $request->input('bypass') === 'true'
                ? InventorySynchronization::METHOD_API_CRONJOB
                : InventorySynchronization::METHOD_MANUAL,
            'third_party' => $thirdParty,
            'created_at' => now()
        ]);
    }

    private function logSyncError($syncId, $venueId, $type, $message, $context = [])
    {
        InventorySyncError::create([
            'synchronization_id' => $syncId,
            'venue_id' => $venueId,
            'error_type' => $type,
            'error_message' => $message,
            'error_context' => $context,
            'created_at' => now()
        ]);

        Log::error("OmniGateway Sync Error: [$syncId] $type - $message", $context);
    }


    /**
     * @OA\Post(
     *     path="/synchronizations/omni/sync-stock",
     *     tags={"OmniGateway Synchronization"},
     *     summary="Synchronize stock from OmniGateway",
     * )
     */
    public function syncStock(Request $request)
    {
        $venue = $this->getVenue($request);
        $sync = $this->createSyncRecord($request, $venue->id, 'stock-sync', 'OmniGateway');

        try {
            $request->validate(['sync_date' => 'required|date_format:Y-m-d']);
            $before_sync = now();

            $response = Http::withHeaders([
                'x-api-key' => config('services.omnigateway.api_key'),
                'client-x-api-key' => $venue->omnigateway_api_key
            ])->post(config('services.omnigateway.base_url') . '/stocks', [
                'sync_date' => $request->sync_date
            ]);

            if ($response->successful()) {
                DB::beginTransaction();
                try {
                    $stocks = $response->json();
                    foreach ($stocks['items'] as $stock) {
                        $this->updateStock($stock, $venue->id);
                    }

                    DB::commit();
                    $sync->update(['completed_at' => now()]);

                    $stats = $this->calculateSyncStats($before_sync);
                    return response()->json([
                        'status' => 'success',
                        'message' => 'OmniGateway Stock Sync completed successfully',
                        'data' => array_merge($stats, [
                            'sync_time' => now()->toDateTimeString(),
                            'sync_id' => $sync->id
                        ])
                    ]);

                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            }

            throw new \Exception('Failed to get stock data from OmniGateway');

        } catch (\Exception $e) {
            $this->logSyncError($sync->id, $venue->id, 'ERROR', $e->getMessage());
            $sync->update(['failed_at' => now()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Stock sync failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/synchronizations/omni/sync-products",
     *     tags={"OmniGateway Synchronization"},
     *     summary="Synchronize products from OmniGateway",
     * )
     */
    public function syncProducts(Request $request)
    {
        $venue = $this->getVenue($request);
        $sync = $this->createSyncRecord($request, $venue->id, 'product-sync', 'OmniGateway');

        try {
            $request->validate(['sync_date' => 'required|date_format:Y-m-d']);
            $before_sync = now();

            $response = Http::withHeaders([
                'x-api-key' => config('services.omnigateway.api_key'),
                'client-x-api-key' => $venue->omnigateway_api_key
            ])->post(config('services.omnigateway.base_url') . '/products', [
                'sync_date' => $request->sync_date
            ]);

            if ($response->successful()) {
                DB::beginTransaction();
                try {
                    $products = $response->json();
                    foreach ($products['items'] as $product) {
                        $this->updateProduct($product, $venue->id);
                    }

                    DB::commit();
                    $sync->update(['completed_at' => now()]);

                    return response()->json([
                        'status' => 'success',
                        'message' => 'OmniGateway Products Sync completed successfully',
                        'data' => [
                            'products_synced' => count($products['items']),
                            'sync_time' => now()->toDateTimeString(),
                            'sync_id' => $sync->id
                        ]
                    ]);

                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            }

            throw new \Exception('Failed to get products data from OmniGateway');

        } catch (\Exception $e) {
            $this->logSyncError($sync->id, $venue->id, 'ERROR', $e->getMessage());
            $sync->update(['failed_at' => now()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Products sync failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
