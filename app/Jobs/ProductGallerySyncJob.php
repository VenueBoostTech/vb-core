<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductGallery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProductGallerySyncJob implements ShouldQueue
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
        Log::info("Processing product gallery sync batch", [
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
            ])->get($this->apiUrl . 'productgallery-sync', [
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

            // Process product galleries
            $productGalleries = $data['data'];
            $totalProcessed = 0;
            $skippedCount = 0;
            $uploadJobs = [];

            foreach ($productGalleries as $item) {
                DB::beginTransaction();
                try {
                    // Make sure the required fields are available
                    if (!isset($item['id'])) {
                        Log::warning('Product gallery missing ID', ['item' => $item]);
                        $skippedCount++;
                        DB::rollBack();
                        continue;
                    }

                    $productId = isset($productIds[$item['product_id']]) ? $productIds[$item['product_id']] : null;
                    if (!$productId) {
                        Log::warning("Product not found for gallery", [
                            'product_gallery_id' => $item['id'],
                            'product_bybest_id' => $item['product_id']
                        ]);
                        $skippedCount++;
                        DB::rollBack();
                        continue;
                    }

                    $productGallery = ProductGallery::updateOrCreate(
                        ['bybest_id' => $item['id']],
                        [
                            'product_id' => $productId,
                            'bybest_id' => $item['id'],
                            'photo_description' => $item['photo_description'],
                            'created_at' => $item['created_at'],
                            'updated_at' => $item['updated_at'],
                        ]
                    );

                    // Queue image upload job if there's an image
                    if (!empty($item['photo_name'])) {
                        $uploadJobs[] = [
                            'model' => $productGallery,
                            'image_path' => 'https://admin.bybest.shop/storage/products/' . $item['photo_name'],
                            'field' => 'photo_name'
                        ];
                    }

                    $totalProcessed++;
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error("Error processing product gallery", [
                        'batch_id' => $this->batchId,
                        'product_gallery_id' => $item['id'] ?? 'unknown',
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

            Log::info("Successfully processed product galleries batch", [
                'batch_id' => $this->batchId,
                'page' => $this->page,
                'processed' => $totalProcessed,
                'skipped' => $skippedCount,
                'upload_jobs' => count($uploadJobs)
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to process product galleries sync batch", [
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

            Log::info("Force updated progress counter for product galleries", [
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
