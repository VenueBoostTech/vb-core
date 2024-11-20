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
use App\Models\VbStoreAttribute;

class BbCollectionsController extends Controller
{
    // Return collection products
    public function collectionProducts(Request $request, string $collection_url): \Illuminate\Http\JsonResponse
    {
        // try {
            $collection = Collection::where('slug', $collection_url)->first();
            if (!@$collection) {
                return response()->json(['error' => 'Collections not found'], 404);
            }

            $products_query = Product::with(['attribute.option', 'productImages'])
                ->select(
                    "products.*",
                    // DB::raw("JSON_UNQUOTE(JSON_EXTRACT(products.product_name, '$.en')) AS product_name_en"),
                    // DB::raw("JSON_UNQUOTE(JSON_EXTRACT(products.product_short_description, '$.en')) AS product_short_description_en"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.sale_price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_sale_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.date_sale_start) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_start"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.date_sale_end) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_end"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as max_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as min_price"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.stock_quantity) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as total_stock_quantity"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.currency_alpha) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id AND vb_store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha")
                )
                ->join('product_collections', 'product_collections.product_id', '=', 'products.id')
                ->where('product_collections.collection_id', '=', $collection->id)->orderBy('products.created_at', 'DESC')
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.warehouse_alpha')
                ->whereNotNull('products.currency_alpha')->distinct();

            if ($request->filled('search')) {
                $products_query->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                    ->where('product_groups.group_id', '=', $request->search)->distinct();
            }

            $products = $products_query
                ->select(
                    "products.*",
                    // DB::raw("JSON_UNQUOTE(JSON_EXTRACT(products.product_name, '$.en')) AS product_name_en"),
                    // DB::raw("JSON_UNQUOTE(JSON_EXTRACT(products.product_short_description, '$.en')) AS product_short_description_en"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.sale_price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_sale_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.date_sale_start) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_start"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.date_sale_end) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_end"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as max_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as min_price"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.stock_quantity) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as total_stock_quantity"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.currency_alpha) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id AND vb_store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha")
                )->with('productImages')->distinct()->paginate(30);

            $filters_query = VbStoreAttribute::join('vb_store_attributes_options', 'vb_store_attributes_options.attribute_id', '=', 'vb_store_attributes.id')
                ->join('vb_store_product_variant_attributes', 'vb_store_product_variant_attributes.attribute_id', '=', 'vb_store_attributes_options.id')
                ->join('vb_store_products_variants', 'vb_store_product_variant_attributes.variant_id', 'vb_store_products_variants.id')
                ->join('products', 'vb_store_products_variants.product_id', 'products.id')
                ->select(
                    'vb_store_attributes.*',
                    'vb_store_attributes_options.id as option_id',
                    'vb_store_attributes_options.option_name as option_id',
                    'vb_store_attributes_options.option_name_al as option_name_al',
                    // DB::raw("JSON_UNQUOTE(JSON_EXTRACT(vb_store_attributes_options.option_name, '$." . App::getLocale() . "')) AS option_name")
                )
                ->distinct()
                ->join('product_collections', 'product_collections.product_id', '=', 'products.id')
                ->where('product_collections.collection_id', '=', $collection->id)
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.warehouse_alpha')
                ->whereNotNull('products.currency_alpha');
            if (App::getLocale() == "en") {
                $filters_query = $filters_query->orderByRaw('LENGTH(vb_store_attributes_options.option_name_al) asc')
                ->orderBy('vb_store_attributes_options.option_name_al', 'ASC');
            } else {
                $filters_query = $filters_query->orderByRaw('LENGTH(vb_store_attributes_options.option_name) asc')
                ->orderBy('vb_store_attributes_options.option_name', 'ASC');
            }

            $filters_query = $filters_query->distinct();

            $categories = Category::join('product_category', 'product_category.category_id', '=', 'categories.id')
                ->join('products', 'products.id', '=', 'product_category.product_id')
                ->join('product_collections', 'product_collections.product_id', '=', 'products.id')
                ->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                ->where('product_groups.group_id', '=', $request->search)
                ->where('product_collections.collection_id', '=', $collection->id)
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                // ->whereNull('products.deleted_at')
                ->with(['childrenCategory' => function ($q) use ($collection) {
                    $q->leftJoin('product_category', 'product_category.category_id', '=', 'categories.id')
                        ->join('products', 'products.id', '=', 'product_category.product_id')
                        ->join('product_collections', 'product_collections.product_id', '=', 'products.id')
                        ->select(
                            'categories.*',
                            DB::raw("(SELECT COUNT(product_category.product_id)
                            FROM `product_category`
                            ) as product_count")
                        )
                        ->where('product_collections.collection_id', '=', $collection->id)
                        ->where('products.stock_quantity', '>', 0)
                        ->where('products.product_status', '=', 1)
                        ->whereNotNull('products.currency_alpha')
                        ->whereNull('products.deleted_at')
                        // ->whereNull('product_category.deleted_at')
                        // ->whereNull('categories.deleted_at')
                        ->distinct();;
                }])
                ->select('categories.*')
                ->orderBy('categories.order_no', 'ASC', 'categories.category', 'ASC')
                ->whereNull('parent_id')->distinct()->get();

            $category_status = Category::join('product_category', 'product_category.category_id', '=', 'categories.id')
                ->join('products', 'product_category.product_id', '=', 'products.id')
                ->join('product_collections', 'product_collections.product_id', '=', 'products.id')
                ->where('products.product_status', '=', 1)
                ->where('product_collections.collection_id', '=', $collection->id)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.warehouse_alpha')
                ->whereNotNull('products.currency_alpha')
                ->whereNull('products.deleted_at')
                ->distinct()
                ->select(
                    'product_category.category_id',
                    DB::raw("(SELECT COUNT(product_category.product_id)
                            FROM `product_category`
                            ) as product_count")
                )
                ->get();

            $prices_query = Product::join('product_collections', 'product_collections.product_id', '=', 'products.id')
                ->where('product_collections.collection_id', '=', $collection->id)
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.warehouse_alpha')
                ->whereNotNull('products.currency_alpha')
                ->select(
                    DB::raw('IFNULL(Max(products.price), 0) as max_price'),
                    DB::raw('IFNULL(Min(products.price), 0) as min_price')
                );

            $brands_query = Brand::join('products', 'products.brand_id', '=', 'brands.id')
                ->join('product_category', 'product_category.product_id', '=', 'products.id')
                ->join('product_collections', 'product_collections.product_id', '=', 'products.id')
                ->where('product_collections.collection_id', '=', $collection->id)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.warehouse_alpha')
                ->whereNotNull('products.currency_alpha')
                ->where('products.product_status', '=', 1);

            $filters = $filters_query->distinct()->get()->groupBy('attr_name')->sortDesc();

            $prices = $prices_query->first();
            $brands = $brands_query->where('brands.status_no', '=', '1')->select('brands.*')->distinct()->get();

            // Replace the view rendering with a JSON response
            return response()->json([
                'collection' => $collection,
                'products' => $products,
                'filters' => $filters,
                'prices' => $prices,
                'brands' => $brands,
                'categories' => $categories
            ]);
        // } catch (\Throwable $th) {
        //     return response()->json(['error' => 'Not found'], 404);
        // }
    }

