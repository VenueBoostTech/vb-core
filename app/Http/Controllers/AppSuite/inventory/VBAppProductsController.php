<?php
namespace App\Http\Controllers\AppSuite\Inventory;

use App\Http\Controllers\Controller;
use App\Models\GiftOccasion;
use App\Models\GiftSuggestion;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VBAppProductsController extends Controller
{
    protected VenueService $venueService;
    protected int $perPage = 20; // Number of products per page

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function index(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'category' => 'integer',
            'offset' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $category_id = $request->input('category');
            $offset = $request->input('offset', 0);

            $query = Product::with('takeHome')->where('restaurant_id', $venue->id);
            if ($category_id) {
                $query->whereHas('categories', function ($q) use ($category_id) {
                    $q->where('categories.id', $category_id);
                });
            }

            $totalProducts = $query->count();

            $products = $query->orderBy('created_at', 'DESC')
                ->offset($offset)
                ->limit($this->perPage)
                ->get();

            $storeSetting = StoreSetting::where('venue_id', $venue->id)->first();
            $currency = $storeSetting->currency ?? $venue->currency ?? 'USD';

            $updatedProducts = $products->map(function ($product) use ($currency) {
                $productData = $product->toArray();
                // if ($product->image_path !== null) {
                //     $productData['image_path'] = Storage::disk('s3')->temporaryUrl($product->image_path, '+5 minutes');
                // }
                $productData['currency'] = $currency;
                return $productData;
            });

            $nextOffset = $offset + $this->perPage;
            $hasMore = $nextOffset < $totalProducts;

            return response()->json([
                'message' => 'Products retrieved successfully',
                'products' => $updatedProducts,
                'pagination' => [
                    'total' => $totalProducts,
                    'offset' => $offset,
                    'limit' => $this->perPage,
                    'has_more' => $hasMore,
                    'next_offset' => $hasMore ? $nextOffset : null,
                ],
            ], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function giftSuggestions(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'occasion' => 'required|string',
            'budget' => 'required|numeric|min:0',
            'store_id' => 'required|exists:physical_stores,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $occasion = $request->input('occasion');
        $budget = $request->input('budget');
        $storeId = $request->input('store_id');

        $giftOccasion = GiftOccasion::firstOrCreate(['name' => strtolower($occasion)]);

        $suggestedProducts = $this->getSuggestedProducts($giftOccasion->id, $budget, $storeId);

        $suggestions = $suggestedProducts->map(function ($product) {
            // if ($product->image_path !== null) {
            //     $product->image_path = Storage::disk('s3')->temporaryUrl($product->image_path, '+5 minutes');
            // }
            return [
                'id' => $product->id,
                'name' => $product->name,
                'brand' => $product->brand,
                'price' => $product->price,
                'description' => $product->description,
                'image_path' => $product->image_path,
            ];
        });

        $this->storeSuggestions($giftOccasion->id, $suggestedProducts->pluck('id'), $storeId);

        return response()->json([
            'suggestions' => $suggestions,
            'total' => $suggestions->count(),
        ]);
    }

    private function getSuggestedProducts($occasionId, $budget, $storeId)
    {
        // Get previously suggested products
        $previouslySuggested = GiftSuggestion::where('gift_occasion_id', $occasionId)
            ->where('physical_store_id', $storeId)
            ->select('product_id')
            ->groupBy('product_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(5)
            ->pluck('product_id');

        // Get products that match the criteria and were previously suggested
        $suggestedProducts = Product::whereHas('storeInventories', function($query) use ($storeId) {
            $query->where('physical_store_id', $storeId)
                ->where('quantity', '>', 0);
        })
            ->where('price', '<=', $budget)
            ->whereIn('id', $previouslySuggested)
            ->get();

        // If we have less than 10 products, get additional random products
        $additionalCount = 10 - $suggestedProducts->count();
        if ($additionalCount > 0) {
            $additionalProducts = Product::whereHas('storeInventories', function($query) use ($storeId) {
                $query->where('physical_store_id', $storeId)
                    ->where('quantity', '>', 0);
            })
                ->where('price', '<=', $budget)
                ->whereNotIn('id', $previouslySuggested)
                ->inRandomOrder()
                ->limit($additionalCount)
                ->get();

            $suggestedProducts = $suggestedProducts->concat($additionalProducts);
        }

        return $suggestedProducts;
    }

    private function storeSuggestions($occasionId, $productIds, $storeId): void
    {
        $suggestions = $productIds->map(function ($productId) use ($occasionId, $storeId) {
            return [
                'gift_occasion_id' => $occasionId,
                'product_id' => $productId,
                'physical_store_id' => $storeId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        GiftSuggestion::insert($suggestions);
    }
}
