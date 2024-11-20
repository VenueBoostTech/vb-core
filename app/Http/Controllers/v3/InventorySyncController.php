<?php

namespace App\Http\Controllers\v3;

use App\Exceptions\CustomException;
use App\Http\Controllers\Controller;
use App\Models\ActivityRetail;
use App\Models\Brand;
use App\Models\Collection;
use App\Models\Group;
use App\Models\InventorySync;
use App\Models\Photo;
use App\Models\Product;
use App\Models\Category;
use App\Models\InventoryRetail;
use App\Models\ProductStock;
use App\Models\VbStoreProductVariant;
use App\Models\VbStoreAttribute;
use App\Models\VbStoreAttributeOption;
use App\Models\VbStoreAttributeType;
use App\Models\VbStoreProductAttribute;
use App\Models\ProductGroup;
use App\Models\ProductCategory;
use App\Models\ProductCollection;
use App\Models\ProductGallery;
use App\Models\VbStoreProductVariantAttribute;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Promise;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Jobs\UploadCollectionPhotoJob;
use App\Jobs\UploadPhotoJob;


class InventorySyncController extends Controller
{
    private $bybestApiUrl = 'https://bybest.shop/api/V1/';
    private $bybestApiKey = 'crm.pixelbreeze.xyz-dbz';