    // Search products
    public function searchProducts(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $currency = Currency::where('is_primary', '=', true)->first();

            $products_query = Product::join('product_category', 'product_category.product_id', '=', 'products.id')
                ->select('products.*')->distinct();

            // Filter by category
            if ($request->filled('category_id')) {
                $products_query->where('product_category.category_id', '=', $request->category_id)->orderBy('products.created_at', 'DESC');
            }

            // Filter by minimum price
            if ($request->filled('min_price_search')) {
                $products_query->where('products.price', '>=', $request->min_price_search);
            }

            // Filter by maximum price
            if ($request->filled('max_price_search')) {
                $products_query->where('products.price', '<=', $request->max_price_search);
            }

            // Filter by product group
            if ($request->filled('group_id')) {
                $products_query->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                    ->where('product_groups.group_id', '=', $request->group_id);
            }

            // Filter by brands
            if ($request->filled('brand_id') && is_array($request->brand_id)) {
                foreach ($request->brand_id as $brand) {
                    $products_query->where('products.brand_id', '=', $brand);
                }
            }

            // Filter by collection
            if ($request->filled('collection_id') && is_array($request->collection_id)) {
                $products_query->join('product_collections', 'product_collections.product_id', '=', 'products.id');
                $products_query->where(function ($query) use ($request) {
                    foreach ($request->collection_id as $collection) {
                        $query->orWhere('product_collections.collection_id', '=', $collection);
                    }
                });
            }

            // Filter by atributes
            if ($request->filled('atributes_id') && is_array($request->atributes_id)) {
                $products_query->join('vb_store_product_attributes', 'vb_store_product_attributes.product_id', '=', 'products.id');
                $products_query->where(function ($query) use ($request) {
                    foreach ($request->atributes_id as $atribute) {
                        $query->orWhere('vb_store_product_attributes.attribute_id', '=', $atribute);
                    }
                });
            }

            $products = $products_query->orderBy('products.created_at', 'DESC')->paginate(30);

            // Replace the view rendering with a JSON response
            return response()->json([
                'currency' => $currency,
                'products' => $products
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
            $collections = Collection::join('product_collections', 'product_collections.collection_id', '=', 'collections.id')
                ->join('products', 'products.id', '=', 'product_collections.product_id')
                ->select('collections.*')->orderBy('collections.name', 'ASC')->distinct()->get();

            // Replace the view rendering with a JSON response
            return response()->json([
                'collections' => $collections
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'An error occurred'], 500);
        }
    }
}
