<?php

namespace App\Jobs;

use App\Models\Restaurant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Brand;

class SyncProductBatchJob implements ShouldQueue
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
        Log::info("Processing product sync batch", [
            'batch_id' => $this->batchId,
            'page' => $this->page,
            'venue_id' => $this->venueId
        ]);

        try {
            // Fetch data from API
            Log::info("Fetching data from API", ['page' => $this->page]);
            $response = Http::withHeaders([
                'X-App-Key' => $this->apiKey
            ])->get($this->apiUrl . 'products-sync', [
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

                return;
            }

            $data = $response->json();

            if (empty($data) || !isset($data['data']) || empty($data['data'])) {
                Log::info("No data to process", ['batch_id' => $this->batchId]);
                return;
            }

            // Pre-fetch brand IDs for quick lookups
            $brandIds = Brand::withTrashed()->pluck('bybest_id', 'id')->toArray();

            // Process products in chunk of 100
            $products = $data['data'];
            $totalProcessed = 0;

            // Use database transaction
            DB::beginTransaction();

            try {
                $uploadJobs = [];
                $productRows = [];

                foreach ($products as $item) {
                    // Parse data
                    $productData = $this->parseProductData($item, $brandIds);
                    $productRows[] = $productData;

                    // Track image uploads
                    if ($item['product_image']) {
                        $uploadJobs[] = [
                            'bybest_id' => $item['id'],
                            'image_path' => 'https://admin.bybest.shop/storage/products/' . $item['product_image']
                        ];
                    }

                    $totalProcessed++;
                }

                // Use efficient bulk upsert (requires Laravel 8+)
                if (!empty($productRows)) {
                    // Extract bybest_ids for existing products query
                    $bybestIds = array_column($productRows, 'bybest_id');

                    // Get existing products
                    $existingProducts = Product::withTrashed()
                        ->whereIn('bybest_id', $bybestIds)
                        ->get()
                        ->keyBy('bybest_id');

                    // Separate inserts and updates
                    $inserts = [];
                    $updates = [];

                    foreach ($productRows as $row) {
                        $bybestId = $row['bybest_id'];

                        if (isset($existingProducts[$bybestId])) {
                            // Update
                            $product = $existingProducts[$bybestId];
                            foreach ($row as $key => $value) {
                                $product->$key = $value;
                            }
                            $updates[] = $product;
                        } else {
                            // Insert
                            $inserts[] = $row;
                        }
                    }

                    // Save updates
                    foreach ($updates as $product) {
                        $product->save();
                    }

                    // Perform batch insert
                    if (!empty($inserts)) {
                        Product::insert($inserts);
                    }
                }

                DB::commit();

                // Queue upload jobs (outside transaction)
                $venue = Restaurant::find($this->venueId);
                foreach ($uploadJobs as $job) {
                    $product = Product::where('bybest_id', $job['bybest_id'])->first();
                    if ($product) {
                        \App\Jobs\UploadPhotoJob::dispatch($product, $job['image_path'], 'image_path', $venue)
                            ->onQueue('uploads')
                            ->delay(now()->addSeconds(rand(5, 30))); // Add delay to prevent API rate limits
                    }
                }

                Log::info("Successfully processed product batch", [
                    'batch_id' => $this->batchId,
                    'page' => $this->page,
                    'processed' => $totalProcessed
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error processing product batch", [
                    'batch_id' => $this->batchId,
                    'page' => $this->page,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            throw $e;
            Log::error("Failed to process sync batch", [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Requeue with backoff if needed
            if ($this->attempts() < 3) {
                $this->release(30 * $this->attempts());
            }

        }
    }

    /**
     * Parse product data
     */
    private function parseProductData($item, $brandIds)
    {
        $json_title = json_decode($item['product_name']);
        $json_shortdesc = json_decode($item['product_short_description']);
        $json_desc = json_decode($item['product_long_description']);

        $brandId = array_search($item['brand_id'], $brandIds) !== false ?
            array_search($item['brand_id'], $brandIds) : null;

        return [
            'bybest_id' => $item['id'],
            'title' => isset($json_title->en) && $json_title->en != null ? $json_title->en : '',
            'title_al' => isset($json_title->sq) && $json_title->sq != null ? $json_title->sq : '',
            'description' => isset($json_desc->en) && $json_desc->en != null ? $json_desc->en : '',
            'description_al' => isset($json_desc->sq) && $json_desc->sq != null ? $json_desc->sq : '',
            'short_description' => isset($json_shortdesc->en) && $json_shortdesc->en != null ? $json_shortdesc->en : '',
            'short_description_al' => isset($json_shortdesc->sq) && $json_shortdesc->sq != null ? $json_shortdesc->sq : '',
            'price' => $item['regular_price'],
            'available' => $item['product_status'] == 1 ? 1 : 0,
            'is_for_retail' => 0,
            'article_no' => $item['article_no'],
            'sale_price' => $item['sale_price'],
            'date_sale_start' => $item['date_sale_start'],
            'date_sale_end' => $item['date_sale_end'],
            'product_url' => $item['product_url'],
            'product_type' => $item['product_type'] == 1 ? 'single' : 'variable',
            'weight' => $item['weight'],
            'length' => $item['length'],
            'width' => $item['width'],
            'height' => $item['height'],
            'featured' => $item['featured'],
            'is_best_seller' => $item['is_best_seller'],
            'product_tags' => $item['product_tags'],
            'product_sku' => $item['product_sku'],
            'sku_alpha' => $item['sku_alpha'],
            'currency_alpha' => $item['currency_alpha'],
            'tax_code_alpha' => $item['tax_code_alpha'],
            'price_without_tax_alpha' => $item['price_without_tax_alpha'],
            'unit_code_alpha' => $item['unit_code_alpha'],
            'warehouse_alpha' => $item['warehouse_alpha'],
            'bb_points' => $item['bb_points'],
            'product_status' => $item['product_status'],
            'enable_stock' => $item['enable_stock'],
            'product_stock_status' => $item['product_stock_status'],
            'sold_invidually' => $item['sold_invidually'],
            'stock_quantity' => $item['stock_quantity'],
            'low_quantity' => $item['low_quantity'],
            'shipping_class' => $item['shipping_class'],
            'purchase_note' => $item['purchase_note'],
            'menu_order' => $item['menu_order'],
            'allow_back_order' => $item['allow_back_order'],
            'allow_customer_review' => $item['allow_customer_review'],
            'syncronize_at' => $item['syncronize_at'],
            'brand_id' => $brandId,
            'restaurant_id' => $this->venueId,
            'created_at' => $item['created_at'],
            'updated_at' => $item['updated_at'],
            'deleted_at' => $item['deleted_at']
        ];
    }
}
