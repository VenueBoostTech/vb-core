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
            $group = Group::findOrFail($group_id);
            $products = Product::with('productImages')->whereHas('groups', function ($query) use ($group_id) {
                $query->where('group_id', $group_id);
            })->paginate(20);

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
                ->where('product_groups.group_id', '=', $group_id)
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
                ->where('product_groups.group_id', '=', $group_id)
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
                ->where('product_groups.group_id', '=', $group_id)
                ->where('products.product_status', '=', 1)
                ->select('collections.*')
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->whereNull('products.deleted_at')
                ->orderBy('collections.name', 'ASC')
                ->distinct()->get();

            $prices = Product::join('product_category', 'product_category.product_id', '=', 'products.id')
                ->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                ->where('product_groups.group_id', '=', $group_id)
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
            return response()->json(['error' => 'Group not found'], 404);
        }
    }
}
