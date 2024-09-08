<?php

namespace App\Http\Controllers\v3;

use App\Http\Controllers\Controller;
use App\Models\ActivityRetail;
use App\Models\Brand;
use App\Models\Collection;
use App\Models\InventorySync;
use App\Models\Photo;
use App\Models\Product;
use App\Models\Category;
use App\Models\InventoryRetail;
use App\Models\VbStoreProductVariant;
use App\Models\VbStoreAttribute;
use App\Models\VbStoreAttributeOption;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Promise;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Jobs\UploadCollectionPhotoJob;


class InventorySyncController extends Controller
{
    private $bybestApiUrl = 'https://bybest.shop/api/V1/all-time-sync';
    private $bybestApiKey = 'crm.pixelbreeze.xyz-dbz';

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

    private function syncVariants($variants, $product_id, $venue_id)
    {
        foreach ($variants as $variant) {
            $variant_data = [
                'product_id' => $product_id,
                'venue_id' => $venue_id,
                'variation_sku' => $variant['variation_sku'] ?? null,
                'article_no' => $variant['article_no'] ?? null,
                'currency_alpha' => $variant['currency_alpha'] ?? null,
                'currency' => $variant['currency'] ?? null,
                'sku_alpha' => $variant['sku_alpha'] ?? null,
                'unit_code_alpha' => $variant['unit_code_alpha'] ?? null,
                'unit_code' => $variant['unit_code'] ?? null,
                'tax_code_alpha' => $variant['tax_code_alpha'] ?? null,
                'warehouse_alpha' => $variant['warehouse_alpha'] ?? null,
                'last_synchronization' => now(),
                'synced_at' => now(),
                'synced_method' => 'api_cronjob',
                'product_stock_status' => $variant['product_stock_status'] ?? null,
                'name' => $variant['varation_name'] ?? null,
                'variation_image' => $variant['variation_image'] ?? null,
                'sale_price' => $variant['sale_price'] ?? 0,
                'date_sale_start' => $variant['date_sale_start'] ?? null,
                'date_sale_end' => $variant['date_sale_end'] ?? null,
                'price' => $variant['regular_price'] ?? 0,
                'stock_quantity' => $variant['stock_quantity'] ?? 0,  // Default to 0 if NULL
                'manage_stock' => $variant['manage_stock'] ?? false,
                'sell_eventually' => $variant['sell_eventually'] ?? false,
                'allow_back_orders' => $variant['allow_back_orders'] ?? false,
                'weight' => $variant['weight'] ?? 0,
                'length' => $variant['length'] ?? 0,
                'width' => $variant['width'] ?? 0,
                'height' => $variant['height'] ?? 0,
                'product_long_description' => json_decode($variant['product_long_description'], true)['en'] ?? '',
            ];

            $vb_variant = VbStoreProductVariant::updateOrCreate(
                ['id' => $variant['id']],
                $variant_data
            );

            $this->syncVariantAttributes($variant['attributes'], $vb_variant->id, $venue_id);
        }
    }

