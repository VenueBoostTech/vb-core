<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Collection;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\Product;
use App\Models\VbStoreAttribute;
use Illuminate\Support\Facades\DB;

class BbGroupsController extends Controller
{
    public function groupProducts(Request $request, $group_id): \Illuminate\Http\JsonResponse
    {
        try {
            $group = Group::whereBybestId($group_id)->first();
            if (!@$group) {
                return response()->json(['error' => 'Group not found'], 404);
            }

            // Get product from database
            $products = Product::with('productImages')
                ->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                ->join('groups', 'groups.id', '=', 'product_groups.group_id')
                ->where('groups.bybest_id', '=', $group_id);

            // Search product by brands
            if ($request->filled('brand_id') && is_array($request->brand_id) && count($request->brand_id) != 0) {
                $products = $products->whereIn('products.brand_id', $request->brand_id);
            }

            // if ($request->filled('brand_id') && is_array($request->brand_id) && count($request->brand_id) != 0) {
            //     $products = $products->join('brands', 'brands.id', '=', 'products.brand_id')
            //         ->whereIn('brands.bybest_id', $request->brand_id);
            // }

            // Search product by collections
            if ($request->filled('collection_id') && is_array($request->collection_id) && count($request->collection_id) != 0) {
                $products = $products->join('product_collections', 'product_collections.product_id', '=', 'products.id')
                    ->whereIn('product_collections.collection_id', $request->collection_id);
            }

            // if ($request->filled('collection_id') && is_array($request->collection_id) && count($request->collection_id) != 0) {
            //     $products = $products->join('product_collections', 'product_collections.product_id', '=', 'products.id')
            //         ->join('collections', 'collections.id', '=', 'product_collections.collection_id')
            //         ->whereIn('collections.bybest_id', '=', $request->collection_id);
            // }

            // Search product by minimum price
            if ($request->filled('min_price_search')) {
                $products = $products->where('products.price', '>=', $request->min_price_search);
            }

            // Search product by maximum price
            if ($request->filled('max_price_search')) {
                $products = $products->where('products.price', '<=', $request->max_price_search);
            }

            if ($request->filled('attribute_id') && is_array($request->attribute_id) && count($request->attribute_id) != 0) {
                $products = $products->join('vb_store_product_attributes', 'vb_store_product_attributes.product_id', '=', 'products.id');
                $products = $products->whereIn('vb_store_product_attributes.attribute_id', $request->attribute_id);
            }

            $products = $products->distinct('products.id')->paginate(30);

            $filters = VbStoreAttribute::join('vb_store_attributes_options', 'vb_store_attributes_options.attribute_id', '=', 'vb_store_attributes.id')
                ->join('vb_store_product_variant_attributes', 'vb_store_product_variant_attributes.attribute_id', '=', 'vb_store_attributes_options.id')
                ->join('vb_store_products_variants', 'vb_store_product_variant_attributes.variant_id', 'vb_store_products_variants.id')
                ->join('products', 'vb_store_products_variants.product_id', 'products.id')
                ->join('product_category', 'product_category.product_id', '=', 'products.id')
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->orderByRaw('LENGTH(vb_store_attributes_options.option_name) asc')
                ->orderBy('vb_store_attributes_options.option_name', 'ASC')
                ->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                ->join('groups', 'groups.id', '=', 'product_groups.group_id')
                ->where('groups.bybest_id', '=', $group_id)
                ->select(
                    'vb_store_attributes.*',
                    'vb_store_attributes_options.id as option_id',
                    'vb_store_attributes_options.option_name as option_name',
                    'vb_store_attributes_options.option_name_al as option_name_al',
                )
                ->where('products.product_status', '=', 1)
                ->orderBy('vb_store_attributes_options.option_name', 'ASC')
                ->whereNull('products.deleted_at')
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->distinct()
                ->get()
                ->groupBy('attr_name')
                ->sortDesc();

            $brands = Brand::join('products', 'products.brand_id', '=', 'brands.id')
                ->join('product_category', 'product_category.product_id', '=', 'products.id')
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                ->join('groups', 'groups.id', '=', 'product_groups.group_id')
                ->where('groups.bybest_id', '=', $group_id)
                ->where('products.product_status', '=', 1)
                ->whereNull('products.deleted_at')
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->select('brands.*')->distinct()->get();

            $collections = Collection::join('product_collections', 'product_collections.collection_id', '=', 'collections.id')
                ->join('products', 'products.id', '=', 'product_collections.product_id')
                ->join('product_category', 'product_category.product_id', '=', 'products.id')
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                ->join('groups', 'groups.id', '=', 'product_groups.group_id')
                ->where('groups.bybest_id', '=', $group_id)
                ->where('products.product_status', '=', 1)
                ->select('collections.*')
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->whereNull('products.deleted_at')
                ->orderBy('collections.name', 'ASC')
                ->distinct()->get();

            $prices = Product::join('product_category', 'product_category.product_id', '=', 'products.id')
                ->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                ->join('groups', 'groups.id', '=', 'product_groups.group_id')
                ->where('groups.bybest_id', '=', $group_id)
                ->where('products.product_status', '=', 1)
                ->whereNull('products.deleted_at')
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->select(
                    DB::raw('IFNULL(Max(products.price), 0) as max_price'),
                    DB::raw('IFNULL(Min(products.price), 0) as min_price')
                )->first();

            return response()->json([
                'group' => $group,
                'products' => $products,
                'filters' => $filters,
                'brands' => $brands,
                'collections' => $collections,
                'prices' => $prices,
                // Add other necessary data
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Something went wrong.'], 404);
        }
    }
}
