<?php

namespace App\Jobs;

use App\Models\VbStoreProductVariant;
use App\Models\VbStoreAttributeOption;
use App\Models\VbStoreProductVariantAttribute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProductVariantAttributeSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $page;
    protected $perPage;
    protected $venueId;
    protected $apiUrl;
    protected $apiKey;
    protected $batchId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($page, $perPage, $venueId, $apiUrl, $apiKey, $batchId)
    {
        $this->page = $page;
        $this->perPage = $perPage;
        $this->venueId = $venueId;
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
        $this->batchId = $batchId;

        // Set high timeout for large data processing
        $this->timeout = 600;

        // Set to a dedicated queue
        $this->onQueue('sync-products');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("Processing product variant attribute sync batch", [
            'batch_id' => $this->batchId,
            'page' => $this->page,
            'venue_id' => $this->venueId
        ]);

        try {
            // Force update counter at the beginning to ensure progress tracking
            $this->forceUpdateProgressCounter();

            // Fetch data from API
            Log::info("Fetching data from API", ['page' => $this->page]);
            $response = Http::withHeaders([
                'X-App-Key' => $this->apiKey
            ])->get($this->apiUrl . 'product-variant-attributes', [
                'page' => $this->page,
                'per_page' => $this->perPage
            ]);

            if (!$response->successful()) {
                Log::error("API request failed", [
                    'batch_id' => $this->batchId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                throw new \Exception("API request failed with status " . $response->status());
            }

            $data = $response->json();

            if (empty($data) || !isset($data['data']) || empty($data['data'])) {
                Log::info("No data to process", ['batch_id' => $this->batchId]);
                return;
            }

            // Process product variant attributes
            $productVariantAttrs = $data['data'];
            $totalProcessed = 0;
            $skippedCount = 0;

            foreach ($productVariantAttrs as $item) {
                DB::beginTransaction();
                try {
                    // Make sure the required fields are available
                    if (!isset($item['id'])) {
                        Log::warning('Product variant attribute missing ID', ['item' => $item]);
                        $skippedCount++;
                        DB::rollBack();
                        continue;
                    }

                    $variant = VbStoreProductVariant::withTrashed()->where('bybest_id', $item['variant_id'])->first();
                    if (!$variant) {
                        Log::warning("Variant not found for attribute", [
                            'variant_attr_id' => $item['id'],
                            'variant_bybest_id' => $item['variant_id']
                        ]);
                        $skippedCount++;
                        DB::rollBack();
                        continue;
                    }

                    $attr = VbStoreAttributeOption::where('bybest_id', $item['atribute_id'])->first();
                    if (!$attr) {
                        Log::warning("Attribute option not found", [
                            'variant_attr_id' => $item['id'],
                            'attribute_bybest_id' => $item['atribute_id']
                        ]);
                        $skippedCount++;
                        DB::rollBack();
                        continue;
                    }

                    VbStoreProductVariantAttribute::updateOrCreate(
                        ['bybest_id' => $item['id']],
                        [
                            'variant_id' => $variant->id,
                            'attribute_id' => $attr->id,
                            'venue_id' => $this->venueId,
                            'bybest_id' => $item['id'],
                            'created_at' => $item['created_at'],
                            'updated_at' => $item['updated_at'],
                            'deleted_at' => $item['deleted_at'],
                        ]
                    );

                    $totalProcessed++;
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error("Error processing product variant attribute", [
                        'batch_id' => $this->batchId,
                        'variant_attr_id' => $item['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $skippedCount++;
                }
            }

            Log::info("Successfully processed product variant attributes batch", [
                'batch_id' => $this->batchId,
                'page' => $this->page,
                'processed' => $totalProcessed,
                'skipped' => $skippedCount
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to process product variant attributes sync batch", [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Requeue with backoff if needed
            if ($this->attempts() < 3) {
                $this->release(30 * $this->attempts());
                return;
            }

            throw $e;
        }
    }

    /**
     * Force update the progress counter using direct SQL
     */
    private function forceUpdateProgressCounter()
    {
        try {
            // USE RAW SQL FOR GUARANTEED UPDATE
            $updated = DB::statement("
                UPDATE inventory_syncs
                SET processed_pages = processed_pages + 1,
                    updated_at = NOW()
                WHERE batch_id = ?
            ", [$this->batchId]);

            Log::info("Force updated progress counter for product variant attributes", [
                'batch_id' => $this->batchId,
                'page' => $this->page,
                'success' => $updated
            ]);

            // Force a delay to make sure DB operations complete
            sleep(1);

        } catch (\Exception $e) {
            Log::error("CRITICAL: Failed to force update progress counter", [
                'batch_id' => $this->batchId,
                'page' => $this->page,
                'error' => $e->getMessage()
            ]);
        }
    }
}
