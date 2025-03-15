<?php

namespace App\Jobs;

use App\Models\VbStoreProductVariant;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProductVariantSyncJob implements ShouldQueue
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
        Log::info("Processing product variant sync batch", [
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
            ])->get($this->apiUrl . 'productvariation-sync', [
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

            // Get product mappings for quick lookups
            $productIds = Product::withTrashed()->pluck('id', 'bybest_id')->toArray();
            if (empty($productIds)) {
                Log::warning("No products found for mapping", ['batch_id' => $this->batchId]);
            }

            // Process variants
            $variations = $data['data'];
            $totalProcessed = 0;
            $skippedCount = 0;
            $uploadJobs = [];

            foreach ($variations as $item) {
                DB::beginTransaction();
                try {
                    // Make sure the required fields are available
                    if (!isset($item['id'])) {
                        Log::warning('Variant missing ID', ['item' => $item]);
                        $skippedCount++;
                        DB::rollBack();
                        continue;
                    }

                    $json_desc = json_decode($item['product_long_description']);
                    $desc = (isset($json_desc->en) && $json_desc->en != null) ? $json_desc->en : '';
                    $desc_al = (isset($json_desc->sq) && $json_desc->sq != null) ? $json_desc->sq : '';

                    $productId = isset($productIds[$item['product_id']]) ? $productIds[$item['product_id']] : null;
                    if (!$productId) {
                        Log::warning("Product not found for variant", [
                            'variant_id' => $item['id'],
                            'product_bybest_id' => $item['product_id']
                        ]);
                        $skippedCount++;
                        DB::rollBack();
                        continue;
                    }

                    $variant = VbStoreProductVariant::updateOrCreate(
                        ['bybest_id' => $item['id']],
                        [
                            'product_id' => $productId,
                            'venue_id' => $this->venueId,
                            'name' =>  $item['varation_name'],
                            'variation_sku' =>  $item['variation_sku'],
                            'sku_alpha' => $item['variation_sku_alpha'],
                            'warehouse_alpha' => $item['warehouse_alpha'],
                            'currency_alpha' => $item['currency_alpha'],
                            'tax_code_alpha' => $item['tax_code_alpha'],
                            'price_without_tax_alpha' => $item['price_without_tax_alpha'],
                            'unit_code_alpha' => $item['unit_code_alpha'],
                            'article_no' =>  $item['article_no'],
                            'gender_id' => $item['gender_id'],
                            'sale_price' => $item['sale_price'],
                            'date_sale_start' => $item['date_sale_start'],
                            'date_sale_end' => $item['date_sale_end'],
                            'price' => $item['regular_price'],
                            'bb_points' => $item['bb_points'],
                            'product_stock_status' => $item['product_stock_status'] == 1 ? 'available' : 'not available',
                            'manage_stock' => $item['manage_stock'],
                            'stock_quantity' => $item['stock_quantity'],
                            'sell_eventually' => $item['sell_eventually'],
                            'allow_back_orders' => $item['allow_back_orders'],
                            'weight' => $item['weight'],
                            'length' => $item['length'],
                            'width' => $item['width'],
                            'height' => $item['height'],
                            'shipping_class' => $item['shipping_class'],
                            'synced_at' => $item['syncronize_at'],
                            'synced_method' => 'api_batch_job',
                            'product_long_description' => $desc,
                            'product_long_description_al' => $desc_al,
                            'created_at' => $item['created_at'],
                            'updated_at' => $item['updated_at'],
                        ]
                    );

                    // Queue image upload job if there's an image
                    if (!empty($item['variation_image'])) {
                        $uploadJobs[] = [
                            'model' => $variant,
                            'image_path' => 'https://admin.bybest.shop/storage/products/' . $item['variation_image'],
                            'field' => 'variation_image'
                        ];
                    }

                    $totalProcessed++;
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error("Error processing variant", [
                        'batch_id' => $this->batchId,
                        'variant_id' => $item['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $skippedCount++;
                }
            }

            // Queue upload jobs (outside transaction)
            $venue = \App\Models\Restaurant::find($this->venueId);
            foreach ($uploadJobs as $job) {
                \App\Jobs\UploadPhotoJob::dispatch(
                    $job['model'],
                    $job['image_path'],
                    $job['field'],
                    $venue
                )->onQueue('uploads')
                    ->delay(now()->addSeconds(rand(5, 30))); // Add delay to prevent API rate limits
            }

            Log::info("Successfully processed product variants batch", [
                'batch_id' => $this->batchId,
                'page' => $this->page,
                'processed' => $totalProcessed,
                'skipped' => $skippedCount,
                'upload_jobs' => count($uploadJobs)
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to process product variants sync batch", [
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

            Log::info("Force updated progress counter for product variants", [
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
