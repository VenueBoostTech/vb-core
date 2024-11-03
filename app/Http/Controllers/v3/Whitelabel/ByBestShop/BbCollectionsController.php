<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use App\Models\AccountingFinance\Currency;
use App\Models\Collection;
use App\Models\Category;
use App\Models\ProductAttribute;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\Brand;

class BbCollectionsController extends Controller
{
    // Return collection products
    public function collectionProducts(Request $request, $collection_url): \Illuminate\Http\JsonResponse
    {
        try {
            // ... (keep all the existing logic here)

            // Replace the view rendering with a JSON response
            return response()->json([
                'currency' => $currency,
                'products' => $products,
                'products_counter' => $products_counter,
                'filters' => $filters,
                'collection' => $collection,
                'categories' => $categories,
                'category_status' => $category_status,
                'prices' => $prices,
                'brands' => $brands,
                'wish_list_products' => $wish_list_products,
                'group_id' => $request->search
            ]);

        } catch (\Throwable $th) {
            return response()->json(['error' => 'Not found'], 404);
        }
    }

    // Search products
    public function searchProducts(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // ... (keep all the existing logic here)

            // Replace the view rendering with a JSON response
            return response()->json([
                'currency' => $currency,
                'products' => $products,
                'wish_list_products' => $wish_list_products
            ]);

        } catch (\Throwable $th) {
            return response()->json(['error' => 'An error occurred'], 500);
        }
    }

    // Show all collections
    public function showAllCollections(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // ... (keep all the existing logic here)

            // Replace the view rendering with a JSON response
            return response()->json([
                'collections' => $collections_query->distinct()->get()
            ]);

        } catch (\Throwable $th) {
            return response()->json(['error' => 'An error occurred'], 500);
        }
    }
}