    private function syncVariantAttributes($attributes, $variant_id, $venue_id)
    {
        foreach ($attributes as $attribute) {
            $type_id = $this->determineAttributeType($attribute['attribute_name']);

            $vb_attribute = VbStoreAttribute::firstOrCreate(
                ['attr_name' => json_encode(['en' => $attribute['attribute_name'], 'sq' => $attribute['attribute_name']])],
                [
                    'attr_url' => \Str::slug($attribute['attribute_name']),
                    'type_id' => $type_id,
                    'attr_description' => json_encode(['en' => '', 'sq' => '']),
                ]
            );

            $vb_attribute_option = VbStoreAttributeOption::firstOrCreate(
                ['attribute_id' => $vb_attribute->id, 'option_name' => json_encode(['en' => $attribute['option_name'], 'sq' => $attribute['option_name']])],
                ['option_url' => \Str::slug($attribute['option_name'])]
            );

            DB::table('vb_store_product_variant_attributes')->updateOrInsert(
                [
                    'variant_id' => $variant_id,
                    'attribute_id' => $vb_attribute_option->id,
                    'venue_id' => $venue_id
                ],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function determineAttributeType($attributeName)
    {
        $lowercaseAttributeName = strtolower($attributeName);

        if (in_array($lowercaseAttributeName, ['color', 'ngjyra', 'colour'])) {
            return 2; // Ngjyre
        } elseif (in_array($lowercaseAttributeName, ['size', 'weight', 'height', 'width', 'depth'])) {
            return 3; // Numerik
        } elseif ($lowercaseAttributeName === 'foto') {
            return 4; // Foto
        } else {
            return 5; // Opsione (default)
        }
    }

    private function syncAttributeTypes()
    {

        $bybestAttributeTypes = [
            ['id' => 1, 'type' => 'Tekst', 'description' => 'Tekst'],
            ['id' => 2, 'type' => 'Ngjyre', 'description' => 'Ngjyre'],
            ['id' => 3, 'type' => 'Numerik', 'description' => 'Numerik'],
            ['id' => 4, 'type' => 'Foto', 'description' => 'Foto'],
            ['id' => 5, 'type' => 'Opsione', 'description' => 'Opsione'],
        ];

        foreach ($bybestAttributeTypes as $type) {
            DB::table('vb_store_attributes_types')->updateOrInsert(
                ['id' => $type['id']],
                [
                    'type' => $type['type'],
                    'description' => $type['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function collectionSync(Request $request)
    {
        $venue = $this->venueService->adminAuthCheck();
        $page = 1;
        $perPage = 100;
        $batchSize = 50;
        $skippedCount = 0;
        $processedCount = 0;

        do {
            try {
                $start = microtime(true);
                $response = Http::withHeaders([
                    'X-App-Key' => $this->bybestApiKey
                ])->get('https://bybest.shop/api/V1/collection-sync', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                if (!$response->successful()) {
                    return response()->json(['message' => 'Failed to fetch data from ByBest API'], 500);
                }

                $bybestCollections = $response->json();

                if (empty($bybestCollections) || !isset($bybestCollections['data'])) {
                    break; // No more collections to process
                }

                $collections = $bybestCollections['data']; // Assuming 'data' contains the actual collections

                foreach (array_chunk($collections, $batchSize) as $batch) {
                    DB::transaction(function () use ($batch, $venue, &$skippedCount, &$processedCount) {
                        foreach ($batch as $bybestCollection) {
                            \Log::info('Processing collection', ['collection' => $bybestCollection]);

                            // Make sure the required fields are available
                            if (!isset($bybestCollection['id'])) {
                                \Log::error('Collection missing id', ['collection' => $bybestCollection]);
                                $skippedCount++;
                                continue;
                            }

                            $collection = Collection::withTrashed()->updateOrCreate(
                                ['bybest_id' => $bybestCollection['id']],
                                [
                                    'name' => json_encode($bybestCollection['name']),
                                    'slug' => $bybestCollection['collection_url'],
                                    'description' => json_encode($bybestCollection['description']),
                                    'created_at' => $bybestCollection['created_at'],
                                    'updated_at' => $bybestCollection['updated_at'],
                                    'venue_id' => $venue->id,
                                    'deleted_at' => $bybestCollection['deleted_at'] ? Carbon::parse($bybestCollection['deleted_at']) : null
                                ]
                            );

                            // Dispatch job for photo upload
                            if ($bybestCollection['photo']) {

                                \Log::info('Dispatching UploadCollectionPhotoJob', [
                                    'collection_id' => $collection->id,
                                    'photo_url' => $bybestCollection['photo'],
                                ]);

                                UploadCollectionPhotoJob::dispatch($collection, $bybestCollection['photo'], $venue);

                            }
                            $processedCount++;
                        }
                    });
                }

                \Log::info("Processed {$processedCount} collections so far.");

                $page++;
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
        } while (count($collections) == $perPage); // Use $collections here

        return response()->json([
            'message' => 'Collections sync completed successfully',
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount
        ], 200);
    }



}
