<?php

namespace App\Http\Controllers\v1;

use App\Enums\FeatureNaming;
use App\Http\Controllers\Controller;
use App\Http\PhotoFactory;
use App\Models\ActivityRetail;
use App\Models\Category;
use App\Models\DigitalMenu;
use App\Models\EcommercePlatform;
use App\Models\Feature;
use App\Models\FeatureUsageCredit;
use App\Models\FeatureUsageCreditHistory;
use App\Models\Gallery;
use App\Models\ImportedSale;
use App\Models\InventoryActivity;
use App\Models\InventoryAlert;
use App\Models\InventoryAlertHistory;
use App\Models\InventoryRetail;
use App\Models\InventoryWarehouseProduct;
use App\Models\Photo;
use App\Models\PhysicalStore;
use App\Models\PlanFeature;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\Restaurant;
use App\Models\ScanActivity;
use App\Models\StoreSetting;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\TakeHomeProduct;
use App\Models\Variation;
use App\Models\Brand;
use App\Rules\NumericRangeRule;
use App\Services\ApiUsageLogger;
use App\Services\VenueService;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\Writer;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use stdClass;


/**
 * @OA\Info(
 *   title="Menu Management API",
 *   version="1.0",
 *   description="This API allows use to retrieve all Menu Management related data",
 * )
 */

/**
 * @OA\Tag(
 *   name="Menu Management",
 *   description="Operations related to Menu Management"
 * )
 */

class ProductsController extends Controller
{
    const BASE_DIRECTORY = 'images/products';

    protected VenueService $venueService;

    protected ApiUsageLogger $apiUsageLogger;

    public function __construct(ApiUsageLogger $apiUsageLogger, VenueService $venueService)
    {
        $this->apiUsageLogger = $apiUsageLogger;
        $this->venueService = $venueService;
    }

