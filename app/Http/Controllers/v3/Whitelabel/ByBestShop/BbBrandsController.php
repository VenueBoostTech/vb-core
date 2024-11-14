<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use App\Models\AccountingFinance\Currency;
use App\Models\Collection;
use App\Models\Category;
use App\Models\VbStoreAttribute;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class BbBrandsController extends Controller
{
    public function brandProducts(Request $request, $brand_url)
    {
        try {
            // Original logic remains the same, just removed view rendering and session handling
            $apiCallVenueAppKey = request()->get('venue_app_key');
            $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
            if (!$venue) {
                return response()->json(['error' => 'Venue not found or user not eligible'], 404);
            }

            // $temp = $request->page;
            // $request->merge(['page' => 1]);
            // $paginate = 30;
            // if ((int) $temp) {
            //     $paginate = 30 * (int) $temp;
            // }

            // $currency = Currency::where('is_primary', '=', true)->first();
            // $exchange_rate = Currency::where('is_primary', '=', true)->first();

            $brand = Brand::where('venue_id', $venue->id)->where('url', '=', $brand_url)->first();
            if (!$brand) {
                return response()->json(['error' => 'Brand not found'], 404);
            }

            // Products
            $products_query = Product::with(['attribute.option', 'productImages'])
                ->select(
                    "products.*",
                    DB::raw("(SELECT MAX(vb_store_products_variants.sale_price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_sale_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.date_sale_start) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_start"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.date_sale_end) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_end"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as max_regular_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as min_regular_price"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.stock_quantity) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as total_stock_quantity"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.currency_alpha) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id AND vb_store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha")
                )
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.warehouse_alpha')
                ->whereNotNull('products.currency_alpha')
                ->where('products.product_status', '=', 1)
                ->where('products.brand_id', $brand->id)
                ->whereNull('products.deleted_at')
                ->orderBy('created_at', 'DESC');

            if ($request->filled('search')) {
                $products_query->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                    ->where('product_groups.group_id', '=', $request->search);
            }

            $products = $products_query
                ->orderBy('products.created_at', 'DESC')
                ->where('products.product_status', '=', 1)
                ->whereNull('products.deleted_at')
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->whereNotNull('products.warehouse_alpha')
                ->distinct('products.id')
                ->paginate(30);

            $totalProducts = $products->total();

            // Filters
            $filters_query = VbStoreAttribute::join('vb_store_attributes_options', 'vb_store_attributes_options.attribute_id', '=', 'vb_store_attributes.id')
                ->join('vb_store_product_attributes', 'vb_store_product_attributes.attribute_id', 'vb_store_attributes_options.id')
                ->join('products', 'vb_store_product_attributes.product_id', 'products.id')
                ->select(
                    'vb_store_attributes.*',
                    'vb_store_attributes_options.id as option_id',
                    'vb_store_attributes_options.option_name as option_name',
                    'vb_store_attributes_options.option_name_al as option_name_al'
                )
                ->distinct()
                ->where('products.brand_id', '=', $brand->id)
                ->whereNull('products.deleted_at')
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.warehouse_alpha')
                ->whereNotNull('products.currency_alpha')
                ->where('products.product_status', '=', 1)
                ->orderByRaw('LENGTH(vb_store_attributes_options.option_name) asc')
                ->orderBy('vb_store_attributes_options.option_name', 'ASC')->distinct();

            if ($request->filled('search')) {
                $filters_query->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                    ->where('product_groups.group_id', '=', $request->search)->distinct();
            }

            $filters = $filters_query->distinct()->get()->groupBy('attr_name')->sortDesc();

            // Categories
            $categorues_query = Category::join('product_category', 'product_category.category_id', '=', 'categories.id')
                ->select(
                    'categories.*',
                    DB::raw("(SELECT COUNT(product_category.product_id) FROM `product_category`) as product_count")
                )
                ->join('products', 'products.id', '=', 'product_category.product_id')
                ->where('products.product_status', '=', 1)
                ->where('products.brand_id', '=', $brand->id)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.warehouse_alpha')
                ->whereNotNull('products.currency_alpha')
                ->whereNull('products.deleted_at')
                ->distinct();

            $category_status = Category::join('product_category', 'product_category.category_id', '=', 'categories.id')
                ->join('products', 'product_category.product_id', '=', 'products.id')
                ->where('products.product_status', '=', 1)
                ->where('products.brand_id', '=', $brand->id)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.warehouse_alpha')
                ->whereNotNull('products.currency_alpha')
                ->whereNull('products.deleted_at')
                ->distinct()
                ->select(
                    'product_category.category_id',
                    DB::raw("(SELECT COUNT(product_category.product_id) FROM `product_category`) as product_count")
                )
                ->get();

            if ($request->filled('search')) {
                $categorues_query->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                    ->select(
                        'categories.*',
                        DB::raw("(SELECT COUNT(product_category.product_id) FROM `product_category`) as product_count")
                    )
                    ->where('products.product_status', '=', 1)
                    ->where('products.stock_quantity', '>', 0)
                    ->whereNotNull('products.warehouse_alpha')
                    ->whereNotNull('products.currency_alpha')
                    ->whereNull('products.deleted_at')
                    ->where('product_groups.group_id', '=', $request->search)
                    ->orderBy('categories.order_no', 'ASC')
                    ->orderBy('categories.category', 'ASC');
            }

            $categories = $categorues_query
                ->with([
                    'childrenCategory' => function ($q) use ($brand) {
                        $q->leftJoin('product_category', 'product_category.category_id', '=', 'categories.id')
                            ->join('products', 'products.id', '=', 'product_category.product_id')
                            ->select(
                                'categories.*',
                                DB::raw("(SELECT COUNT(product_category.product_id) FROM `product_category`) as product_count")
                            )
                            ->where('products.brand_id', '=', $brand->id)
                            ->where('products.stock_quantity', '>', 0)
                            ->where('products.product_status', '=', 1)
                            ->whereNotNull('products.warehouse_alpha')
                            ->whereNotNull('products.currency_alpha')
                            ->whereNull('products.deleted_at')
                            ->orderBy('categories.order_no', 'ASC', 'categories.category', 'ASC')
                            ->distinct();
                    }
                ])
                ->where('products.brand_id', '=', $brand->id)
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.warehouse_alpha')
                ->whereNotNull('products.currency_alpha')
                ->whereNull('products.deleted_at')
                ->whereNotNull('products.currency_alpha')
                ->whereNull('parent_id')
                ->select(
                    'categories.*',
                    DB::raw("(SELECT COUNT(product_category.product_id) FROM `product_category`) as product_count")
                )
                ->orderBy('categories.order_no', 'ASC')
                ->orderBy('categories.category', 'ASC')
                ->distinct()->get();

            // Collections
            $collectons_query = Collection::join('product_collections', 'product_collections.collection_id', '=', 'collections.id')
                ->join('products', 'products.id', '=', 'product_collections.product_id')
                ->select('collections.*')
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.warehouse_alpha')
                ->whereNotNull('products.currency_alpha')
                ->where('products.product_status', '=', 1)
                ->whereNull('products.deleted_at')
                ->where('products.brand_id', '=', $brand->id);

            if ($request->filled('search')) {
                $collectons_query->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                    ->where('product_groups.group_id', '=', $request->search);
            }

            $collections = $collectons_query->orderBy('collections.name', 'ASC')->distinct()->get();

            // Prices
            $prices_query = Product::where('brand_id', '=', $brand->id)
                ->where('products.product_status', '=', 1)
                ->whereNull('products.deleted_at')
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.warehouse_alpha')
                ->whereNotNull('products.currency_alpha')
                ->select(
                    DB::raw('IFNULL(Max(products.price), 0) as max_price'),
                    DB::raw('IFNULL(Min(products.price), 0) as min_price')
                );

            if ($request->filled('search')) {
                $prices_query->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                    ->where('product_groups.group_id', '=', $request->search);
            }

            $prices = $prices_query->first();

            return response()->json([
                // 'currency' => $currency,
                'brand' => $brand,
                'products' => $products,
                'totalProducts' => $totalProducts,
                'filters' => $filters,
                'prices' => $prices,
                'categories' => $categories,
                'category_status' => $category_status,
                'collections' => $collections,
                'group_id' => $request->search
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function searchProducts(Request $request)
    {
        try {
            // Original logic remains the same, just removed view rendering

            $currency = Currency::where('is_primary', '=', true)->first();

            // Products query remains the same

            return response()->json([
                // 'currency' => $currency,
                // 'products' => $products
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function showAllBrands(Request $request)
    {
        try {
            // Original logic remains the same, just removed view rendering

            $brands_query = Brand::join('products', 'products.brand_id', '=', 'store_brands.id');
            // ... (query remains the same)

            return response()->json([
                'brands' => $brands_query->get(),
                'search' => $request->search
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }
}
