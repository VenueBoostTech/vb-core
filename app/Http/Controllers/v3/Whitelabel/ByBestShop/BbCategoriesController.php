<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Currency;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use App\Models\VbStoreAttribute;
use App\Models\Collection;
use App\Models\Brand;
use Illuminate\Support\Facades\DB;

class BbCategoriesController extends Controller
{
    public function categoryProducts(Request $request, string $category_url): \Illuminate\Http\JsonResponse
    {
        try {
            // $category_url = $request->input('category_url');
            // Assuming you have similar logic as in the original controller
            // Retrieve category, products, filters, etc.

            $products_query = Product::join('product_category', 'product_category.product_id', '=', 'products.id')
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
                ->where(function ($query) use ($category_url) {
                    $category_multiple = Category::where('category_url', '=', $category_url)->get();
                    foreach ($category_multiple as $single_category) {
                        $hirearkia = Category::with(['childrenCategory'])
                            ->where('id', '=', $single_category->id)
                            ->select('categories.*')
                            ->orderBy('categories.category', 'ASC')
                            ->distinct()->get();

                        foreach ($hirearkia as $main_child) {
                            $query->orWhere('product_category.category_id', '=', $main_child->id);
                            foreach ($main_child->childrenCategory as $second_child) {
                                $query->orWhere('product_category.category_id', '=', $second_child->id);
                                foreach ($second_child->childrenCategory as $thid_child) {
                                    $query->orWhere('product_category.category_id', '=', $thid_child->id);
                                }
                            }
                        }
                    }
                })
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha');

            if ($request->filled('search')) {
                $products_query->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                    ->where('product_groups.group_id', '=', $request->search);
            }

            $products_query = $products_query
                ->orderBy('products.created_at', 'DESC')
                ->with('productImages')
                ->distinct('products.id');

            $totalProducts = $products_query->count('products.id');
            $products = $products_query->paginate(30);

            $filters_query = VbStoreAttribute::join('vb_store_attributes_options', 'vb_store_attributes_options.attribute_id', '=', 'vb_store_attributes.id')
                ->join('vb_store_product_variant_attributes', 'vb_store_product_variant_attributes.attribute_id', '=', 'vb_store_attributes_options.id')
                ->join('vb_store_products_variants', 'vb_store_product_variant_attributes.variant_id', 'vb_store_products_variants.id')
                ->join('products', 'vb_store_products_variants.product_id', 'products.id')
                ->join('product_category', 'product_category.product_id', '=', 'products.id')
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->orderByRaw('LENGTH(vb_store_attributes_options.option_name) asc')
                ->orderBy('vb_store_attributes_options.option_name', 'ASC');

            if ($request->filled('search')) {
                $filters_query->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                    ->where('product_groups.group_id', '=', $request->search);
            }

            $filters = $filters_query
                ->select(
                    'vb_store_attributes.*',
                    'vb_store_attributes_options.id as option_id',
                    'vb_store_attributes_options.option_name as option_name',
                    'vb_store_attributes_options.option_name_al as option_name_al',
                )
                ->where('products.product_status', '=', 1)
                ->where(function ($query) use ($category_url) {
                    $category_multiple = Category::where('category_url', '=', $category_url)->get();
                    foreach ($category_multiple as $single_category) {
                        $hirearkia = Category::with(['childrenCategory'])
                            ->where('id', '=', $single_category->id)
                            ->select('categories.*')
                            ->orderBy('categories.category', 'ASC')
                            ->distinct()->get();

                        foreach ($hirearkia as $main_child) {
                            $query->orWhere('product_category.category_id', '=', $main_child->id);
                            foreach ($main_child->childrenCategory as $second_child) {
                                $query->orWhere('product_category.category_id', '=', $second_child->id);
                                foreach ($second_child->childrenCategory as $thid_child) {
                                    $query->orWhere('product_category.category_id', '=', $thid_child->id);
                                }
                            }
                        }
                    }
                })
                ->orderBy('vb_store_attributes_options.option_name', 'ASC')
                ->whereNull('products.deleted_at')
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->distinct()
                ->get()
                ->groupBy('attr_name')
                ->sortDesc();

            $brands_query = Brand::join('products', 'products.brand_id', '=', 'brands.id')
                ->join('product_category', 'product_category.product_id', '=', 'products.id')
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha');

            if ($request->filled('search')) {
                $brands_query->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                    ->where('product_groups.group_id', '=', $request->search);
            }

            $brands = $brands_query->where(function ($query) use ($category_url) {

                $category_multiple = Category::where('category_url', '=', $category_url)->get();

                foreach ($category_multiple as $single_category) {

                    $hirearkia = Category::with(['childrenCategory'])
                        ->where('id', '=', $single_category->id)
                        ->select('categories.*')
                        ->orderBy('categories.category', 'ASC')
                        ->distinct()->get();

                    foreach ($hirearkia as $main_child) {

                        $query->orWhere('product_category.category_id', '=', $main_child->id);

                        foreach ($main_child->childrenCategory as $second_child) {

                            $query->orWhere('product_category.category_id', '=', $second_child->id);

                            foreach ($second_child->childrenCategory as $thid_child) {

                                $query->orWhere('product_category.category_id', '=', $thid_child->id);
                            }
                        }
                    }
                }
            })
                ->where('products.product_status', '=', 1)
                ->whereNull('products.deleted_at')
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->select('brands.*')->distinct()->get();

            $collections_query = Collection::join('product_collections', 'product_collections.collection_id', '=', 'collections.id')
                ->join('products', 'products.id', '=', 'product_collections.product_id')
                ->join('product_category', 'product_category.product_id', '=', 'products.id')
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha');

            if ($request->filled('search')) {
                $collections_query->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                    ->where('product_groups.group_id', '=', $request->search);
            }

            $collections = $collections_query
                ->where(function ($query) use ($category_url) {
                    $category_multiple = Category::where('category_url', '=', $category_url)->get();
                    foreach ($category_multiple as $single_category) {
                        $hirearkia = Category::with(['childrenCategory'])
                            ->where('id', '=', $single_category->id)
                            ->select('categories.*')
                            ->orderBy('categories.category', 'ASC')
                            ->distinct()->get();

                        foreach ($hirearkia as $main_child) {
                            $query->orWhere('product_category.category_id', '=', $main_child->id);
                            foreach ($main_child->childrenCategory as $second_child) {
                                $query->orWhere('product_category.category_id', '=', $second_child->id);
                                foreach ($second_child->childrenCategory as $thid_child) {
                                    $query->orWhere('product_category.category_id', '=', $thid_child->id);
                                }
                            }
                        }
                    }
                })
                ->where('products.product_status', '=', 1)
                ->select('collections.*')
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->whereNull('products.deleted_at')
                ->orderBy('collections.name', 'ASC')
                ->distinct()->get();

            $prices_query = Product::join('product_category', 'product_category.product_id', '=', 'products.id')
                ->where(function ($query) use ($category_url) {
                    $category_multiple = Category::where('category_url', '=', $category_url)->get();
                    foreach ($category_multiple as $single_category) {
                        $hirearkia = Category::with(['childrenCategory'])
                            ->where('id', '=', $single_category->id)
                            ->select('categories.*')
                            ->orderBy('categories.category', 'ASC')
                            ->distinct()->get();
                        foreach ($hirearkia as $main_child) {
                            $query->orWhere('product_category.category_id', '=', $main_child->id);
                            foreach ($main_child->childrenCategory as $second_child) {
                                $query->orWhere('product_category.category_id', '=', $second_child->id);
                                foreach ($second_child->childrenCategory as $thid_child) {
                                    $query->orWhere('product_category.category_id', '=', $thid_child->id);
                                }
                            }
                        }
                    }
                });

            if ($request->filled('search')) {
                $prices_query->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                    ->where('product_groups.group_id', '=', $request->search);
            }

            $prices = $prices_query
                ->where('products.product_status', '=', 1)
                ->whereNull('products.deleted_at')
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->select(
                    DB::raw('IFNULL(Max(products.price), 0) as max_price'),
                    DB::raw('IFNULL(Min(products.price), 0) as min_price')
                )->first();

            $category = Category::where('category_url', '=', $category_url)->first();

            // Example response structure
            return response()->json([
                // 'currency' => $currency, 
                'category' => $category,
                'products' => $products,
                'totalProducts' => $totalProducts,
                'filters' => $filters,
                'collections' => $collections,
                'prices' => $prices,
                'brands' => $brands,
                'group_id' => $request->search
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Category not found'], 404);
        }
    }

    public function searchProducts(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // Implement search logic here
            // Use $request parameters to filter products

            $currency = Currency::where('is_primary', '=', true)->first();

            $products_query = Product::join('product_category', 'product_category.product_id', '=', 'products.id')
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
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha');

            // Filter by category
            if ($request->filled('category_url')) {
                $products_query->where(function ($query) use ($request) {
                    $category_multiple = Category::where('category_url', '=', $request->category_url)->get();
                    $totalCategories = [];
                    foreach ($category_multiple as $single_category) {
                        $hirearkia = Category::with(['childrenCategory'])
                            ->where('id', '=', $single_category->id)
                            ->select('categories.*')
                            ->orderBy('categories.category', 'ASC')
                            ->distinct()->get();

                        foreach ($hirearkia as $main_child) {
                            $query->orWhere('product_category.category_id', '=', $main_child->id);
                            foreach ($main_child->childrenCategory as $second_child) {
                                $query->orWhere('product_category.category_id', '=', $second_child->id);
                                foreach ($second_child->childrenCategory as $thid_child) {
                                    $query->orWhere('product_category.category_id', '=', $thid_child->id);
                                }
                            }
                        }
                    }
                });
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

            $products_query = $products_query
                ->orderBy('products.created_at', 'DESC')
                ->with('productImages')
                ->distinct();

            $totalProducts = $products_query->count();
            $products = $products_query->paginate(30);

            // Example response
            return response()->json([
                'products' => $products,
                'total_products' => $totalProducts,
                'currency' => $currency,
                // Add other necessary data
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'An error occurred while searching'], 500);
        }
    }

    public function showAllCategories(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $categories = Category::orderBy('title', 'ASC')->get();

            return response()->json([
                'categories' => $categories
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'An error occurred while fetching categories'], 500);
        }
    }
}