    /**
     * @OA\Get(
     *     path="/menu/products",
     *     summary="Get all products",
     *     tags={"Menu Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *     response=200,
     *     description="Products retrieved successfully",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Products retrieved successfully"),
     *     )
     *    ),
     *     @OA\Response(
     *     response=400,
     *     description="Restaurant not found for the user making the API call",
     *     @OA\JsonContent(
     *     @OA\Property(property="error", type="string", example="Restaurant not found for the user making the API call"),
     *     )
     *   ),
     *     @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Server error"),
     *     )
     *  ),
     *     @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Unauthenticated"),
     *     )
     * )
     * )
     */
    public function get(Request $request): \Illuminate\Http\JsonResponse
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
            'category' => 'integer',
            'search' => 'string',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $category_id = $request->input('category');
            $search = $request->input('search');

            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 15);
            $products = Product::with('takeHome')->where('restaurant_id', $venue->id);
            if ($category_id) {
                $products = $products->whereRaw("
                    (
                        id IN
                        (
                            SELECT product_id
                            FROM product_category
                            WHERE category_id = ?
                        )
                    )
                    ", [$category_id]);
            }

            error_log("category_id $category_id");

            if ($search) {
                $products = $products->whereRaw('LOWER(title) LIKE ?', ["%" . strtolower($search) . "%"]);
            }

            $products = $products->orderBy('created_at', 'DESC')->paginate($perPage);


            // Get currency setting for the venue
            $storeSetting = StoreSetting::where('venue_id', $venue->id)->first();
            $currency = $storeSetting ? $storeSetting->currency : ($venue->currency ? $venue->currency : null);


            $updatedProducts = $products->map(function ($product) use ($currency) {
                if ($product->image_path !== null) {
                    // Generate the new path and update the image_path attribute
                    $newPath = Storage::disk('s3')->temporaryUrl($product->image_path, '+5 minutes');
                    $product->image_path = $newPath;
                }
                $product->currency = $currency ?? 'USD';
                $product->try_at_home = $product?->takeHome ?? null;
                return $product;
            });

            $products->setCollection($updatedProducts);

            // determine if venues has used all credits for the feature for the month

            $hasUsedAllCredits = false;
            try {

                $featureName = FeatureNaming::items_food;
                if ($venue->venueType->definition === 'accommodation') {
                    $featureName = FeatureNaming::items_accommodation;
                }

                if ($venue->venueType->definition === 'accommodation' || $venue->venueType->definition === 'sport_entertainment') {
                    $featureName = FeatureNaming::items_sport_entertainment;
                }

                if ($venue->venueType->definition === 'retail') {
                    $featureName = FeatureNaming::items_retail;
                }
                $featureId = Feature::where('name', $featureName)
                    ->where('active', 1)
                    ->where('feature_category', $venue->venueType->definition)->first()->id;
                $subFeatureId = null;

                $activeSubscription = Subscription::with(['subscriptionItems.pricingPlanPrice', 'pricingPlan'])
                    ->where('venue_id', $venue->id)
                    ->where(function ($query) {
                        $query->where('status', 'active')
                            ->orWhere('status', 'trialing');
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();
                $planName = $activeSubscription?->pricingPlan->name;
                $planId = $activeSubscription?->pricing_plan_id;
                if ($planName === 'Discover') {
                    // Check Count of the product used on FeatureUsageCreditHistory with feature_id
                    $featureUsageCreditHistoryCount = FeatureUsageCreditHistory::where('feature_id', $featureId)->get();
                    // get usage credit for this feature
                    $featureUsageCredit = PlanFeature::where('feature_id', $featureId)->where('plan_id', $planId)->first()->usage_credit;
                    // if count is same as usage credit
                    if ($featureUsageCreditHistoryCount->count() >= $featureUsageCredit) {
                        $hasUsedAllCredits = true;
                    }

                }

                $this->apiUsageLogger->log($featureId, $venue->id, 'List Products - GET', $subFeatureId);
            } catch (\Exception $e) {
                // do nothing
            }

            return response()->json([
                'message' => 'Products retrieved successfully',
                'products' => $products->items(),
                'hasUsedAllCredits' => $hasUsedAllCredits,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/menu/products/{id}",
     *     summary="Get product details from id",
     *     tags={"Menu Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Product id",
     *     required=true,
     *     @OA\Schema(
     *     type="integer",
     *     )
     *    ),
     *     @OA\Response(
     *     response=200,
     *     description="Products retrieved successfully",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Products retrieved successfully"),
     *     )
     *    ),
     *     @OA\Response(
     *     response=400,
     *     description="Restaurant not found for the user making the API call",
     *     @OA\JsonContent(
     *     @OA\Property(property="error", type="string", example="Restaurant not found for the user making the API call"),
     *     )
     *   ),
     *     @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Server error"),
     *     )
     *  ),
     *     @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Unauthenticated"),
     *     )
     * )
     * )
     */
    public function getOne($id)
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

        try {
            $product = Product::where('restaurant_id', $venue->id)->with(['variations.attribute', 'variations.value'])->find($id);
            if (!$product) {
                return response()->json(['message' => 'Not found product'], 404);
            }

            $options = DB::table('product_options')->where('product_id', $product->id)->where('type', 'option')->get();
            $additions = DB::table('product_options')->where('product_id', $product->id)->where('type', 'addition')->get();

            $product->image_path = $product->image_path ? Storage::disk('s3')->temporaryUrl($product->image_path, '+5 minutes') : null;

            // check if it has parent category
            $productParentCategoryRelationship = DB::table('product_category')->where('product_id', $product->id)->where('is_parent', true)->first();
            if ($productParentCategoryRelationship) {
                $productParentCategory =  new StdClass;
                $productParentCategory->id = $productParentCategoryRelationship->category_id;
                $productParentCategory->title = DB::table('categories')->where('id', $productParentCategoryRelationship->category_id)->first()->title;

                $product->parent = $productParentCategory;
            }

            // check if it has sub category
            $productCategoryRelationship = DB::table('product_category')->where('product_id', $product->id)->where('is_parent', false)->first();
            if ($productCategoryRelationship) {
                $productCategory =  new StdClass;
                $productCategory->id = $productCategoryRelationship->category_id;
                $productCategory->title = DB::table('categories')->where('id', $productCategoryRelationship->category_id)->first()->title;

                $product->category = $productCategory;
            }

            $galleryProduct = Gallery::where('product_id',  $product->id)->get();

            $managedGallery = $galleryProduct->map(function ($item) {
                return [
                    'photo_id' => $item->photo_id,
                    'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
                ];
            });

            $product->gallery = $managedGallery;
            $product->inventory_retail = $product->inventoryRetail ?: null;

            $variationsOutput = $product->variations->map(function ($variation) {
                return [
                    'id' => $variation->id,
                    'attribute' => [
                        'name' => $variation->attribute->name,
                        'id' => $variation->attribute->id,
                    ],
                    'value' => [
                        'name' => $variation->value->value,  // Assuming the name of the attribute value is stored in 'value' column.
                        'id' => $variation->value->id,
                    ],
                    'price' => $variation->price,
                ];
            })->toArray();

            // Step 2: Convert the Eloquent model to an array
            $productArray = $product->toArray();

            $attributes = DB::table('product_attribute_value')
                ->join('attribute_values', 'product_attribute_value.attribute_value_id', '=', 'attribute_values.id')
                ->join('product_attributes', 'attribute_values.attribute_id', '=', 'product_attributes.id')
                ->where('product_attribute_value.product_id', $product->id)
                ->select(
                    'product_attributes.name as attribute_name',
                    'attribute_values.value as attribute_value',
                    'product_attributes.id as attribute_id',
                    'product_attribute_value.visible_on_product_page as visible_on_product_page',
                    'product_attribute_value.used_for_variations as used_for_variations'
                )
                ->get();

            $groupedAttributes = [];

            foreach ($attributes as $attribute) {
                if (!isset($groupedAttributes[$attribute->attribute_id])) {
                    $groupedAttributes[$attribute->attribute_id] = [
                        'id' => $attribute->attribute_id,
                        'name' => $attribute->attribute_name,
                        'visible_on_product_page' => $attribute->visible_on_product_page,
                        'used_for_variations' => $attribute->used_for_variations,
                        'values' => [],
                    ];
                }

                $groupedAttributes[$attribute->attribute_id]['values'][] = $attribute->attribute_value;
            }

            $attributesFinal = [];
            foreach ($groupedAttributes as $attribute) {
                $attribute['values'] = implode(', ', $attribute['values']);
                $attributesFinal[] = $attribute;
            }


            // Step 3: Overwrite the 'attributes' key
            $productArray['attributes'] = $attributesFinal;
            $productArray['variations'] = $variationsOutput;
            $productArray['currency'] =  $venue->storeSettings()->first()?->currency ?: $venue->currency;
            return response()->json(['message' => 'Product retrieved successfully',
                'product' => $productArray, 'options' => $options, 'additions' => $additions], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getOneBySku($sku)
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

        try {
            $product = Product::where('article_no', $sku)->first();
            if (!$product) {
                return response()->json(['message' => 'Not found product'], 404);
            }

            $options = DB::table('product_options')->where('product_id', $product->id)->where('type', 'option')->get();
            $additions = DB::table('product_options')->where('product_id', $product->id)->where('type', 'addition')->get();

            $product->image_path = $product->image_path ? Storage::disk('s3')->temporaryUrl($product->image_path, '+5 minutes') : null;

            // check if it has parent category
            $productParentCategoryRelationship = DB::table('product_category')->where('product_id', $product->id)->where('is_parent', true)->first();
            if ($productParentCategoryRelationship) {
                $productParentCategory =  new StdClass;
                $productParentCategory->id = $productParentCategoryRelationship->category_id;
                $productParentCategory->title = DB::table('categories')->where('id', $productParentCategoryRelationship->category_id)->first()->title;

                $product->parent = $productParentCategory;
            }

            // check if it has sub category
            $productCategoryRelationship = DB::table('product_category')->where('product_id', $product->id)->where('is_parent', false)->first();
            if ($productCategoryRelationship) {
                $productCategory =  new StdClass;
                $productCategory->id = $productCategoryRelationship->category_id;
                $productCategory->title = DB::table('categories')->where('id', $productCategoryRelationship->category_id)->first()->title;

                $product->category = $productCategory;
            }

            $galleryProduct = Gallery::where('product_id',  $product->id)->get();

            $managedGallery = $galleryProduct->map(function ($item) {
                return [
                    'photo_id' => $item->photo_id,
                    'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
                ];
            });

            $product->gallery = $managedGallery;
            $product->inventory_retail = $product->inventoryRetail ?: null;
            $product->currency =  $venue->storeSettings()->first()?->currency ?: $venue->currency;
            return response()->json(['message' => 'Product retrieved successfully',
                'product' => $product, 'options' => $options, 'additions' => $additions], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


    /**@OA\Post(
     *     path="/menu/products",
     *     summary="Create product",
     *     tags={"Menu Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *      required=true,
     *     @OA\JsonContent(
     *     required={"title", "price"},
     *     @OA\Property(property="title", type="string", example="Product title"),
     *     @OA\Property(property="description", type="string", example="Product description"),
     *     @OA\Property(property="available", type="boolean", example="true"),
     *     @OA\Property(property="price", type="number", example="10.00"),
     *     @OA\Property(property="image", type="string", example="https://via.placeholder.com/150"),
     *     @OA\Property(property="category_id", type="integer", example="1"),
     *     @OA\Property(property="options", type="array",
     *     @OA\Items(
     *     @OA\Property(property="title", type="string", example="Option title"),
     *     @OA\Property(property="price", type="number", example="10.00"),
     *     @OA\Property(property="type", type="string", example="option"),
     *     @OA\Property(property="required", type="boolean", example="true"),
     *     @OA\Property(property="options", type="array",
     *     @OA\Items(
     *     @OA\Property(property="title", type="string", example="Option title"),
     *     @OA\Property(property="price", type="number", example="10.00"),
     *     @OA\Property(property="type", type="string", example="option"),
     *     @OA\Property(property="required", type="boolean", example="true"),
     *     ),
     *     ),
     *     ),
     *     ),
     *     @OA\Property(property="additions", type="array",
     *     @OA\Items(
     *      @OA\Property(property="title", type="string", example="Addition title"),
     *     @OA\Property(property="price", type="number", example="10.00"),
     *     @OA\Property(property="type", type="string", example="addition"),
     *     @OA\Property(property="required", type="boolean", example="true"),
     *     ),
     *     ),
     *     ),
     *     ),
     *     ),
     *    ),
     *    @OA\Response(
     *     response=201,
     *     description="Product created successfully",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Product created successfully"),
     *     )
     *   ),
     *   @OA\Response(
     *     response=400,
     *     description="Restaurant not found for the user making the API call",
     *     @OA\JsonContent(
     *     @OA\Property(property="error", type="string", example="Restaurant not found for the user making the API call"),
     *     )
     *  ),
     *    @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Server error"),
     *     )
     * ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Unauthenticated"),
     *     )
     * )
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

        $rules = [
            'title' => 'required|string|max:255',
            'price' => ['required', 'numeric', new NumericRangeRule()],
        ];

        if ($request->input('is_for_retail')) {
            $rules['short_description'] = 'required|string';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            $product_id = $request->input('id');
            $title = $request->input('title');
            $description = $request->input('description');
            $article_no = $request->input('article_no');
            $available = $request->input('available');
            $price = $request->input('price');
            $order_method = $request->input('order_method');
            $option_selected_type = $request->input('option_selected_type');
            $addition_selected_type = $request->input('addition_selected_type');
            $option_selected_required = $request->input('option_selected_required');
            $brand_id = $request->input('brand_id');
            $unit_measure = $request->input('unit_measure');
            $requestType = 'product';

            $path = null;
            // check if product image is uploaded
            if ($request->file('image')) {


                $productImage = $request->file('image');

                // Decode base64 image data
                $photoFile = $productImage;
                $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();

                // Upload photo to AWS S3
                $path = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $venue->venueType->short_name . '/' . $requestType . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)), $photoFile, $filename);

                // Save photo record in the database
                $photo = new Photo();
                $photo->venue_id = $venue->id;
                $photo->image_path = $path;
                $photo->type = $requestType;
                $photo->save();

            }

            if ($product_id) {
                $product = Product::where('id', $product_id)->first();
                if (!$product) {
                    return response()->json(['error' => 'Product not found'], 400);
                }
            } else {
                $product = new Product();
            }


            $product->image_path = $path ?? $product->image_path;
            $product->image_thumbnail_path = null;
            $product->title = $title;
            $product->description = $description;
            $product->article_no = $article_no ?? $product->artcle_no;
            $product->available = $available ?? false;
            $product->price = $price;
            $product->order_method = $order_method;
            $product->option_selected_type = $option_selected_type ?? 1;
            $product->addition_selected_type = $addition_selected_type ?? 1;
            $product->option_selected_required = $option_selected_required ?? 1;
            $product->restaurant_id = $venue->id;
            $product->short_description = $request->input('short_description') ?? $product->short_description;
            $product->is_for_retail = $request->input('is_for_retail') ?? $product->is_for_retail ?? false;
            $product->product_url = '';
            $product->brand_id = $brand_id;
            $product->unit_measure = $unit_measure;
            $product->scan_time = Carbon::now();
            $product->save();

            if ($request->input('quantity')) {
                // Reuse the logic from createOrUpdateRetailProductInventory here
                $inventoryRetail = InventoryRetail::firstOrNew([
                    'product_id' => $product->id,
                    'venue_id' => $venue->id
                ]);

                $inventoryRetail->stock_quantity = $request->input('quantity');
                $inventoryRetail->manage_stock = true;
                $inventoryRetail->used_in_whitelabel = true;
                $inventoryRetail->used_in_stores = [];
                $inventoryRetail->used_in_ecommerces = [];
                $inventoryRetail->save();
            }

            if ($request->input('category_id')) {

                $requestCategoryId = $request->input('category_id');

                // check if product category exist
                $doesProductCategoryExist = DB::table('product_category')->where('product_id', $product->id)->where('is_parent', false)->first();

                if ($doesProductCategoryExist) {
                    if ($requestCategoryId === 'null' || $requestCategoryId === null) {
                        DB::table('product_category')->where('product_id', $product->id)->where('is_parent', false)->delete();
                    }
                    else if ($doesProductCategoryExist->category_id !== intval($requestCategoryId)) {
                        DB::table('product_category')->where('product_id', $product->id)->where('is_parent', false)->update([
                            'category_id' => intval($requestCategoryId),
                        ]);
                    }
                }
                else {

                    if ($requestCategoryId !== 'null' && $requestCategoryId !== null && $requestCategoryId !== '' && $requestCategoryId !== 'undefined') {

                        DB::table('product_category')->insert([
                            'category_id' => intval($requestCategoryId),
                            'product_id' => $product->id,
                        ]);
                    }
                }
            }

            if ($request->input('parent_category_id')) {

                $requestParentCategoryId = $request->input('parent_category_id');
                // check if category exist
                $doesParentCategoryExist = Category::where('id', $requestParentCategoryId)->first();
                if (!$doesParentCategoryExist) {
                    return response()->json(['error' => 'Not found parent category'], 400);
                }


                // check if product parent category exist
                $doesProductParentCategoryExist = DB::table('product_category')->where('product_id', $product->id)->where('is_parent', true)->first();

                if ($doesProductParentCategoryExist) {
                    if ($doesProductParentCategoryExist->category_id !== intval($requestParentCategoryId)) {

                        DB::table('product_category')
                            ->where('product_id', $product->id)
                            ->where('is_parent', true)
                            ->update(['category_id' => intval($requestParentCategoryId)]);
                    }
                }
                else {
                    DB::table('product_category')->insert([
                        'category_id' => intval($requestParentCategoryId),
                        'product_id' => $product->id,
                        'is_parent' => true,
                    ]);
                }
            }

            DB::table('product_options')->where('product_id', $product->id)->delete();

            $product_options = $request->input('options');
            if ($product_options) {
                foreach ($product_options as $key => $option) {
                    DB::table('product_options')->insert([
                        'title' => $option['title'],
                        'type' => 'option',
                        'price' => 0,
                        'available' => 1,
                        'product_id' => $product->id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $product_additions = $request->input('additions');
            if ($product_additions) {
                foreach ($product_additions as $key => $option) {
                    DB::table('product_options')->insert([
                        'title' => $option['title'],
                        'type' => 'addition',
                        'price' => $option['price'],
                        'available' => 1,
                        'product_id' => $product->id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            try {

                $featureName = FeatureNaming::items_food;
                if ($venue->venueType->definition === 'accommodation') {
                    $featureName = FeatureNaming::items_accommodation;
                }

                if ($venue->venueType->definition === 'accommodation' || $venue->venueType->definition === 'sport_entertainment') {
                    $featureName = FeatureNaming::items_sport_entertainment;
                }

                if ($venue->venueType->definition === 'retail') {
                    $featureName = FeatureNaming::items_retail;
                }
                $featureId = Feature::where('name', $featureName)
                    ->where('active', 1)
                    ->where('feature_category', $venue->venueType->definition)->first()->id;
                $subFeatureId = null;

                $activeSubscription = Subscription::with(['subscriptionItems.pricingPlanPrice', 'pricingPlan'])
                    ->where('venue_id', $venue->id)
                    ->where(function ($query) {
                        $query->where('status', 'active')
                            ->orWhere('status', 'trialing');
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();
                $planName = $activeSubscription?->pricingPlan->name;
                $planId = $activeSubscription?->pricing_plan_id;
                if ($planName === 'Discover') {
                    // Check Count of the product used on FeatureUsageCreditHistory with feature_id
                    $featureUsageCreditHistoryCount = FeatureUsageCreditHistory::where('feature_id', $featureId)->get();
                    // get usage credit for this feature
                    $featureUsageCredit = PlanFeature::where('feature_id', $featureId)->where('plan_id', $planId)->first()->usage_credit;
                    // if count is less than usage credit then deduct from usage credit
                    if ($featureUsageCreditHistoryCount->count() < $featureUsageCredit) {
                        // find feature usage credit for this venue
                        $featureUsageCredit = FeatureUsageCredit::where('venue_id', $venue->id)->first();
                        $featureUsageCredit->update([
                            'balance' => $featureUsageCredit->balance - 1
                        ]);
                        // create feature usage credit history
                        $featureUsageCreditHistory = new FeatureUsageCreditHistory();
                        $featureUsageCreditHistory->feature_id = $featureId;
                        $featureUsageCreditHistory->used_at_feature = $featureName;
                        $featureUsageCreditHistory->feature_usage_credit_id = $featureUsageCredit->id;
                        $featureUsageCreditHistory->transaction_type = 'decrease';
                        $featureUsageCreditHistory->amount = 1;
                        $featureUsageCreditHistory->save();
                    }
                }


                $this->apiUsageLogger->log($featureId, $venue->id, 'Add Manual Product - POST', $subFeatureId);
            } catch (\Exception $e) {
                // do nothing
            }

            return response()->json(['message' => 'Product is created successfully'], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function storeAfterScanning(Request $request): \Illuminate\Http\JsonResponse
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

        $rules = [
            'title' => 'required|string|max:255',
            'price' => ['required', 'numeric', new NumericRangeRule()],
        ];

        if ($request->input('is_for_retail')) {
            $rules['short_description'] = 'required|string';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            $product_id = $request->input('id');
            $after_scanning = $request->input('after_scanning', false);
            $title = $request->input('title');
            $description = $request->input('description');
            $article_no = $request->input('article_no');
            $available = $request->input('available');
            $price = $request->input('price');
            $order_method = $request->input('order_method');
            $option_selected_type = $request->input('option_selected_type');
            $addition_selected_type = $request->input('addition_selected_type');
            $option_selected_required = $request->input('option_selected_required');
            $brand_id = $request->input('brand_id');
            $unit_measure = $request->input('unit_measure');
            $requestType = 'product';

            $path = null;
            // check if product image is uploaded
            if ($request->file('image')) {
                $productImage = $request->file('image');
                $photoFile = $productImage;
                $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();
                $path = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $venue->venueType->short_name . '/' . $requestType . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)), $photoFile, $filename);

                $photo = new Photo();
                $photo->venue_id = $venue->id;
                $photo->image_path = $path;
                $photo->type = $requestType;
                $photo->save();
            }

            if ($product_id) {
                $product = Product::where('id', $product_id)->first();
                if (!$product) {
                    return response()->json(['error' => 'Product not found'], 400);
                }
            } else {
                $product = new Product();
            }

            $product->image_path = $path ?? $product->image_path;
            $product->image_thumbnail_path = null;
            $product->title = $title;
            $product->description = $description;
            $product->article_no = $article_no ?? $product->article_no;
            $product->available = $available ?? false;
            $product->price = $price;
            $product->order_method = $order_method;
            $product->option_selected_type = $option_selected_type ?? 1;
            $product->addition_selected_type = $addition_selected_type ?? 1;
            $product->option_selected_required = $option_selected_required ?? 1;
            $product->restaurant_id = $venue->id;
            $product->short_description = $request->input('short_description') ?? $product->short_description;
            $product->is_for_retail = $request->input('is_for_retail') ?? $product->is_for_retail ?? false;
            $product->product_url = '';
            $product->brand_id = $brand_id;
            $product->unit_measure = $unit_measure;
            $product->scan_time = Carbon::now();
            $product->save();

            if ($request->input('quantity')) {
                $inventoryRetail = InventoryRetail::firstOrNew([
                    'product_id' => $product->id,
                    'venue_id' => $venue->id
                ]);

                $oldQuantity = $inventoryRetail->stock_quantity;
                $newQuantity = $request->input('quantity');
                $inventoryRetail->stock_quantity = $newQuantity;
                $inventoryRetail->manage_stock = true;
                $inventoryRetail->used_in_whitelabel = true;
                $inventoryRetail->used_in_stores = [];
                $inventoryRetail->used_in_ecommerces = [];
                $inventoryRetail->save();

                // Log activity
                ActivityRetail::create([
                    'inventory_retail_id' => $inventoryRetail->id,
                    'venue_id' => $venue->id,
                    'activity_type' => $product->wasRecentlyCreated ? 'create' : 'update',
                    'description' => $product->wasRecentlyCreated
                        ? "Product created with initial stock quantity {$newQuantity}"
                        : "Product stock updated from {$oldQuantity} to {$newQuantity}",
                    'data' => json_encode([
                        'previous_quantity' => $oldQuantity,
                        'new_quantity' => $newQuantity
                    ]),
                ]);

                if ($after_scanning) {
                    if ($request->input('moved_from_warehouse')) {
                        $this->handleWarehouseTransfer(
                            $product,
                            $request->input('moved_from_warehouse'),
                            $inventoryRetail->warehouse_id,
                            $newQuantity,
                            $venue
                        );
                    } else {
                        $scanType = $product->wasRecentlyCreated ?
                            ScanActivity::SCAN_TYPE_ADD_NEW_PRODUCT :
                            ScanActivity::SCAN_TYPE_UPDATE_PRODUCT_INVENTORY;

                        ScanActivity::create([
                            'product_id' => $product->id,
                            'scan_type' => $scanType,
                            'scan_time' => Carbon::now(),
                            'moved_to_warehouse' => $inventoryRetail->warehouse_id,
                            'venue_id' => $venue->id
                        ]);
                    }
                }
            }

            if ($request->input('category_id')) {
                $requestCategoryId = $request->input('category_id');
                $doesProductCategoryExist = DB::table('product_category')->where('product_id', $product->id)->where('is_parent', false)->first();

                if ($doesProductCategoryExist) {
                    if ($requestCategoryId === 'null' || $requestCategoryId === null) {
                        DB::table('product_category')->where('product_id', $product->id)->where('is_parent', false)->delete();
                    }
                    else if ($doesProductCategoryExist->category_id !== intval($requestCategoryId)) {
                        DB::table('product_category')->where('product_id', $product->id)->where('is_parent', false)->update([
                            'category_id' => intval($requestCategoryId),
                        ]);
                    }
                }
                else {
                    if ($requestCategoryId !== 'null' && $requestCategoryId !== null && $requestCategoryId !== '' && $requestCategoryId !== 'undefined') {
                        DB::table('product_category')->insert([
                            'category_id' => intval($requestCategoryId),
                            'product_id' => $product->id,
                        ]);
                    }
                }
            }

            if ($request->input('parent_category_id')) {
                $requestParentCategoryId = $request->input('parent_category_id');
                $doesParentCategoryExist = Category::where('id', $requestParentCategoryId)->first();
                if (!$doesParentCategoryExist) {
                    return response()->json(['error' => 'Not found parent category'], 400);
                }

                $doesProductParentCategoryExist = DB::table('product_category')->where('product_id', $product->id)->where('is_parent', true)->first();

                if ($doesProductParentCategoryExist) {
                    if ($doesProductParentCategoryExist->category_id !== intval($requestParentCategoryId)) {
                        DB::table('product_category')
                            ->where('product_id', $product->id)
                            ->where('is_parent', true)
                            ->update(['category_id' => intval($requestParentCategoryId)]);
                    }
                }
                else {
                    DB::table('product_category')->insert([
                        'category_id' => intval($requestParentCategoryId),
                        'product_id' => $product->id,
                        'is_parent' => true,
                    ]);
                }
            }

            DB::table('product_options')->where('product_id', $product->id)->delete();

            $product_options = $request->input('options');
            if ($product_options) {
                foreach ($product_options as $key => $option) {
                    DB::table('product_options')->insert([
                        'title' => $option['title'],
                        'type' => 'option',
                        'price' => 0,
                        'available' => 1,
                        'product_id' => $product->id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $product_additions = $request->input('additions');
            if ($product_additions) {
                foreach ($product_additions as $key => $option) {
                    DB::table('product_options')->insert([
                        'title' => $option['title'],
                        'type' => 'addition',
                        'price' => $option['price'],
                        'available' => 1,
                        'product_id' => $product->id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            try {
                $featureName = FeatureNaming::items_food;
                if ($venue->venueType->definition === 'accommodation') {
                    $featureName = FeatureNaming::items_accommodation;
                }

                if ($venue->venueType->definition === 'accommodation' || $venue->venueType->definition === 'sport_entertainment') {
                    $featureName = FeatureNaming::items_sport_entertainment;
                }

                if ($venue->venueType->definition === 'retail') {
                    $featureName = FeatureNaming::items_retail;
                }
                $featureId = Feature::where('name', $featureName)
                    ->where('active', 1)
                    ->where('feature_category', $venue->venueType->definition)->first()->id;
                $subFeatureId = null;

                $activeSubscription = Subscription::with(['subscriptionItems.pricingPlanPrice', 'pricingPlan'])
                    ->where('venue_id', $venue->id)
                    ->where(function ($query) {
                        $query->where('status', 'active')
                            ->orWhere('status', 'trialing');
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();
                $planName = $activeSubscription?->pricingPlan->name;
                $planId = $activeSubscription?->pricing_plan_id;
                if ($planName === 'Discover') {
                    $featureUsageCreditHistoryCount = FeatureUsageCreditHistory::where('feature_id', $featureId)->get();
                    $featureUsageCredit = PlanFeature::where('feature_id', $featureId)->where('plan_id', $planId)->first()->usage_credit;
                    if ($featureUsageCreditHistoryCount->count() < $featureUsageCredit) {
                        $featureUsageCredit = FeatureUsageCredit::where('venue_id', $venue->id)->first();
                        $featureUsageCredit->update([
                            'balance' => $featureUsageCredit->balance - 1
                        ]);
                        $featureUsageCreditHistory = new FeatureUsageCreditHistory();
                        $featureUsageCreditHistory->feature_id = $featureId;
                        $featureUsageCreditHistory->used_at_feature = $featureName;
                        $featureUsageCreditHistory->feature_usage_credit_id = $featureUsageCredit->id;
                        $featureUsageCreditHistory->transaction_type = 'decrease';
                        $featureUsageCreditHistory->amount = 1;
                        $featureUsageCreditHistory->save();
                    }
                }

                $this->apiUsageLogger->log($featureId, $venue->id, 'Add Manual Product - POST', $subFeatureId);
            } catch (\Exception $e) {
                // do nothing
            }

            return response()->json(['message' => 'Product is created successfully'], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function handleWarehouseTransfer(Product $product, $fromWarehouseId, $toWarehouseId, $quantity, $venue)
    {
        $sourceInventory = InventoryRetail::where('product_id', $product->id)
            ->where('warehouse_id', $fromWarehouseId)
            ->firstOrFail();

        if ($sourceInventory->stock_quantity < $quantity) {
            throw new \Exception('Insufficient stock in source warehouse');
        }

        $oldSourceQuantity = $sourceInventory->stock_quantity;
        $sourceInventory->stock_quantity -= $quantity;
        $sourceInventory->save();

        $destInventory = InventoryRetail::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $toWarehouseId],
            ['venue_id' => $venue->id, 'stock_quantity' => 0]
        );
        $oldDestQuantity = $destInventory->stock_quantity;
        $destInventory->stock_quantity += $quantity;
        $destInventory->save();

        // Log activity for source warehouse
        ActivityRetail::create([
            'inventory_retail_id' => $sourceInventory->id,
            'venue_id' => $venue->id,
            'activity_type' => 'transfer_out',
            'description' => "Product transferred out. Stock reduced from {$oldSourceQuantity} to {$sourceInventory->stock_quantity}",
            'data' => json_encode([
                'previous_quantity' => $oldSourceQuantity,
                'new_quantity' => $sourceInventory->stock_quantity,
                'transferred_quantity' => $quantity,
                'destination_warehouse_id' => $toWarehouseId
            ]),
        ]);

        // Log activity for destination warehouse
        ActivityRetail::create([
            'inventory_retail_id' => $destInventory->id,
            'venue_id' => $venue->id,
            'activity_type' => 'transfer_in',
            'description' => "Product transferred in. Stock increased from {$oldDestQuantity} to {$destInventory->stock_quantity}",
            'data' => json_encode([
                'previous_quantity' => $oldDestQuantity,
                'new_quantity' => $destInventory->stock_quantity,
                'transferred_quantity' => $quantity,
                'source_warehouse_id' => $fromWarehouseId
            ]),
        ]);

        ScanActivity::create([
            'product_id' => $product->id,
            'scan_type' => ScanActivity::SCAN_TYPE_WAREHOUSE_TRANSFER,
            'scan_time' => Carbon::now(),
            'moved_to_warehouse' => $toWarehouseId,
            'moved_from_warehouse' => $fromWarehouseId,
            'venue_id' => $venue->id
        ]);
    }


    public function bulkImportProducts(Request $request): \Illuminate\Http\JsonResponse
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


            // Define custom error messages for validation
            $messages = [
                'numeric' => 'The :attribute must be a number.',
                'string' => 'The :attribute must be a string.',
            ];


            // Validate the CSV file against the rules
            $validator = Validator::make($request->all(), [
                'file' => 'required|file',
                'physical_store_ids' => 'nullable|array',
                'physical_store_ids.*' => 'exists:physical_stores,id',
                'ecommerce_platforms_ids' => 'nullable|array',
                'ecommerce_platforms_ids.*' => 'exists:ecommerce_platforms,id',
                'used_in_whitelabel' => 'nullable|boolean',
                'warehouse_id' => 'required|exists:inventory_warehouses,id'
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors(),
                ], 400);
            }


            $requestCategoryId = null;
            $requestParentCategoryId = null;


            if ($request->input('category_id')) {

                $requestCategoryId = $request->input('category_id');

            }

            if ($request->input('parent_category_id')) {
                $requestParentCategoryId = $request->input('parent_category_id');
            }

            if ($request->input('no_category') === "true" && $requestCategoryId === null && $requestParentCategoryId === null) {


                // Check if the 'Uncategorized' category exists or create it
                $uncategorizedCategory = Category::firstOrNew([
                    'title' => 'Uncategorized',
                    'parent_id' => null, // Assuming it's a top-level category
                    'restaurant_id' => $venue->id,
                ]);

                if (!$uncategorizedCategory->exists) {
                    $uncategorizedCategory->save();
                }
                $requestParentCategoryId = $uncategorizedCategory->id;
            }

            // Get the uploaded CSV file
            $csvFile = $request->file('file')->getPathname();

            // Create a CSV reader
            $csv = Reader::createFromPath($csvFile);

            // Define the CSV delimiter (e.g., comma or tab)
            $csv->setDelimiter(',');

            // Create a statement to fetch rows from the CSV
            $stmt = (new Statement())->offset(1); // Skip the header row

            // Iterate through CSV rows and validate the data
            foreach ($stmt->process($csv) as $index => $productData) {
                // Check if any of the required columns are empty
                if (empty($productData[2])) {
                    return response()->json([
                        'error' => 'Product Name is missing or empty',
                    ], 400);
                }

                if (empty($productData[3])) {
                    return response()->json([
                        'error' => 'Product Stock is missing or empty',
                    ], 400);
                }

                if (empty($productData[4])) {
                    return response()->json([
                        'error' => 'Product Price is missing or empty',
                    ], 400);
                }

                // Check if the 'Stock' column is not empty
                if (!empty($productData[3])) {
                    // Check if the 'Stock' value is not numeric
                    if (!is_numeric( intval($productData[3]))) {
                        return response()->json([
                            'error' => 'Product Stock must be a numeric value',
                        ], 400);
                    }
                }

                // Check if the 'Price' column is not empty
                if (!empty($productData[4])) {
                    // Check if the 'Price' value is not numeric
                    if (!is_numeric( intval($productData[4]))) {
                        return response()->json([
                            'error' => 'Product Price must be a numeric value',
                        ], 400);
                    }
                }
            }

            $products = $stmt->process($csv);

            // Iterate through CSV rows and insert products
            foreach ($products as $productData) {
                // don't import products that have at least onf the article_no or additional_code exists
                if (Product::where('article_no', $productData[0])->orWhere('additional_code', $productData[1])->exists()) {
                    continue;
                }
                $name = $productData[2];      // Name
                $stock = $productData[3];     // Stock
                $price = $productData[4];     // Price

                // Create a new product
                $product = new Product();
                $product->title = $name;
                $product->article_no = isset( $productData[0]) ? $productData[0] : null;
                $product->additional_code = isset( $productData[1]) ? $productData[1] : null;
                $product->price = $price;
                $product->restaurant_id = $venue->id;
                $product->is_for_retail = true;
                // Add other properties as needed

                // Save the product
                $product->save();

                if ($requestCategoryId) {
                    // check if product category exist
                    $doesProductCategoryExist = DB::table('product_category')->where('product_id', $product->id)->where('is_parent', false)->first();

                    if ($doesProductCategoryExist) {
                        if ($requestCategoryId === 'null' || $requestCategoryId === null) {
                            DB::table('product_category')->where('product_id', $product->id)->where('is_parent', false)->delete();
                        }
                        else if ($doesProductCategoryExist->category_id !== intval($requestCategoryId)) {
                            DB::table('product_category')->where('product_id', $product->id)->where('is_parent', false)->update([
                                'category_id' => intval($requestCategoryId),
                            ]);
                        }
                    }
                    else {

                        if ($requestCategoryId !== 'null' && $requestCategoryId !== null && $requestCategoryId !== '' && $requestCategoryId !== 'undefined') {

                            DB::table('product_category')->insert([
                                'category_id' => intval($requestCategoryId),
                                'product_id' => $product->id,
                            ]);
                        }
                    }
                }

                if ($requestParentCategoryId) {
                    // check if parent category exist
                    $doesParentCategoryExist = Category::where('id', $requestParentCategoryId)->first();
                    if (!$doesParentCategoryExist) {
                        return response()->json(['error' => 'Not found parent category'], 400);
                    }


                    // check if product parent category exist
                    $doesProductParentCategoryExist = DB::table('product_category')->where('product_id', $product->id)->where('is_parent', true)->first();

                    if ($doesProductParentCategoryExist) {
                        if ($doesProductParentCategoryExist->category_id !== intval($requestParentCategoryId)) {

                            DB::table('product_category')
                                ->where('product_id', $product->id)
                                ->where('is_parent', true)
                                ->update(['category_id' => intval($requestParentCategoryId)]);
                        }
                    }
                    else {
                        DB::table('product_category')->insert([
                            'category_id' => intval($requestParentCategoryId),
                            'product_id' => $product->id,
                            'is_parent' => true,
                        ]);
                    }
                }

                // Reuse the logic from createOrUpdateRetailProductInventory here
                $inventoryRetail = InventoryRetail::firstOrNew([
                    'product_id' => $product->id,
                    'venue_id' => $venue->id
                ]);

                $inventoryRetail->stock_quantity = $stock;
                $inventoryRetail->manage_stock = true;
                $inventoryRetail->used_in_whitelabel = $request->input('used_in_whitelabel', true);
                $inventoryRetail->used_in_stores = $request->input('physical_store_ids', []);
                $inventoryRetail->used_in_ecommerces = $request->input('ecommerce_platforms_ids', []);

                $inventoryRetail->save();

                // Create or update InventoryWarehouseProduct
                $warehouseInventory = InventoryWarehouseProduct::firstOrNew([
                    'inventory_warehouse_id' => $request->warehouse_id,
                    'product_id' => $product->id,
                ]);
                $warehouseInventory->quantity = $stock;
                $warehouseInventory->save();

                // Create activity for each product
                ActivityRetail::create([
                    'inventory_retail_id' => $inventoryRetail->id,
                    'venue_id' => $venue->id,
                    'activity_type' => 'import',
                    'description' => "Product imported with stock quantity {$stock}",
                    'data' => json_encode([
                        'previous_quantity' => 0,
                        'new_quantity' => $stock
                    ]),
                ]);
            }

            return response()->json(['message' => 'Products are imported successfully'], 200);
        }


    public function tryHomeProduct(Request $request ): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'id' => 'nullable|exists:take_home_products,id', // 'id' is optional, but if it is provided, it should be a valid 'take_home_products' id
            'product_id' => 'required|exists:products,id',
            'take_home' => 'required|boolean',
            'take_home_days' => 'required_if:take_home,true|nullable|integer',
        ]);


        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $id = $request->get('id');

        if ($id) {
            $takeHomeProduct = TakeHomeProduct::find($id);
            if (!$takeHomeProduct) {
                return response()->json(['error' => 'TakeHomeProduct not found'], 404);
            }
            $takeHomeProduct->update($request->all());
            return response()->json($takeHomeProduct, 200);
        } else {
            $takeHomeProduct = TakeHomeProduct::create($request->all());
            return response()->json($takeHomeProduct, 201);
        }
    }

    public function bulkImportSales(Request $request): \Illuminate\Http\JsonResponse
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

        // Define custom error messages for validation
        $messages = [
            'numeric' => 'The :attribute must be a number.',
            'string' => 'The :attribute must be a string.',
        ];


        // Validate the CSV file against the rules
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            'period' => 'required|in:1_month,3_month,6_month,12_month',
            'start_month' => 'required_if:period,1_month,3_month,6_month|numeric|min:1|max:12',
            'year' => 'required|string',
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors(),
            ], 400);
        }

        // Get the uploaded CSV file
        $csvFile = $request->file('file')->getPathname();

        // Create a CSV reader
        $csv = Reader::createFromPath($csvFile);

        // Define the CSV delimiter (e.g., comma or tab)
        $csv->setDelimiter(',');

        // Create a statement to fetch rows from the CSV
        $stmt = (new Statement())->offset(1); // Skip the header row

        // Iterate through CSV rows and validate the data
        foreach ($stmt->process($csv) as $index => $productData) {
            // Check if any of the required columns are empty
            if (empty($productData[0])) {
                return response()->json([
                    'error' => 'Article No. is missing or empty',
                ], 400);
            }

//            if (empty($productData[1])) {
//                return response()->json([
//                    'error' => 'Additional Code is missing or empty',
//                ], 400);
//            }

            // Check if any of the required columns are empty
            if (empty($productData[3])) {
                return response()->json([
                    'error' => 'Product Unit is missing or empty',
                ], 400);
            }

            if (empty($productData[4])) {
                return response()->json([
                    'error' => 'Product Sold Quantity is missing or empty',
                ], 400);
            }

            // Check if the 'Product Unit' column is not empty
            if (!empty($productData[3])) {
                // Check if the 'Unit' value either piece or cope string
                if (!in_array($productData[3], ['piece', 'cope'])) {
                    return response()->json([
                        'error' => 'Product Unit must be either piece or cope',
                    ], 400);
                }
            }

            // Check if the 'Sold Quantity' column is not empty
            if (!empty($productData[4])) {
                // Check if the 'Sold Quantity' value is not numeric
                if (!is_numeric( intval($productData[4]))) {
                    return response()->json([
                        'error' => 'Sold Quantity must be a numeric value',
                    ], 400);
                }
            }
        }

        $products = $stmt->process($csv);

        DB::beginTransaction();

        try {
            foreach ($products as $productData) {
                $unit_type = $productData[3];
                $quantity_sold = $productData[4];
                $physical_store_name = $productData[5] ?? null;
                $ecommerce_platform_name = $productData[6] ?? null;

                $product = Product::where('article_no', $productData[0])
                    ->orWhere('additional_code', $productData[1])
                    ->first();

                if (!$product) {
                    continue;
                }

                $physical_store_id = null;
                $ecommerce_platform_id = null;
                $sale_source = 'whitelabel';

                if ($physical_store_name) {
                    $physical_store = PhysicalStore::where('name', $physical_store_name)
                        ->where('venue_id', $venue->id)
                        ->first();
                    if ($physical_store) {
                        $physical_store_id = $physical_store->id;
                        $sale_source = 'physical_store';
                    }
                }

                if ($ecommerce_platform_name) {
                    $ecommerce_platform = EcommercePlatform::where('name', $ecommerce_platform_name)
                        ->where('venue_id', $venue->id)
                        ->first();
                    if ($ecommerce_platform) {
                        $ecommerce_platform_id = $ecommerce_platform->id;
                        $sale_source = 'ecommerce';
                    }
                }

                // Calculate dates
                list($start_date, $end_date) = $this->calculateDates($request->period, $request->start_month, $request->year);

                $importedSale = ImportedSale::create([
                    'product_id' => $product->id,
                    'venue_id' => $venue->id,
                    'unit_type' => $unit_type === 'cope' || $unit_type === 'piece' ? 'unit' : $unit_type,
                    'quantity_sold' => $quantity_sold,
                    'period' => $request->period,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'year' => $request->year,
                    'physical_store_id' => $physical_store_id,
                    'ecommerce_platform_id' => $ecommerce_platform_id,
                    'sale_source' => $sale_source,
                ]);

                $this->updateInventoryAndCreateActivity($product, $venue, $quantity_sold, $sale_source, $physical_store_id, $ecommerce_platform_id);
            }

            DB::commit();
            return response()->json(['message' => 'Sales are imported successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'An error occurred while importing sales: ' . $e->getMessage()], 500);
        }
    }

    private function calculateDates($period, $start_month, $year)
    {
        if ($period == '12_month') {
            $start_date = date($year . '-01-01');
            $end_date = date($year . '-12-31');
        } else {
            $start_date = date($year . '-' . (int)$start_month . '-01');
            $periodNumeric = (int)filter_var($period, FILTER_SANITIZE_NUMBER_INT);
            $end_date = date("Y-m-t", strtotime("+" . ($periodNumeric - 1) . " month", strtotime($start_date)));
        }
        return [$start_date, $end_date];
    }

    private function updateInventoryAndCreateActivity($product, $venue, $quantity_sold, $sale_source, $physical_store_id, $ecommerce_platform_id)
    {
        $inventoryRetail = InventoryRetail::where('product_id', $product->id)
            ->where('venue_id', $venue->id)
            ->first();

        if ($inventoryRetail) {
            $inventoryRetail->stock_quantity -= $quantity_sold;
            $inventoryRetail->save();

            InventoryActivity::create([
                'inventory_id' => $inventoryRetail->id,
                'product_id' => $product->id,
                'quantity' => $quantity_sold,
                'activity_category' => 'sale',
                'sold_at' => $physical_store_id ?? $ecommerce_platform_id ?? null,
                'sold_at_whitelabel' => $sale_source === 'whitelabel',
            ]);
        }
    }

    public function createSingleSale(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity_sold' => 'required|integer|min:1',
            'unit_type' => 'required|in:unit,other',
            'sale_date' => 'required|date',
            'physical_store_name' => 'nullable|string',
            'ecommerce_platform_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $venue = $this->venueService->adminAuthCheck();

        DB::beginTransaction();

        try {
            $product = Product::findOrFail($request->product_id);

            $physical_store_id = null;
            $ecommerce_platform_id = null;
            $sale_source = 'whitelabel';

            if ($request->physical_store_name) {
                $physical_store = PhysicalStore::where('name', $request->physical_store_name)
                    ->where('venue_id', $venue->id)
                    ->first();
                if ($physical_store) {
                    $physical_store_id = $physical_store->id;
                    $sale_source = 'physical_store';
                }
            }

            if ($request->ecommerce_platform_name) {
                $ecommerce_platform = EcommercePlatform::where('name', $request->ecommerce_platform_name)
                    ->where('venue_id', $venue->id)
                    ->first();
                if ($ecommerce_platform) {
                    $ecommerce_platform_id = $ecommerce_platform->id;
                    $sale_source = 'ecommerce';
                }
            }

            $importedSale = ImportedSale::create([
                'product_id' => $product->id,
                'venue_id' => $venue->id,
                'unit_type' => $request->unit_type,
                'quantity_sold' => $request->quantity_sold,
                'period' => '1_month',
                'start_date' => $request->sale_date,
                'end_date' => $request->sale_date,
                'year' => date('Y', strtotime($request->sale_date)),
                'physical_store_id' => $physical_store_id,
                'ecommerce_platform_id' => $ecommerce_platform_id,
                'sale_source' => $sale_source,
            ]);

            $this->updateInventoryAndCreateActivity($product, $venue, $request->quantity_sold, $sale_source, $physical_store_id, $ecommerce_platform_id);

            DB::commit();
            return response()->json(['message' => 'Sale created successfully', 'data' => $importedSale], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'An error occurred while creating the sale: ' . $e->getMessage()], 500);
        }
    }

    public function syncWooCommerceSales(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            $woocommerce = new Client(
                $venue->woocommerce_store_url,
                $venue->woocommerce_consumer_key,
                $venue->woocommerce_consumer_secret,
                [
                    'wp_api' => true,
                    'version' => 'wc/v3',
                ]
            );

            $orders = $woocommerce->get('orders', [
                'after' => $request->start_date,
                'before' => $request->end_date,
                'status' => 'completed',
            ]);

            DB::beginTransaction();

            foreach ($orders as $order) {
                foreach ($order->line_items as $item) {
                    $product = Product::where('sku', $item->sku)->first();
                    if (!$product) continue;

                    $importedSale = ImportedSale::create([
                        'product_id' => $product->id,
                        'venue_id' => $venue->id,
                        'unit_type' => 'unit',
                        'quantity_sold' => $item->quantity,
                        'period' => '1_month',
                        'start_date' => $order->date_created,
                        'end_date' => $order->date_created,
                        'year' => date('Y', strtotime($order->date_created)),
                        'ecommerce_platform_id' => $venue->ecommerce_platform_id,
                        'sale_source' => 'ecommerce',
                    ]);

                    $this->updateInventoryAndCreateActivity($product, $venue, $item->quantity, 'ecommerce', null, $venue->ecommerce_platform_id);
                }
            }

            DB::commit();
            return response()->json(['message' => 'WooCommerce sales synced successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'An error occurred while syncing WooCommerce sales: ' . $e->getMessage()], 500);
        }
    }



    public function getImportedSales(Request $request): \Illuminate\Http\JsonResponse
    {
        // Check if the user has any associated restaurants
        if (!auth()->user()->restaurants()->exists()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        // Get the venue short code from the request
        $apiCallVenueShortCode = $request->get('venue_short_code');

        // Ensure the venue short code is provided
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        // Find the venue by its short code
        $venue = auth()->user()->restaurants()->where('short_code', $apiCallVenueShortCode)->first();

        // Ensure the venue exists
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // Define default columns to return
        $columns = [
            'products.article_no',
            'products.additional_code',
            'products.title as product_title',
            'imported_sales.unit_type',
            'imported_sales.created_at',
            'imported_sales.start_date',
            'imported_sales.end_date',
            'imported_sales.quantity_sold'
        ];

        // Add additional columns if requested
        if ($request->has('include')) {
            $includeColumns = explode(',', $request->include);
            $columns = array_merge($columns, $includeColumns);
        }

        // Define default filters
        $filters = [
            'year' => null,
            'start_month' => null,
            'period' => null
        ];

        // Apply filters if provided
        if ($request->has('year')) {
            $filters['year'] = $request->year;
        }

        if ($request->has('start_month')) {
            $filters['start_month'] = $request->start_month;
        }

        if ($request->has('period')) {
            $filters['period'] = $request->period;
        }

        // Query to retrieve sales data
        $query = DB::table('imported_sales')
            ->where('imported_sales.venue_id', $venue->id)
            ->join('products', 'imported_sales.product_id', '=', 'products.id')
            ->select($columns);

        // Apply filters
        if ($filters['year']) {
            $query->whereYear('imported_sales.start_date', $filters['year']);
        }

        if ($filters['start_month'] && $filters['period']) {
            $startDate = date($filters['year'] . '-' . $filters['start_month'] . '-01');

            $endDate = date("Y-m-t", strtotime("+" . ((int)$filters['period'] - 1) . " month", strtotime($startDate)));
            $query->whereBetween('imported_sales.start_date', [$startDate, $endDate]);
        }

        // Fetch filtered sales data
        $sales = $query->get();

        return response()->json(['sales' => $sales], 200);
    }

    /**
     * @OA\Delete (
     *     path="/menu/products/{id}",
     *     summary="Delete a product",
     *     tags={"Menu Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Product id",
     *     required=true,
     *     @OA\Schema(
     *     type="integer",
     *     )
     *    ),
     *     @OA\Response(
     *     response=200,
     *     description="Product deleted successfully",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Product deleted successfully"),
     *     )
     *  ),
     *     @OA\Response(
     *     response=400,
     *     description="Product not found",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Category not found"),
     *     )
     * ),
     *     @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Server error"),
     *     )
     * ),
     *     @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Unauthenticated"),
     *     )
     * )
     * )
     */
    public function delete($id): \Illuminate\Http\JsonResponse
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

        try {
            $product = Product::where('restaurant_id', $venue->id)->find($id);
            if (!$product) {
                return response()->json(['message' => 'Not found product'], 404);
            }
            $product->delete();
            return response()->json(['message' => 'Product is deleted successfully'], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post (
     *     path="/menu/create",
     *     summary="Create a menu",
     *     tags={"Menu Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *     required=true,
     *     description="Pass menu data",
     *     ),
     *     @OA\Response(
     *     response=200,
     *     description="Category created successfully",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Category created successfully"),
     *     )
     * ),
     *     @OA\Response(
     *     response=400,
     *     description="Category not found",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Category not found"),
     *     )
     * ),
     *     @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(
     *     @OA\Property(property="message", type="string", example="Server error"),
     *     )
     *     )
     * )
     */
    public function createMenu(Request $request): \Illuminate\Http\JsonResponse
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
            'category_id' => 'required|integer',
            'product_ids' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            $category_id = $request->input('category_id');

            if(!Category::where('restaurant_id', $venue->id)->find($category_id)) {
                return response()->json(['message' => 'Not found category'], 404);
            }

            $product_ids = $request->input('product_ids');

            foreach ($product_ids as $key => $product_id) {
                if(!Product::where('restaurant_id', $venue->id)->find($product_id)) {
                    return response()->json(['message' => 'Not found product'], 404);
                }
                $exists = DB::table('product_category')->where('category_id', $category_id)->where('product_id', $product_id)->count();
                if ($exists == 0) {
                    DB::table('product_category')->insert([
                        'category_id' => $category_id,
                        'product_id' => $product_id
                    ]);
                }
            }
            return response()->json(['message' => 'Menu is created successfully'], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/menu/generate-digital",
     *     summary="Generate Digital Menu",
     *     tags={"Menu Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="categories", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="products", type="array", @OA\Items(type="integer")),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Digital menu generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Digital menu generated and saved successfully"),
     *             @OA\Property(property="menu", type="array", @OA\Items(
     *                 @OA\Property(property="category", type="string"),
     *                 @OA\Property(property="products", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="image", type="string"),
     *                     @OA\Property(property="price", type="number"),
     *                 )),
     *             )),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *         ),
     *     ),
     * )
     */
    public function generateDigitalMenu(Request $request): \Illuminate\Http\JsonResponse
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

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'categories' => 'required|array',
            'products' => 'required|array',
            'categories.*' => Rule::exists('categories', 'id')->where('restaurant_id', $venue->id),
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        // Get the selected categories and products from the request
        $selectedCategories = $request->input('categories');
        $selectedProducts = $request->input('products');

        // Retrieve the category and product data from the database
        $categories = Category::whereIn('id', $selectedCategories)->get();
        $products = DB::table('products')->whereIn('id', $selectedProducts)->where('restaurant_id', $venue->id);

        if($products->get()->count() != count($selectedProducts)) {
            return response()->json(['message' => 'Not found products'], 404);
        }

        // Generate the menu data
        $menuData = [];
        foreach ($categories as $category) {
            $categoryProducts = $products->whereRaw("
                    (
                        id IN
                        (
                            SELECT product_id
                            FROM product_category
                            WHERE category_id = ?
                        )
                    )
                    ", [$category->id])->get();

            $formattedProducts = $categoryProducts->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->title,
                    'image' => $product->image_path,
                    'price' => $product->price,
                ];
            });
            $menuData[] = [
                'category' => $category->title,
                'products' => $formattedProducts,
            ];
        }
        // Save the menu data as JSON in the `digital_menus` table
        $digitalMenu = DigitalMenu::create([
            'menu_data' => json_encode($menuData),
            'restaurant_id' => $venue->id,
        ]);

        // Return the JSON response and the digital menu data
        return response()->json([
            'message' => 'Digital menu generated and saved successfully',
            'menu' => $menuData,
            'digital_menu' => $digitalMenu,
        ]);
    }

    public function uploadPhoto(Request $request): JsonResponse
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
            'photo' => 'required|image|max:15360', // Maximum file size of 15MB
            'type' => 'required|string',
            'product_id' => 'required_if:type,product|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        };


        if ($request->type == 'product') {
            $product = Product::where('id', $request->product_id)->where('restaurant_id', $venue->id)->first();
            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }
        }


        if ($request->hasFile('photo')) {
            $photoFile = $request->file('photo');

            $venue = Restaurant::with('venueType')->findOrFail($venue->id);


            $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();

            // Upload photo to AWS S3
            $path = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $venue->venueType->short_name . '/' . $request->type . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)), $photoFile, $filename);

            // Save photo record in the database
            $photo = new Photo();
            $photo->venue_id = $venue->id;
            $photo->image_path = $path;
            $photo->type = $request->type;
            $photo->save();

            $gallery = new Gallery();
            $gallery->venue_id = $venue->id;
            $gallery->photo_id = $photo->id;
            $gallery->product_id = $request->product_id;

            $gallery->save();

            return response()->json(['message' => 'Photo uploaded successfully']);
        }

        return response()->json(['error' => 'No photo uploaded'], 400);
    }

    public function createOrUpdateRetailProductInventory(Request $request)
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

        $rules = [
            'product_id' => 'required|exists:products,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
//            'sku' => 'nullable|unique:inventory_retail,sku',
            'manage_stock' => 'nullable|boolean',
            'low_stock_threshold' => 'nullable|integer',
            'sold_individually' => 'nullable|boolean',
            'physical_store_ids' => 'nullable|array',
            'physical_store_ids.*' => 'exists:physical_stores,id',
            'ecommerce_platforms_ids' => 'nullable|array',
            'ecommerce_platforms_ids.*' => 'exists:ecommerce_platforms,id',
            'used_in_whitelabel' => 'nullable|boolean',
            'warehouse_id' => 'required|exists:inventory_warehouses,id',
        ];

        if ($request->input('manage_stock')) {
            $rules['stock_quantity'] = 'required|integer';
        } else {
            $rules['stock_quantity'] = 'nullable|integer';
        }


        if ($request->input('supplier_id')) {
            $supplier = Supplier::find($request->input('supplier_id'));
            if (!$supplier) {
                return response()->json([
                    'success' => false,
                    'message' => 'The specified supplier does not exist.',
                ], 400);
            }

        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }


        return DB::transaction(function () use ($request, $venue) {
            $inventoryRetail = InventoryRetail::firstOrNew(['product_id' => $request->input('product_id')]);

            if ($inventoryRetail->exists && $inventoryRetail->venue_id != $venue->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'The existing inventory does not belong to the specified venue.',
                ], 400);
            }

            if ($inventoryRetail->exists && $inventoryRetail->manage_stock && $inventoryRetail->stock_quantity != $request->input('stock_quantity')) {
                ActivityRetail::create([
                    'inventory_retail_id' => $inventoryRetail->id,
                    'venue_id' =>  $venue->id,
                    'activity_type' => 'manual_update',
                    'description' => "Stock quantity changed from {$inventoryRetail->stock_quantity} to {$request->input('stock_quantity')}",
                    'data' =>json_encode( [
                        'previous_quantity' => $inventoryRetail->stock_quantity,
                        'new_quantity' => $request->input('stock_quantity')
                    ])
                ]);
            }

            $warehouseInventory = InventoryWarehouseProduct::firstOrNew([
                'inventory_warehouse_id' => $request->warehouse_id,
                'product_id' => $request->input('product_id'),
            ]);
            $warehouseInventory->quantity = $request->input('stock_quantity');
            $warehouseInventory->save();

            $inventoryRetail->fill([
                'venue_id' => $venue->id,
                'sku' => $request->input('sku') ?? $inventoryRetail->sku ,
                'article_no' => $request->input('article_no') ?? $inventoryRetail->article_no ,
                'stock_quantity' => $request->input('stock_quantity') ?? $inventoryRetail->stock_quantity,
                'manage_stock' => $request->input('manage_stock') ?? $inventoryRetail->manage_stock ?? false,
                'low_stock_threshold' => $request->input('low_stock_threshold') ?? $inventoryRetail->low_stock_threshold ?? 0,
                'sold_individually' => $request->input('sold_individually') ?? $inventoryRetail->sold_individually ?? false,
                'supplier_id' => $request->input('supplier_id') ?? $inventoryRetail->supplier_id ?? null,
                'used_in_whitelabel' => $request->input('used_in_whitelabel') ?? $inventoryRetail->used_in_whitelabel ?? true,
                'used_in_stores' => $request->input('physical_store_ids') ?? $inventoryRetail->used_in_stores ?? [],
                'used_in_ecommerces' => $request->input('ecommerce_platforms_ids') ?? $inventoryRetail->used_in_ecommerces ?? [],
            ]);

            $inventoryRetail->save();

            $inventoryAlert = InventoryAlert::where('inventory_retail_id', $inventoryRetail->id)->first();
            if($inventoryAlert) {
                // Check for existing unresolved alert
                $existingAlert = InventoryAlertHistory::where('inventory_alert_id', $inventoryAlert?->id)
                    ->where('is_resolved', false)
                    ->latest()
                    ->first();

                if($existingAlert && $request->input('stock_quantity') > $inventoryAlert->alert_level) {
                    $existingAlert->is_resolved = true;
                    $existingAlert->resolved_at = Carbon::now();
                    $existingAlert->save();
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Inventory Retail successfully created/updated!',
                'data' => $inventoryRetail
            ], 200);
        });
    }

    public function getRetailProductInventories(): JsonResponse
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

        // Set default values for pagination
        $perPage = (int) request()->get('per_page', 15); // Default to 15 items per page
        $page = (int) request()->get('page', 1); // Default to page 1

        // Get paginated inventories
        $inventories = InventoryRetail::with(['product', 'supplier'])
            ->where('venue_id', $venue->id)
            ->when(request()->get('search'), function ($query, $searchTerm) {
                $searchTerm = '%' . $searchTerm . '%';
                $query->whereHas('product', function ($query) use ($searchTerm) {
                    $query->where('title', 'like', $searchTerm);
                });
            })
            ->paginate($perPage, ['*'], 'page', $page) // Paginate the results
            ->through(function ($inventory) {
                // Get the active (non-soft-deleted) inventory alert
                $activeInventoryAlert = $inventory->inventoryAlerts()
                    ->whereNull('deleted_at')
                    ->latest()
                    ->first();

                // Get all alert histories for the active alert
                $inventoryAlertHistories = $activeInventoryAlert
                    ? InventoryAlertHistory::where('inventory_alert_id', $activeInventoryAlert->id)
//                    ->where('is_resolved', false)
                        ->get()
                    : collect();

                $inventory->product_title = $inventory->product->title ?? null;
                $inventory->brand = Brand::where('id', $inventory->product->brand_id)->first();
                $inventory->supplier_name = $inventory->supplier->name ?? null;
                $inventory->inventory_alert_histories = $inventoryAlertHistories;
                $inventory->inventory_alert = $activeInventoryAlert;
                $inventory->has_active_alert = $inventoryAlertHistories->isNotEmpty() && !$inventoryAlertHistories->last()->is_resolved;

                return $inventory;
            });

        return response()->json([
            'success' => true,
            'message' => 'Inventory Retail successfully retrieved!',
            'data' => $inventories
        ], 200);
    }


    public function getRetailProductInventoryActivity($inventory_id): JsonResponse|\Illuminate\Support\Collection
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


        $activities = DB::table('activity_retail')
            ->where('activity_retail.inventory_retail_id', $inventory_id)
            ->where('activity_retail.venue_id', $venue->id)
            ->select(
                'activity_retail.activity_type',
                'activity_retail.description',
                'activity_retail.data',
                'activity_retail.created_at',
                'activity_retail.updated_at'
            )
            ->get();

        // Transform the data field of each activity
        $formattedActivities = $activities->map(function ($activity) {
            $dataString = collect(json_decode($activity->data, true))->map(function ($value, $key) {
                return "$key: $value";
            })->implode(', ');

            $activity->data = $dataString;

            return $activity;
        });

        return response()->json([
            'success' => true,
            'message' => 'Inventory Retail successfully created/updated!',
            'data' => $formattedActivities
        ], 200);
    }


    public function createAndAssignToProduct(Request $request): JsonResponse
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

        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer',
            'attributes' => 'required|array',
            'attributes.*.name' => 'required|string',
            'attributes.*.values' => 'required|array',
            'attributes.*.visible_on_product_page' => 'required|boolean',
            'attributes.*.used_for_variations' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        // Validate the product exists
        $product = Product::where('id', $request->input('product_id'))->where('restaurant_id', $venue->id)->first();
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        };

        foreach ($request->input('attributes') as $attributeData) {
            // Check if the attribute already exists or create a new one
            $attribute = ProductAttribute::create
            (
                [
                    'name' => $attributeData['name'],
                    'venue_id' => $venue->id,
                    'product_id' => $product->id,
                ]
            );

            // Save attribute values
            foreach ($attributeData['values'] as $value) {

                $attributeValue = $attribute->values()->create(['value' => $value, 'product_id' => $product->id]);

                // TODO: fix why getting the attributes doesn't work
                DB::table('product_attribute_value')->insert(
                    [
                        'product_id' => $product->id,
                        'attribute_value_id' => $attributeValue->id,
                        'visible_on_product_page' => $attributeData['visible_on_product_page'],
                        'used_for_variations' => $attributeData['used_for_variations'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

            }
        }

        return response()->json(['message' => 'Attributes created and assigned to product successfully'], 200);
    }

    public function deleteProductAttribute(Request $request, $attributeId): JsonResponse
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

        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        // Validate the product exists
        $product = Product::where('id', $request->input('product_id'))->where('restaurant_id', $venue->id)->first();
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Get the attribute to be deleted
        $attribute = ProductAttribute::where('id', $attributeId)->where('product_id', $product->id)->first();
        if (!$attribute) {
            return response()->json(['message' => 'Attribute not found'], 404);
        }

        // Detach the relation from the pivot table
        $product->attributeValues()->detach($attribute->values->pluck('id')->toArray());

        // Delete the attribute values and the attribute
        $attribute->values()->delete();
        $attribute->delete();

        return response()->json(['message' => 'Attribute and its values removed from product successfully'], 200);
    }

    public function getProductAttributesForVariations($productId): JsonResponse
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


        $product = Product::where('id', $productId)->where('restaurant_id', $venue->id)->first();

        if (!$product || $product->restaurant_id != $venue->id) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $attributes = DB::table('product_attribute_value')
            ->join('attribute_values', 'product_attribute_value.attribute_value_id', '=', 'attribute_values.id')
            ->join('product_attributes', 'attribute_values.attribute_id', '=', 'product_attributes.id')
            ->where('product_attribute_value.product_id', $productId)
            ->select(
                'product_attributes.name as attribute_name',
                'attribute_values.value as attribute_value',
                'attribute_values.id as attribute_value_id',
                'product_attributes.id as attribute_id',
                'product_attribute_value.visible_on_product_page as visible_on_product_page',
                'product_attribute_value.used_for_variations as used_for_variations'
            )
            ->get();

        $groupedAttributes = $attributes->groupBy('attribute_id')
            ->map(function ($groupedItems) {
                return [
                    'attribute_id' => $groupedItems[0]->attribute_id,
                    'attribute_name' => $groupedItems[0]->attribute_name,
                    'visible_on_product_page' => $groupedItems[0]->visible_on_product_page,
                    'used_for_variations' => $groupedItems[0]->used_for_variations,
                    'values' => $groupedItems->map(function ($item) {
                        return [
                            'attribute_value_id' => $item->attribute_value_id,
                            'attribute_value' => $item->attribute_value
                        ];
                    })->toArray()
                ];
            })->values(); // Reset the keys after grouping


        return response()->json(['data' => $groupedAttributes], 200);
    }


    public function createUpdateVariationsForProduct(Request $request): JsonResponse
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

        // Validation rules
        $rules = [
            'attribute_id' => 'required|integer|exists:product_attributes,id',
            'value_id' => 'required|integer|exists:attribute_values,id',
            'price' => 'required|numeric|min:0',
            'variation_id' => 'sometimes|integer|exists:variations,id',
            'product_id' => 'sometimes|integer|exists:products,id',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        // Validate the product exists
        $product = Product::where('id', $request->input('product_id'))->where('restaurant_id', $venue->id)->first();
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }


        if ($request->has('variation_id')) {
            // If variation_id is provided, find and update the existing variation
            $variation = Variation::find($request->input('variation_id'));
            $variation->update([
                'attribute_id' => $request->input('attribute_id'),
                'value_id' => $request->input('value_id'),
                'price' => $request->input('price'),
            ]);
        } else {
            // Else, create a new variation
            $product->variations()->create([
                'attribute_id' => $request->input('attribute_id'),
                'value_id' => $request->input('value_id'),
                'price' => $request->input('price'),
                'venue_id' => $venue->id,
                'product_id' => $product->id,
            ]);
        }


        return response()->json(['message' => 'Variations created successfully'], 200);
    }

    public function deleteProductVariation(Request $request, $variationId): JsonResponse
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

        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        // Validate the product exists
        $product = Product::where('id', $request->input('product_id'))->where('restaurant_id', $venue->id)->first();

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Fetch the variation and ensure it belongs to the given product
        $variation = Variation::where('id', $variationId)->where('product_id', $product->id)->first();
        if (!$variation) {
            return response()->json(['error' => 'Variation not found or does not belong to the specified product'], 404);
        }

        // Delete the variation
        $variation->delete();

        return response()->json(['message' => 'Variation deleted successfully'], 200);
    }

    public function syncWarehouseInventory()
    {
        $warehouses = InventoryWarehouse::all();

        foreach ($warehouses as $warehouse) {
            // Assuming you have an API or external source to get updated quantities
            $updatedInventory = $this->getUpdatedInventoryFromExternalSource($warehouse->id);

            foreach ($updatedInventory as $item) {
                InventoryWarehouseProduct::updateOrCreate(
                    [
                        'inventory_warehouse_id' => $warehouse->id,
                        'product_id' => $item['product_id'],
                    ],
                    ['quantity' => $item['quantity']]
                );
            }
        }

        return response()->json(['message' => 'Warehouse inventory synced successfully']);
    }

    private function getUpdatedInventoryFromExternalSource($warehouseId): array
    {
        // Implement the logic to fetch updated inventory from your external source
        // This could be an API call, database query, or any other method
        // For now, we'll use a dummy response for demonstration purposes
        return [
            ['product_id' => 1, 'quantity' => 100],
            ['product_id' => 2, 'quantity' => 200],
            // Add more items as needed
        ];
    }

    public function syncRetailInventory(): JsonResponse
    {
        $venues = Restaurant::all();

        foreach ($venues as $venue) {
            $warehouseInventory = InventoryWarehouseProduct::whereHas('warehouse', function ($query) use ($venue) {
                $query->where('venue_id', $venue->id);
            })->get();

            foreach ($warehouseInventory as $item) {
                $inventoryRetail = InventoryRetail::updateOrCreate(
                    [
                        'venue_id' => $venue->id,
                        'product_id' => $item->product_id,
                    ],
                    ['stock_quantity' => $item->quantity]
                );

                ActivityRetail::create([
                    'inventory_retail_id' => $inventoryRetail->id,
                    'venue_id' => $venue->id,
                    'activity_type' => 'sync',
                    'description' => "Retail inventory synced based on warehouse changes",
                    'data' => json_encode([
                        'new_quantity' => $item->quantity,
                    ]),
                ]);
            }
        }

        return response()->json(['message' => 'Retail inventory synced successfully']);
    }



}