    private $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function syncRange($startPage, $endPage)
    {
        $venue = $this->venueService->adminAuthCheck();
        $venue_id = $venue->id;

        $this->syncAttributeTypes();

        $productsProcessed = 0;

        Log::info("Starting sync process for pages {$startPage} to {$endPage}");

        for ($page = $startPage; $page <= $endPage; $page++) {
            $response = $this->fetchFromByBest($page);

            if (!$response || !isset($response['products'])) {
                Log::error('Failed to fetch data for page', ['page' => $page]);
                continue;
            }

            $products = $response['products'];

            DB::beginTransaction();
            try {
                foreach ($products as $product) {
                    $this->syncSingleProduct($product, $venue_id);
                    $productsProcessed++;
                }
                DB::commit();
                Log::info('Synced products for page', ['page' => $page, 'products_synced' => count($products)]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to sync products on page', [
                    'page' => $page,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            // Small delay to prevent overloading
            usleep(100000); // 100ms delay
        }

        Log::info('Sync range completed', [
            'start_page' => $startPage,
            'end_page' => $endPage,
            'total_products_processed' => $productsProcessed
        ]);

        return [
            'message' => 'Sync completed successfully',
            'products_processed' => $productsProcessed
        ];
    }

    public function syncHistory(Request $request)
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

        $inventorySyncs = InventorySync::with(['venues' => function ($query) use ($venue) {
            $query->where('venues.id', $venue->id);
        }])->get();

        return response()->json(['inventory_syncs' => $inventorySyncs]);
    }

    public function startSync(Request $request)
    {
        $venue = $this->venueService->adminAuthCheck();
        $venue_id = $venue->id;

        // Sync attribute types first
        $this->syncAttributeTypes();

        $page = 1;
        $totalPages = 10;
        // $totalPages = $this->getTotalPages();
        $productsProcessed = 0;

        Log::info('Starting sync process', ['total_pages' => $totalPages]);

        while ($page <= $totalPages) {
            $response = $this->fetchFromByBest($page);

            if (!$response || !isset($response['products'])) {
                Log::error('Failed to fetch data for page', ['page' => $page]);
                $page++;
                continue;
            }

            $products = $response['products'];
            $batchSize = count($products);

            DB::beginTransaction();
            try {
                foreach ($products as $product) {
                    $this->syncSingleProduct($product, $venue_id);
                    $productsProcessed++;
                }
                DB::commit();
                Log::info('Synced products for page', ['page' => $page, 'products_synced' => $batchSize]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to sync products on page', [
                    'page' => $page,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $page++;

            // Optionally, you can add a small delay to prevent overloading the server
            usleep(100000); // 100ms delay
        }

        Log::info('Sync completed', ['total_products_processed' => $productsProcessed]);
        return response()->json([
            'message' => 'Sync completed successfully',
            'products_processed' => $productsProcessed
        ], 200);
    }

    private function syncSingleProduct($product, $venue_id)
    {
        // Sync Category
        $category = $this->syncCategory($product['category'], $venue_id);
        if (is_null($category)) {
            return; // Skip this product if category is null
        }

        // Sync Product
        $syncedProduct = $this->syncProduct($product, $category->id, $venue_id);

        // Sync Inventory Retail
        $this->syncInventoryRetail($product, $syncedProduct->id, $venue_id);

        // Sync Variants
        $this->syncVariants($product['variants'], $syncedProduct->id, $venue_id);
    }


    public function fetchFromByBest($page)
    {
        $retryCount = 3;
        $delay = 2000;

        for ($attempt = 1; $attempt <= $retryCount; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->timeout(60)
                    ->get("https://bybest.shop/api/V1/all-time-sync?page=$page");

                $data = $response->json();

                // Check if the response contains the expected data structure
                if (isset($data['products']) && is_array($data['products'])) {
                    return $data;
                }

                if ($attempt === $retryCount) {
                    Log::error('Failed to fetch valid data from ByBest after ' . $retryCount . ' attempts', [
                        'page' => $page,
                        'response' => $data
                    ]);
                    return null;
                }
            } catch (\Exception $e) {
                if ($attempt === $retryCount) {
                    Log::error('Exception when fetching data from ByBest', [
                        'error' => $e->getMessage(),
                        'page' => $page
                    ]);
                    return null;
                }
            }
            usleep($delay * 1000);
        }
    }

    public function getTotalPages()
    {
        $response = $this->fetchFromByBest(1); // Fetch the first page to get pagination info

        if (!$response || !isset($response['pagination']['total_pages'])) {
            Log::error('Failed to fetch initial data for page count', [
                'response' => $response
            ]);
            throw new \Exception('Failed to fetch total pages.');
        }

        return $response['pagination']['total_pages'];
    }


    private function syncCategory($bybest_category, $venue_id)
    {
        $categoryName = $bybest_category['name'] ?? null;

        // Check if category name is null
        if (is_null($categoryName)) {
            \Log::warning('Category name is missing. Skipping category sync.', ['category' => $bybest_category]);
            return null; // Return early or handle as needed
        }

        \Log::info('Creating or updating category with name:', ['title' => $categoryName]);

        return Category::firstOrCreate(
            ['title' => $categoryName, 'restaurant_id' => $venue_id],
            ['description' => '', 'available' => true]
        );
    }



    private function syncProduct($bybest_product, $category_id, $venue_id)
    {
        $brand = Brand::firstOrCreate(
            ['title' => $bybest_product['brand']['name'], 'venue_id' => $venue_id],
            [
                'description' => 'Imported from ByBest',
                'url' => \Str::slug($bybest_product['brand']['name']),
                'total_stock' => 0, // You may want to calculate this separately
            ]
        );

        $product_data = [
            'title' => json_decode($bybest_product['product_name'], true)['en'] ?? '',
            'description' => json_decode($bybest_product['product_long_description'], true)['en'] ?? '',
            'short_description' => json_decode($bybest_product['product_short_description'], true)['en'] ?? '',
            'image_path' => $bybest_product['product_image'],
            'price' => $bybest_product['regular_price'],
            'sale_price' => $bybest_product['sale_price'],
            'date_sale_start' => $bybest_product['date_sale_start'],
            'date_sale_end' => $bybest_product['date_sale_end'],
            'product_url' => $bybest_product['product_url'],
            'product_type' => $bybest_product['product_type'],
            'weight' => $bybest_product['weight'],
            'length' => $bybest_product['length'],
            'width' => $bybest_product['width'],
            'height' => $bybest_product['height'],
            'brand_id' => $brand->id,
            'available' => $bybest_product['product_status'] == 1,
            'is_for_retail' => true,
            'article_no' => $bybest_product['article_no'],
            'restaurant_id' => $venue_id,
            'product_id' => $bybest_product['id'],
        ];


        $product = Product::updateOrCreate(
            ['id' => $bybest_product['id']],  // Remove restaurant_id from here
            $product_data
        );



        $product->categories()->sync([$category_id]);

        return $product;
    }

    private function syncInventoryRetail($bybest_product, $product_id, $venue_id): void
    {
        // Find existing inventory record by SKU and Venue
        $existing_inventory = InventoryRetail::where('sku', $bybest_product['product_sku'] ?? '')
            ->where('venue_id', $venue_id)
            ->first();

        $new_stock_quantity = $bybest_product['product_stock']['stock_quantity'] ?? 0;

        $inventory_data = [
            'venue_id' => $venue_id,
            'product_id' => $product_id,
            'sku' => $bybest_product['product_sku'] ?? null,
            'stock_quantity' => $new_stock_quantity,
            'manage_stock' => $bybest_product['enable_stock'] ?? false,
            'low_stock_threshold' => $bybest_product['low_quantity'] ?? null,
            'sold_individually' => $bybest_product['sold_invidually'] ?? false,
            'article_no' => $bybest_product['article_no'] ?? null,
            'currency_alpha' => $bybest_product['currency_alpha'] ?? null,
            'currency' => $bybest_product['currency'] ?? null,
            'sku_alpha' => $bybest_product['sku_alpha'] ?? null,
            'unit_code_alpha' => $bybest_product['unit_code_alpha'] ?? null,
            'unit_code' => $bybest_product['unit_code'] ?? null,
            'tax_code_alpha' => $bybest_product['tax_code_alpha'] ?? null,
            'warehouse_alpha' => $bybest_product['warehouse_alpha'] ?? null,
            'last_synchronization' => now(),
            'synced_method' => 'api_cronjob',
            'product_stock_status' => $bybest_product['product_stock_status'] ?? null,
        ];

        if ($existing_inventory) {
            // Update existing record
            $inventory_data['updated_at'] = now();
            $inventory = InventoryRetail::where('id', $existing_inventory->id)
                ->update($inventory_data);
        } else {
            // Create new record
            $inventory_data['created_at'] = now();
            $inventory_data['updated_at'] = now();
            $inventory = InventoryRetail::create($inventory_data);
        }

        // Create activity log if quantity has changed
        if (!$existing_inventory || $existing_inventory->stock_quantity != $new_stock_quantity) {
            $old_quantity = $existing_inventory ? $existing_inventory->stock_quantity : 0;
            $quantity_change = $new_stock_quantity - $old_quantity;

            ActivityRetail::create([
                'venue_id' => $venue_id,
                'inventory_retail_id' => $inventory->id,
                'activity_type' => 'sync',
                'description' => "Stock quantity updated via API sync",
                'data' => json_encode([
                    'old_quantity' => $old_quantity,
                    'new_quantity' => $new_stock_quantity,
                    'change' => $quantity_change
                ])
            ]);
        }
    }

    public function collectionSync(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!@$venue->id) {
            return response()->json(['message' => 'Venue not found.'], 500);
        }
        // Get parameters from request with default values
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        // $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;

        // do {
            try {

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get(
                    $this->bybestApiUrl . 'collection-sync',
                [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestCollections = $response->json();

                if (empty($bybestCollections) || !isset($bybestCollections['data'])) {
                    // break; // No more collections to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }

                $collections = $bybestCollections['data']; // Assuming 'data' contains the actual collections

                // foreach (array_chunk($collections, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($collections as $bybestCollection) {
                            DB::beginTransaction();
                            try {
                                \Log::info('Processing collection', ['collection' => $bybestCollection]);

                                // Make sure the required fields are available
                                if (!isset($bybestCollection['id'])) {
                                    \Log::error('Collection missing id', ['collection' => $bybestCollection]);
                                    $skippedCount++;
                                    continue;
                                }


                                $json_name = json_decode($bybestCollection['name']);
                                $json_desc = json_decode($bybestCollection['description']);


                                $name = (isset($json_name->en) && isset($json_name->en) != null) ? $json_name->en : '';
                                $name_al = (isset($json_name->sq) && isset($json_name->sq) != null) ? $json_name->sq : '';
                                $desc = (isset($json_desc->en) && isset($json_desc->en) != null) ? $json_desc->en : '';
                                $desc_al = (isset($json_desc->sq) && isset($json_desc->sq) != null) ? $json_desc->sq : '';


                                $collection = Collection::withTrashed()->updateOrCreate(
                                    ['bybest_id' => $bybestCollection['id']],
                                    [
                                        'name' => $name,
                                        'name_al' => $name_al,
                                        'description' => $desc,
                                        'description_al' => $desc_al,
                                        'slug' => $bybestCollection['collection_url'],
                                        //'logo_path' => 'https://admin.bybest.shop/storage/collections/' . $bybestCollection['photo'],
                                        'venue_id' => $venue->id,
                                        'bybest_id' => $bybestCollection['id'],
                                        'created_at' => $bybestCollection['created_at'],
                                        'updated_at' => $bybestCollection['updated_at'],
                                        'deleted_at' => $bybestCollection['deleted_at'] ? Carbon::parse($bybestCollection['deleted_at']) : null
                                    ]
                                );

                                // Dispatch job for photo upload
                                if ($bybestCollection['photo']) {
                                    \Log::info('Dispatching UploadCollectionPhotoJob', [
                                        'collection_id' => $collection->id,
                                        'photo_url' => $bybestCollection['photo'],
                                    ]);

                                    UploadPhotoJob::dispatch($collection, 'https://admin.bybest.shop/storage/collections/' . $bybestCollection['photo'], 'logo_path', $venue);
                                }

                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                $skippedCount++;
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                \Log::info("Processed {$processedCount} collections so far.");

                // $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in collection sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
                return response()->json([
                    "message" => "Error in collection sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($collections) == $perPage); // Use $collections here

        return response()->json([
            'message' => 'Collections sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestCollections['total_pages']) ? $bybestCollections['total_pages'] : null,
            'current_page' => isset($bybestCollections['current_page']) ? $bybestCollections['current_page'] : null
        ], 200);
    }

    public function productSync(Request $request)
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!@$venue->id) {
            return response()->json(['message' => 'Venue not found.'], 500);
        }
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        // $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;

        ini_set('max_execution_time', 3000000);
        // do {
            try {

                error_log("log page $page");

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'products-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    return response()->json(['message' => 'No more data to process'], 500);
                }

                $products = $bybestData['data'];

                $brandIds = Brand::withTrashed()->pluck('bybest_id','id')->toArray();
                if (count($brandIds) > 0) {
                    $brandIds = array_filter($brandIds);
                    if (count($brandIds) == 0) {
                        return response()->json(['message' => 'No brands are exists'], 500);
                    }
                }

                // foreach (array_chunk($products, $batchSize) as $batch) {
                    foreach ($products as $item) {
                        // DB::transaction(function () use ($item, $venue, &$skippedCount, &$processedCount) {
                            DB::beginTransaction();
                            try {
                                \Log::info('Processing product', ['item' => $item]);

                                // Make sure the required fields are available
                                if (!isset($item['id'])) {
                                    \Log::error('Product missing id', ['item' => $item]);
                                    $skippedCount++;
                                    continue;
                                }
                                error_log("Processing product " . $item['id']);

                                $json_title = json_decode($item['product_name']);
                                $json_shortdesc = json_decode($item['product_short_description']);
                                $json_desc = json_decode($item['product_long_description']);

                                $title = (isset($json_title->en) && $json_title->en != null) ? $json_title->en : '';
                                $title_al = (isset($json_title->sq) && isset($json_title->en) != null) ? $json_title->sq : '';
                                $shortdesc = (isset($json_shortdesc->en) && isset($json_shortdesc->en) != null) ? $json_shortdesc->en : '';
                                $shortdesc_al = (isset($json_shortdesc->sq) && isset($json_shortdes->sq) != null) ? $json_shortdesc->sq : '';
                                $desc = (isset($json_desc->en) && isset($json_desc->en) != null) ? $json_desc->en : '';
                                $desc_al = (isset($json_desc->sq) && isset($json_desc->sq) != null) ? $json_desc->sq : '';

                                // $brand = Brand::withTrashed()->where('bybest_id', $item['brand_id'])->first();
                                $synced_product = Product::updateOrCreate(
                                    ['bybest_id' => $item['id']],
                                    [
                                        'title' => $title,
                                        'title_al' => $title_al,
                                        'description' => $desc,
                                        'description_al' => $desc_al,
                                        'short_description' => $shortdesc,
                                        'short_description_al' => $shortdesc_al,
                                        //'image_path' => 'https://admin.bybest.shop/storage/products/' . $item['product_image'],
                                        'image_thumbnail_path' => null,
                                        'price' => $item['regular_price'],
                                        'order_method' => null,
                                        'available' => $item['product_status'] == 1 ? 1 : 0,
                                        'is_for_retail' => 0,
                                        'article_no' => $item['article_no'],
                                        'additional_code' => null,
                                        'sale_price' => $item['sale_price'],
                                        'date_sale_start' => $item['date_sale_start'],
                                        'date_sale_end' => $item['date_sale_end'],
                                        'product_url' => $item['product_url'],
                                        'product_type' => $item['product_type'] == 1 ? 'single' : 'variable',
                                        'weight' => $item['weight'],
                                        'length' => $item['length'],
                                        'width' => $item['width'],
                                        'height' => $item['height'],
                                        'unit_measure' => null,
                                        'scan_time' => null,
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
                                        'brand_id' => array_search($item['brand_id'], $brandIds) !== false ? array_search($item['brand_id'], $brandIds) : null,
                                        'restaurant_id' => $venue->id,
                                        'bybest_id' => $item['id'],
                                        'third_party_product_id' => null,
                                        'created_at' => $item['created_at'],
                                        'updated_at' => $item['updated_at'],
                                        'deleted_at' => $item['deleted_at']
                                    ]
                                );

                                // Dispatch job for photo upload
                                if ($item['product_image']) {
                                    \Log::info('Dispatching UploadPhotoJob', [
                                        'product_id' => $synced_product->id,
                                        'photo_url' => $item['product_image'],
                                    ]);
                                    UploadPhotoJob::dispatch($synced_product, 'https://admin.bybest.shop/storage/products/' . $item['product_image'], 'image_path', $venue);
                                }
                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                $skippedCount++;
                                DB::rollBack();
                            }
                        // });
                    }
                // }

                \Log::info("Processed {$processedCount} products so far.");

                // $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in product sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);

                error_log('Error in product sync' . $th->getMessage());
                return response()->json([
                    "message" => "Error in products sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($products) == $perPage);

        return response()->json([
            'message' => 'Products sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }

    public function brandSync(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!@$venue->id) {
            return response()->json(['message' => 'Venue not found.'], 500);
        }
        $skippedCount = 0;
        $processedCount = 0;
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        $batchSize = $request->input('batch_size', 50);
        ini_set('max_execution_time', 3000000);

        // do {
            try {

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'brands-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);


                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }

                $brands = $bybestData['data'];

                // foreach (array_chunk($brands, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($brands as $item) {
                            DB::beginTransaction();
                            try {
                                \Log::info('Processing product', ['item' => $item]);

                                // Make sure the required fields are available
                                if (!isset($item['id'])) {
                                    \Log::error('Product missing id', ['item' => $item]);
                                    $skippedCount++;
                                    continue;
                                }

                                $json_shortdesc = json_decode($item['short_description']);
                                $json_desc = json_decode($item['long_description']);

                                $shortdesc = (isset($json_shortdesc->en) && isset($json_shortdesc->en) != null) ? $json_shortdesc->en : '';
                                $shortdesc_al = (isset($json_shortdesc->sq) && isset($json_shortdesc->sq) != null) ? $json_shortdesc->sq : '';
                                $desc = (isset($json_desc->en) && isset($json_desc->en) != null) ? $json_desc->en : '';
                                $desc_al = (isset($json_desc->sq) && isset($json_desc->sq) != null) ? $json_desc->sq : '';

                                $synced_brand = Brand::updateOrCreate(
                                    ['bybest_id' => $item['id']],
                                    [
                                        'title' => $item['brand_name'],
                                        'description' => $desc,
                                        'description_al' => $desc_al,
                                        'short_description' => $shortdesc,
                                        'short_description_al' => $shortdesc_al,
                                        //'logo_path' => 'https://admin.bybest.shop/storage/brands/' . $item['brand_logo_color'],
                                        //'white_logo_path' => 'https://admin.bybest.shop/storage/brands/' . $item['brand_logo_white'],
                                        //'sidebar_logo_path' => 'https://admin.bybest.shop/storage/brands/' . $item['brand_logo_sidebar'],
                                        'venue_id' => $venue->id,
                                        'url' => $item['brand_url'],
                                        'total_stock' => 0,
                                        'parent_id' => null,
                                        'bybest_id' => $item['id'],
                                        'keywords' => $item['keywords'],
                                        'more_info' => $item['more_info'],
                                        'brand_order_no' => $item['brand_order_no'],
                                        'status_no' => $item['status_no'],
                                        'created_at' => $item['created_at'],
                                        'updated_at' => $item['updated_at'],
                                        'deleted_at' => $item['deleted_at']
                                    ]
                                );

                                // Dispatch job for photo upload
                                if ($item['brand_logo_color']) {

                                    \Log::info('Dispatching UploadPhotoJob', [
                                        'brand_id' => $synced_brand->id,
                                        'photo_url' => $item['brand_logo_color'],
                                    ]);

                                    UploadPhotoJob::dispatch($synced_brand, 'https://admin.bybest.shop/storage/brands/' . $item['brand_logo_color'], 'logo_path', $venue);
                                }
                                if ($item['brand_logo_white']) {

                                    \Log::info('Dispatching UploadPhotoJob', [
                                        'brand_id' => $synced_brand->id,
                                        'photo_url' => $item['brand_logo_white'],
                                    ]);

                                    UploadPhotoJob::dispatch($synced_brand, 'https://admin.bybest.shop/storage/brands/' . $item['brand_logo_white'], 'white_logo_path', $venue);
                                }
                                if ($item['brand_logo_sidebar']) {

                                    \Log::info('Dispatching UploadPhotoJob', [
                                        'brand_id' => $synced_brand->id,
                                        'photo_url' => $item['brand_logo_sidebar'],
                                    ]);

                                    UploadPhotoJob::dispatch($synced_brand, 'https://admin.bybest.shop/storage/brands/' . $item['brand_logo_sidebar'], 'sidebar_logo_path', $venue);
                                }
                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                $skippedCount++;
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                \Log::info("Processed {$processedCount} brands so far.");

                // $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in brands sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);

                return response()->json([
                    "message" => "Error in brands sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($brands) == $perPage);

        return response()->json([
            'message' => 'brands sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }

    public function groupsSync(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!@$venue->id) {
            return response()->json(['message' => 'Venue not found.'], 500);
        }
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;

        // do {
            try {

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'groups-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    // break; // No more data to process
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $groups = $bybestData['data'];

                // foreach (array_chunk($groups, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($groups as $item) {
                            // Make sure the required fields are available
                            DB::beginTransaction();
                            try {
                                if (!isset($item['id'])) {
                                    \Log::error('Product missing id', ['item' => $item]);
                                    $skippedCount++;
                                    continue;
                                }
                                $json_name = json_decode($item['group_name']);
                                $json_desc = json_decode($item['description']);
                                $namedesc = (isset($json_name->en) && isset($json_name->en) != null) ? $json_name->en : '';
                                $namedesc_al = (isset($json_name->sq) && isset($json_name->sq) != null) ? $json_name->sq : '';
                                $desc = (isset($json_desc->en) && isset($json_desc->en) != null) ? $json_desc->en : '';
                                $desc_al = (isset($json_desc->sq) && isset($json_desc->sq) != null) ? $json_desc->sq : '';

                                Group::updateOrCreate(
                                    ['bybest_id' => $item['id']],
                                    [
                                        'venue_id' => $venue->id,
                                        'group_name' => $namedesc,
                                        'group_name_al' => $namedesc_al,
                                        'description' => $desc,
                                        'description_al' => $desc_al,
                                        'bybest_id' => $item['id'],
                                        'created_at' => $item['created_at'],
                                        'updated_at' => $item['updated_at'],
                                    ]
                                );

                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                $skippedCount++;
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                error_log("Processed {$processedCount} groups so far.");

                $page++;
            } catch (\Throwable $th) {
                error_log("Error in groups sync " . $th->getMessage());
                return response()->json([
                    "message" => "Error in groups sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($groups) == $perPage);

        return response()->json([
            'message' => 'groups sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }

    public function categoriesSync(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!@$venue->id) {
            return response()->json(['message' => 'Venue not found.'], 500);
        }
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;
        ini_set('max_execution_time', 3000000);
        // do {
            try {

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'categories-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }


                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }

                $categories = $bybestData['data'];

                $parentCategoryIds = Category::pluck('bybest_id','id')->toArray();
                if (count($parentCategoryIds) > 0) {
                    $parentCategoryIds = array_filter($parentCategoryIds);
                    if (count($parentCategoryIds) == 0) {
                        return response()->json(['message' => 'No categories are exists'], 500);
                    }
                }

                // foreach (array_chunk($categories, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($categories as $item) {

                            DB::beginTransaction();
                            try {

                                \Log::info('Processing categories', ['item' => $item]);

                                // Make sure the required fields are available
                                if (!isset($item['id'])) {
                                    \Log::error('categories missing id', ['item' => $item]);
                                    $skippedCount++;
                                    continue;
                                }

                                $json_category = json_decode($item['category']);
                                $json_title = json_decode($item['title']);
                                $json_subtitle = json_decode($item['subtitle']);
                                $json_desc = json_decode($item['description']);

                                $cat = (isset($json_category->en) && isset($json_category->en) != null) ? $json_category->en : '';
                                $cat_al = (isset($json_category->sq) && isset($json_category->sq) != null) ? $json_category->sq : '';
                                $title = (isset($json_title->en) && isset($json_title->en) != null) ? $json_title->en : '';
                                $title_al = (isset($json_title->sq) && isset($json_title->sq) != null) ? $json_title->sq : '';
                                $subtitle = (isset($json_subtitle->en) && isset($json_subtitle->en) != null) ? $json_subtitle->en : '';
                                $subtitle_al = (isset($json_subtitle->sq) && isset($json_subtitle->sq) != null) ? $json_subtitle->sq : '';
                                $desc = (isset($json_desc->en) && isset($json_desc->en) != null) ? $json_desc->en : '';
                                $desc_al = (isset($json_desc->sq) && isset($json_desc->sq) != null) ? $json_desc->sq : '';

                                // $parentCat = Category::where('bybest_id', $item['parent_id'])->first();

                                $synced_category = Category::updateOrCreate(
                                    ['bybest_id' => $item['id']],
                                    [
                                        'restaurant_id' => $venue->id,
                                        'title' => $title,
                                        'title_al' => $title_al,
                                        'description' => $desc,
                                        'description_al' => $desc_al,
                                        'parent_id' => array_search($item['parent_id'], $parentCategoryIds) !== false ? array_search($item['parent_id'], $parentCategoryIds) : null,
                                        'category' => $cat,
                                        'category_al' => $cat_al,
                                        'category_url' => $item['category_url'],
                                        'subtitle' => $subtitle,
                                        'subtitle_al' => $subtitle_al,
                                        'photo' => 'https://admin.bybest.shop/storage/categories/' . $item['photo'],
                                        'order_no' => $item['order_no'],
                                        'visible' => $item['visible'],
                                        'bybest_id' => $item['id'],
                                        'created_at' => $item['created_at'],
                                        'updated_at' => $item['updated_at']
                                    ]
                                );

                                // Dispatch job for photo upload
                                if ($item['photo']) {
                                    \Log::info('Dispatching UploadPhotoJob', [
                                        'cat_id' => $synced_category->id,
                                        'photo_url' => $item['photo'],
                                    ]);

                                    UploadPhotoJob::dispatch($synced_category, 'https://admin.bybest.shop/storage/categories/' . $item['photo'], 'photo', $venue);
                                }
                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                $skippedCount++;
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                \Log::info("Processed {$processedCount} categories so far.");

                $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in categories sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
                error_log('Error in categories sync ' . $th->getMessage());
                return response()->json([
                    "message" => "Error in categories sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($categories) == $perPage);

        return response()->json([
            'message' => 'categories sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }

    public function attributesSync(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!@$venue->id) {
            return response()->json(['message' => 'Venue not found.'], 500);
        }
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        // $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;

        try {

            $response = Http::withHeaders([
                'X-App-Key' => $this->bybestApiKey
            ])->get($this->bybestApiUrl . 'attributes-sync', [
                // 'page' => $page,
                // 'per_page' => $perPage
            ]);

            if (!$response->successful()) {
                return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
            }

            $bybestData = $response->json();
            $attr_types = $bybestData['attr_types'];

            DB::transaction(function () use ($attr_types, $venue, &$skippedCount, &$processedCount) {
                foreach ($attr_types as $item) {
                    \Log::info('Processing attr types', ['item' => $item]);

                    // Make sure the required fields are available
                    if (!isset($item['id'])) {
                        \Log::error('attr types missing id', ['item' => $item]);
                        continue;
                    }

                    $json_type = json_decode($item['type']);
                    $json_desc = json_decode($item['description']);

                    $type = (isset($json_type->en) && isset($json_type->en) != null) ? $json_type->en : '';
                    $type_al = (isset($json_type->sq) && isset($json_type->sq) != null) ? $json_type->sq : '';
                    $desc = (isset($json_desc->en) && isset($json_desc->en) != null) ? $json_desc->en : '';
                    $desc_al = (isset($json_desc->sq) && isset($json_desc->sq) != null) ? $json_desc->sq : '';

                    VbStoreAttributeType::updateOrCreate(
                        ['bybest_id' => $item['id']],
                        [
                            'type' => $type,
                            'type_al' => $type_al,
                            'description' => $desc,
                            'description_al' => $desc_al,
                            'bybest_id' => $item['id'],
                            'created_at' => $item['created_at'],
                            'updated_at' => $item['updated_at'],
                        ]
                    );
                }
            });
        } catch (\Throwable $th) {
            \Log::error('Error in attr types sync', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            return response()->json([
                "message" => "Error in attr types sync",
                "error" => $th->getMessage()
            ], 503);
        }

        // do {
            try {

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'attributes-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['attributes'])) {
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);

                }

                $attributes = $bybestData['attributes'];

                // foreach (array_chunk($attributes, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($attributes as $item) {
                            DB::beginTransaction();
                            try {
                                \Log::info('Processing attributes', ['item' => $item]);

                                // Make sure the required fields are available
                                if (!isset($item['id'])) {
                                    \Log::error('attributes missing id', ['item' => $item]);
                                    $skippedCount++;
                                    continue;
                                }

                                $json_name = json_decode($item['attr_name']);
                                $json_desc = json_decode($item['attr_description']);

                                $name = (isset($json_name->en) && isset($json_name->en) != null) ? $json_name->en : '';
                                $name_al = (isset($json_name->sq) && isset($json_name->sq) != null) ? $json_name->sq : '';
                                $desc = (isset($json_desc->en) && isset($json_desc->en) != null) ? $json_desc->en : '';
                                $desc_al = (isset($json_desc->sq) && isset($json_desc->sq) != null) ? $json_desc->sq : '';

                                $attrType = VbStoreAttributeType::where('bybest_id', $item['type_id'])->first();

                                VbStoreAttribute::updateOrCreate(
                                    ['bybest_id' => $item['id']],
                                    [
                                        'type_id' => $attrType->id,
                                        'attr_name' => $name,
                                        'attr_name_al' => $name_al,
                                        'attr_url' => $item['attr_url'],
                                        'attr_description' => $desc,
                                        'attr_description_al' => $desc_al,
                                        'order_id' => $item['order_id'],
                                        'bybest_id' => $item['id'],
                                        'created_at' => $item['created_at'],
                                        'updated_at' => $item['updated_at'],
                                    ]
                                );

                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                $skippedCount++;
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                \Log::info("Processed {$processedCount} attributes so far.");

                // $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in attributes sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
                error_log('Error in attributes sync ' . $th->getMessage());
                return response()->json([
                    "message" => "Error in attributes sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($attributes) == $perPage);

        return response()->json([
            'message' => 'attributes sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }

    public function attributesOptionsSync(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!@$venue->id) {
            return response()->json(['message' => 'Venue not found.'], 500);
        }
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        // $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;

        // do {
            try {

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'attroptions-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }

                $attroptions = $bybestData['data'];

                $attributeIds = VbStoreAttribute::pluck('bybest_id','id')->toArray();
                if (count($attributeIds) > 0) {
                    $attributeIds = array_filter($attributeIds);
                    if (count($attributeIds) == 0) {
                        return response()->json(['message' => 'No attributes are exists'], 500);
                    }
                }

                // foreach (array_chunk($attroptions, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($attroptions as $item) {
                            DB::beginTransaction();
                            try {
                                \Log::info('Processing attroptions', ['item' => $item]);

                                // Make sure the required fields are available
                                if (!isset($item['id'])) {
                                    \Log::error('attroptions missing id', ['item' => $item]);
                                    $skippedCount++;
                                    continue;
                                }

                                $json_name = json_decode($item['option_name']);
                                $json_desc = json_decode($item['option_description']);

                                $name = (isset($json_name->en) && isset($json_name->en) != null) ? $json_name->en : '';
                                $name_al = (isset($json_name->sq) && isset($json_name->sq) != null) ? $json_name->sq : '';
                                $desc = (isset($json_desc->en) && isset($json_desc->en) != null) ? $json_desc->en : '';
                                $desc_al = (isset($json_desc->sq) && isset($json_desc->sq) != null) ? $json_desc->sq : '';

                                // $attr = VbStoreAttribute::where('bybest_id', $item['attribute_id'])->first();

                                $attrOption = VbStoreAttributeOption::updateOrCreate(
                                    ['bybest_id' => $item['id']],
                                    [
                                        'attribute_id' => array_search($item['attribute_id'], $attributeIds) !== false ? array_search($item['attribute_id'], $attributeIds) : null,
                                        'option_name' => $name,
                                        'option_name_al' => $name_al,
                                        'option_url' => $item['option_url'],
                                        'option_description' => $desc,
                                        'option_description_al' => $desc_al,
                                        // 'option_photo' => 'https://admin.bybest.shop/storage/options/' . $item['option_photo'],
                                        'order_id' => $item['order_id'],
                                        'bybest_id' => $item['id'],
                                        'created_at' => $item['created_at'],
                                        'updated_at' => $item['updated_at'],
                                    ]
                                );

                                // Dispatch job for photo upload
                                if ($item['option_photo']) {
                                    \Log::info('Dispatching UploadPhotoJob', [
                                        'cat_id' => $attrOption->id,
                                        'photo_url' => $item['option_photo'],
                                    ]);

                                    UploadPhotoJob::dispatch($attrOption, 'https://admin.bybest.shop/storage/options/' . $item['option_photo'], 'option_photo', $venue);
                                }
                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                $skippedCount++;
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                \Log::info("Processed {$processedCount} attroptions so far.");

                // $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in attroptions sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
                error_log('Error in attroptions sync ' . $th->getMessage());
                return response()->json([
                    "message" => "Error in attroptions sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($attroptions) == $perPage);

        return response()->json([
            'message' => 'attroptions sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }

    public function productVariantsSync(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!@$venue->id) {
            return response()->json(['message' => 'Venue not found.'], 500);
        }
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        // $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;
        ini_set('max_execution_time', 3000000);

        // do {
            try {

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'productvariation-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }
                
                error_log("page $page");

                $variations = $bybestData['data'];

                $productIds = Product::withTrashed()->pluck('bybest_id','id')->toArray();
                if (count($productIds) > 0) {
                    $productIds = array_filter($productIds);
                    if (count($productIds) == 0) {
                        return response()->json(['message' => 'No product are exists'], 500);
                    }
                }

                // foreach (array_chunk($variations, $batchSize) as $batch) {
                    foreach ($variations as $item) {
                        DB::beginTransaction();
                        try {
                            \Log::info('Processing variations', ['item' => $item]);

                            // Make sure the required fields are available
                            if (!isset($item['id'])) {
                                \Log::error('variations missing id', ['item' => $item]);
                                $skippedCount++;
                                continue;
                            }

                            error_log("processed " . $item['id']);

                            $json_desc = json_decode($item['product_long_description']);

                            $desc = (isset($json_desc->en) && isset($json_desc->en) != null) ? $json_desc->en : '';
                            $desc_al = (isset($json_desc->sq) && isset($json_desc->sq) != null) ? $json_desc->sq : '';

                            // $product = Product::withTrashed()->where('bybest_id', $item['product_id'])->first();
                            
                            $attrOption = VbStoreProductVariant::updateOrCreate(
                                ['bybest_id' => $item['id']],
                                [
                                    'product_id' => array_search($item['product_id'], $productIds) !== false ? array_search($item['product_id'], $productIds) : null,
                                    'venue_id' => $venue->id,
                                    'name' =>  $item['varation_name'],
                                    // 'variation_image' => 'https://admin.bybest.shop/storage/products/' . $item['variation_image'],
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
                                    'synced_method' => 'api_cronjob',
                                    'product_long_description' => $desc,
                                    'product_long_description_al' => $desc_al,
                                    'bybest_id' => $item['id'],
                                    'created_at' => $item['created_at'],
                                    'updated_at' => $item['updated_at'],
                                ]
                            );

                            // Dispatch job for photo upload
                            if ($item['variation_image']) {
                                \Log::info('Dispatching UploadPhotoJob', [
                                    'cat_id' => $attrOption->id,
                                    'photo_url' => $item['variation_image'],
                                ]);

                                UploadPhotoJob::dispatch($attrOption, 'https://admin.bybest.shop/storage/products/' . $item['variation_image'], 'variation_image', $venue);
                            }
                            $processedCount++;
                            DB::commit();
                        } catch (\Exception $e) {
                            $skippedCount++;
                            DB::rollBack();
                        }
                    }
                // }

                \Log::info("Processed {$processedCount} variations so far.");

                // $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in variations sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
                error_log('Error in variations sync ' . $th->getMessage());
                return response()->json([
                    "message" => "Error in variations sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($variations) == $perPage);

        return response()->json([
            'message' => 'variations sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }

    public function productAttributesSync(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!@$venue->id) {
            return response()->json(['message' => 'Venue not found.'], 500);
        }
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        // $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;
        ini_set('max_execution_time', 3000000);
        // do {
            try {

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'productattr-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }

                error_log("page $page");

                $productattrs = $bybestData['data'];

                $productIds = Product::withTrashed()->pluck('bybest_id','id')->toArray();
                if (count($productIds) > 0) {
                    $productIds = array_filter($productIds);
                    if (count($productIds) == 0) {
                        return response()->json(['message' => 'No products are exists'], 500);
                    }
                }

                $attributeOptionIds = VbStoreAttributeOption::pluck('bybest_id','id')->toArray();
                if (count($attributeOptionIds) > 0) {
                    $attributeOptionIds = array_filter($attributeOptionIds);
                    if (count($attributeOptionIds) == 0) {
                        return response()->json(['message' => 'No attribute options are exists'], 500);
                    }
                }

                // foreach (array_chunk($productattrs, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($productattrs as $item) {
                            DB::beginTransaction();
                            try {
                                \Log::info('Processing productattrs', ['item' => $item]);

                                // Make sure the required fields are available
                                if (!isset($item['id'])) {
                                    \Log::error('productattrs missing id', ['item' => $item]);
                                    $skippedCount++;
                                    continue;
                                }
                                error_log("Processing  " . $item['id']);

                                // $product = Product::withTrashed()->where('bybest_id', $item['product_id'])->first();
                                // $attr = VbStoreAttributeOption::where('bybest_id', $item['atribute_id'])->first();

                                VbStoreProductAttribute::updateOrCreate(
                                    ['bybest_id' => $item['id']],
                                    [
                                        'product_id' => array_search($item['product_id'], $productIds) !== false ? array_search($item['product_id'], $productIds) : null,
                                        'attribute_id' => array_search($item['atribute_id'], $attributeOptionIds) !== false ? array_search($item['atribute_id'], $attributeOptionIds) : null,
                                        'venue_id' => $venue->id,
                                        'bybest_id' => $item['id'],
                                        'created_at' => $item['created_at'],
                                        'updated_at' => $item['updated_at'],
                                    ]
                                );

                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                $skippedCount++;
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                \Log::info("Processed {$processedCount} productattrs so far.");

                // $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in productattrs sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
                error_log('Error in productattrs sync ' . $th->getMessage());
                return response()->json([
                    "message" => "Error in productattrs sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($productattrs) == $perPage);

        return response()->json([
            'message' => 'productattrs sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }

    public function productVariantAttributesSync(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!@$venue->id) {
            return response()->json(['message' => 'Venue not found.'], 500);
        }
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        // $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;
        ini_set('max_execution_time', 3000000);
        // do {
            try {

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'product-variant-attributes', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }

                error_log("page $page");

                $productVariantAttrs = $bybestData['data'];

                // $productVariantIds = VbStoreProductVariant::withTrashed()->pluck('bybest_id','id')->toArray();
                // if (count($productVariantIds) > 0) {
                //     $productVariantIds = array_filter($productVariantIds);
                //     if (count($productVariantIds) == 0) {
                //         return response()->json(['message' => 'No products variants are exists'], 500);
                //     }
                // }

                // $attributeOptionIds = VbStoreAttributeOption::pluck('bybest_id','id')->toArray();
                // if (count($attributeOptionIds) > 0) {
                //     $attributeOptionIds = array_filter($attributeOptionIds);
                //     if (count($attributeOptionIds) == 0) {
                //         return response()->json(['message' => 'No attribute options are exists'], 500);
                //     }
                // }

                // foreach (array_chunk($productVariantAttrs, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($productVariantAttrs as $item) {
                            DB::beginTransaction();
                            try {
                                \Log::info('Processing productVariantAttrs', ['item' => $item]);

                                // Make sure the required fields are available
                                if (!isset($item['id'])) {
                                    \Log::error('productVariantAttrs missing id', ['item' => $item]);
                                    $skippedCount++;
                                    continue;
                                }
                                error_log("Processing  " . $item['id']);

                                $variant = VbStoreProductVariant::withTrashed()->where('bybest_id', $item['variant_id'])->first();
                                $attr = VbStoreAttributeOption::where('bybest_id', $item['atribute_id'])->first();

                                VbStoreProductVariantAttribute::updateOrCreate(
                                    ['bybest_id' => $item['id']],
                                    [
                                        // 'variant_id' => array_search($item['variant_id'], $productVariantIds) !== false ? array_search($item['variant_id'], $productVariantIds) : null,
                                        // 'attribute_id' => array_search($item['atribute_id'], $attributeOptionIds) !== false ? array_search($item['atribute_id'], $attributeOptionIds) : null,
                                        'variant_id' => $variant->id,
                                        'attribute_id' => $attr->id,
                                        'venue_id' => $venue->id,
                                        'bybest_id' => $item['id'],
                                        'created_at' => $item['created_at'],
                                        'updated_at' => $item['updated_at'],
                                        'deleted_at' => $item['deleted_at'],
                                    ]
                                );

                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                $skippedCount++;
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                \Log::info("Processed {$processedCount} productVariantAttrs so far.");

                // $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in productVariantAttrs sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
                error_log('Error in productVariantAttrs sync ' . $th->getMessage());
                return response()->json([
                    "message" => "Error in productVariantAttrs sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($productVariantAttrs) == $perPage);

        return response()->json([
            'message' => 'productattrs sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }

    public function productGroupsSync(Request $request): \Illuminate\Http\JsonResponse
    {
        // $venue = $this->venueService->adminAuthCheck();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        // $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;
        ini_set('max_execution_time', 3000000);
        // do {
            try {

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'productgroups-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }
                error_log("page $page");
                $productgroups = $bybestData['data'];

                $productIds = Product::withTrashed()->pluck('bybest_id','id')->toArray();
                if (count($productIds) > 0) {
                    $productIds = array_filter($productIds);
                    if (count($productIds) == 0) {
                        return response()->json(['message' => 'No products are exists'], 500);
                    }
                }

                $groupIds = Group::pluck('bybest_id','id')->toArray();
                if (count($groupIds) > 0) {
                    $groupIds = array_filter($groupIds);
                    if (count($groupIds) == 0) {
                        return response()->json(['message' => 'No groups are exists'], 500);
                    }
                }

                // foreach (array_chunk($productgroups, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($productgroups as $item) {
                            DB::beginTransaction();
                            try {
                                \Log::info('Processing productgroups', ['item' => $item]);

                                // Make sure the required fields are available
                                if (!isset($item['id'])) {
                                    \Log::error('productattrs missing id', ['item' => $item]);
                                    $skippedCount++;
                                    continue;
                                }
                                error_log("Processing  " . $item['id']);
                                // $product = Product::withTrashed()->where('bybest_id', $item['product_id'])->first();
                                // $group = Group::where('bybest_id', $item['group_id'])->first();

                                ProductGroup::updateOrCreate(
                                    ['bybest_id' => $item['id']],
                                    [
                                        'product_id' => array_search($item['product_id'], $productIds) !== false ? array_search($item['product_id'], $productIds) : null,
                                        'group_id' => array_search($item['group_id'], $groupIds) !== false ? array_search($item['group_id'], $groupIds) : null,
                                        'bybest_id'  => $item['id'],
                                        'created_at' => $item['created_at'],
                                        'updated_at' => $item['updated_at'],
                                    ]
                                );

                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                $skippedCount++;
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                \Log::info("Processed {$processedCount} productgroups so far.");

                // $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in productgroups sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
                error_log('Error in productgroups sync ' . $th->getMessage());
                return response()->json([
                    "message" => "Error in productgroups sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($productgroups) == $perPage);

        return response()->json([
            'message' => 'productgroups sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }

    public function productCategorySync(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        // $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;
        ini_set('max_execution_time', 3000000);
        // do {
            try {

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'productcategory-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }
                error_log("page $page");
                $productcategories = $bybestData['data'];

                $productIds = Product::withTrashed()->pluck('bybest_id','id')->toArray();
                if (count($productIds) > 0) {
                    $productIds = array_filter($productIds);
                    if (count($productIds) == 0) {
                        return response()->json(['message' => 'No products are exists'], 500);
                    }
                }

                $categoryIds = Category::pluck('bybest_id','id')->toArray();
                if (count($categoryIds) > 0) {
                    $categoryIds = array_filter($categoryIds);
                    if (count($categoryIds) == 0) {
                        return response()->json(['message' => 'No category are exists'], 500);
                    }
                }

                // foreach (array_chunk($productcategories, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($productcategories as $item) {
                            DB::beginTransaction();
                            try {
                                \Log::info('Processing productcategories', ['item' => $item]);

                                // Make sure the required fields are available
                                if (!isset($item['id'])) {
                                    \Log::error('productattrs missing id', ['item' => $item]);
                                    $skippedCount++;
                                    continue;
                                }
                                error_log("Processing  " . $item['id']);

                                // $product = Product::withTrashed()->where('bybest_id', $item['product_id'])->first();
                                // $category = Category::where('bybest_id', $item['category_id'])->first();

                                ProductCategory::updateOrCreate(
                                    ['bybest_id' => $item['id']],
                                    [
                                        // 'product_id' => $product->id,
                                        // 'category_id' => $category->id,
                                        'product_id' => array_search($item['product_id'], $productIds) !== false ? array_search($item['product_id'], $productIds) : null,
                                        'category_id' => array_search($item['category_id'], $categoryIds) !== false ? array_search($item['category_id'], $categoryIds) : null,
                                        'bybest_id' => $item['id']
                                    ]
                                );

                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                $skippedCount++;
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                \Log::info("Processed {$processedCount} productcategories so far.");

                // $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in productcategories sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);

                error_log('Error in productcategories sync ' . $th->getMessage());
                return response()->json([
                    "message" => "Error in productcategories sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($productcategories) == $perPage);

        return response()->json([
            'message' => 'productcategories sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }

    public function productCollectionSync(Request $request): \Illuminate\Http\JsonResponse
    {
        // $venue = $this->venueService->adminAuthCheck();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        // $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;
        ini_set('max_execution_time', 3000000);
        // do {
            try {

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'productcollection-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }
                error_log("page $page");
                $productcollections = $bybestData['data'];

                $productIds = Product::withTrashed()->pluck('bybest_id','id')->toArray();
                if (count($productIds) > 0) {
                    $productIds = array_filter($productIds);
                    if (count($productIds) == 0) {
                        return response()->json(['message' => 'No products are exists'], 500);
                    }
                }

                $collectionIds = DB::table('collections')->pluck('bybest_id','id')->toArray();
                if (count($collectionIds) > 0) {
                    $collectionIds = array_filter($collectionIds);
                    if (count($collectionIds) == 0) {
                        return response()->json(['message' => 'No collections are exists'], 500);
                    }
                }

                // foreach (array_chunk($productcollections, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($productcollections as $item) {
                            DB::beginTransaction();
                            try {
                                \Log::info('Processing productcollections', ['item' => $item]);

                                // Make sure the required fields are available
                                if (!isset($item['id'])) {
                                    \Log::error('productattrs missing id', ['item' => $item]);
                                    $skippedCount++;
                                    continue;
                                }
                                error_log("Processing  " . $item['id'] . " " . $item['product_id'] . " " . $item['collection_id']);
                                // $product = Product::withTrashed()->where('bybest_id', $item['product_id'])->first();
                                // $collection = DB::table('collections')->where('bybest_id', $item['collection_id'])->first();

                                ProductCollection::updateOrCreate(
                                    ['bybest_id' => $item['id']],
                                    [
                                        // 'product_id' => $product->id,
                                        // 'collection_id' => $collection->id,
                                        'product_id' => array_search($item['product_id'], $productIds) !== false ? array_search($item['product_id'], $productIds) : null,
                                        'collection_id' => array_search($item['collection_id'], $collectionIds) !== false ? array_search($item['collection_id'], $collectionIds) : null,
                                        'bybest_id' => $item['id'],
                                        'created_at' => $item['created_at'],
                                        'updated_at' => $item['updated_at'],
                                    ]
                                );

                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                $skippedCount++;
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                \Log::info("Processed {$processedCount} productcollections so far.");

                // $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in productcollections sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);
                error_log('Error in productcollections sync ' . $th->getMessage());
                return response()->json([
                    "message" => "Error in productcollections sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($productcollections) == $perPage);

        return response()->json([
            'message' => 'productcollections sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }

    public function productGallerySync(Request $request): \Illuminate\Http\JsonResponse
    {
        // $venue = $this->venueService->adminAuthCheck();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        // $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;
        ini_set('max_execution_time', 3000000);
        // do {
            try {

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'productgallery-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }
                error_log("page $page");
                $productGalleries = $bybestData['data'];

                $productIds = Product::withTrashed()->pluck('bybest_id','id')->toArray();
                if (count($productIds) > 0) {
                    $productIds = array_filter($productIds);
                    if (count($productIds) == 0) {
                        return response()->json(['message' => 'No products are exists'], 500);
                    }
                }

                // foreach (array_chunk($productGalleries, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($productGalleries as $item) {
                            DB::beginTransaction();
                            try {
                                \Log::info('Processing productGalleries', ['item' => $item]);

                                // Make sure the required fields are available
                                if (!isset($item['id'])) {
                                    \Log::error('productGalleries missing id', ['item' => $item]);
                                    $skippedCount++;
                                    continue;
                                }
                                error_log("Processing  " . $item['id']);
                                // $product = Product::withTrashed()->where('bybest_id', $item['product_id'])->first();

                                ProductGallery::updateOrCreate(
                                    ['bybest_id' => $item['id']],
                                    [
                                        // 'product_id' => $product->id,
                                        'product_id' => array_search($item['product_id'], $productIds) !== false ? array_search($item['product_id'], $productIds) : null,
                                        'bybest_id' => $item['id'],
                                        'photo_name' => 'https://admin.bybest.shop/storage/products/' . $item['photo_name'],
                                        'photo_description' => $item['photo_description'],
                                        'created_at' => $item['created_at'],
                                        'updated_at' => $item['updated_at'],
                                    ]
                                );

                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                $skippedCount++;
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                \Log::info("Processed {$processedCount} productGalleries so far.");

                // $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in productGalleries sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);

                error_log('Error in productGalleries sync ' . $th->getMessage());
                return response()->json([
                    "message" => "Error in productGalleries sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($productGalleries) == $perPage);

        return response()->json([
            'message' => 'productGalleries sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }

    public function productStockSync(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 100);
        $batchSize = $request->input('batch_size', 50);
        $skippedCount = 0;
        $processedCount = 0;
        ini_set('max_execution_time', 3000000);
        // do {
            try {

                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get($this->bybestApiUrl . 'product-stock-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestData = $response->json();

                if (empty($bybestData) || !isset($bybestData['data'])) {
                    // break; // No more data to process
                    return response()->json(['message' => 'No more data to process'], 500);
                }
                error_log("page $page");
                $productStocks = $bybestData['data'];

                // foreach (array_chunk($productStocks, $batchSize) as $batch) {
                    // DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($productStocks as $item) {
                            DB::beginTransaction();
                            try {
                                \Log::info('Processing productStocks', ['item' => $item]);

                                // Make sure the required fields are available
                                if (!isset($item['id'])) {
                                    \Log::error('productStocks missing id', ['item' => $item]);
                                    $skippedCount++;
                                    continue;
                                }
                                error_log("Processing  " . $item['id']);

                                ProductStock::updateOrCreate(
                                    ['bybest_id' => $item['id']],
                                    [
                                        'bybest_id' => $item['id'],
                                        'article_no' => $item['article_no'],
                                        'alpha_warehouse' => $item['alpha_warehouse'],
                                        'stock_quantity' => $item['stock_quantity'],
                                        'synchronize_at' => $item['synchronize_at'],
                                        'created_at' => $item['created_at'],
                                        'alpha_date' => $item['alpha_date'],
                                        'updated_at' => $item['updated_at'],
                                        'deleted_at' => $item['deleted_at'],
                                        'venue_id' => $venue->id,
                                    ]
                                );

                                $processedCount++;
                                DB::commit();
                            } catch (\Exception $e) {
                                $skippedCount++;
                                DB::rollBack();
                            }
                        }
                    // });
                // }

                \Log::info("Processed {$processedCount} productStocks so far.");

                // $page++;
            } catch (\Throwable $th) {
                \Log::error('Error in productStocks sync', [
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString()
                ]);

                error_log('Error in productStocks sync ' . $th->getMessage());
                return response()->json([
                    "message" => "Error in productStocks sync",
                    "error" => $th->getMessage()
                ], 503);
            }
        // } while (count($productStocks) == $perPage);

        return response()->json([
            'message' => 'productStocks sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'total_pages' => isset($bybestData['total_pages']) ? $bybestData['total_pages'] : null,
            'current_page' => isset($bybestData['current_page']) ? $bybestData['current_page'] : null
        ], 200);
    }
}
