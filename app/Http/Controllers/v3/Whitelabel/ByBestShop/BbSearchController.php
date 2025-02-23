<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Currency;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BbSearchController extends Controller
{
    public function searchPage(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $per_page = @(int)$request->per_page > 0 ? (int)$request->per_page : 30;
            $page = @(int)$request->page > 0 ? (int)$request->page : 1;

            $currency = Currency::where('is_primary', '=', true)->first();

            $products = Product::where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->whereNotNull('products.warehouse_alpha')
                ->where(function ($query) use ($request) {
                    $query->where('products.title', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('products.short_description', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('products.product_sku', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('products.article_no', 'LIKE', ["%{$request->q}%"]);
                })
                ->select(
                    "products.*",
                    // DB::raw("JSON_UNQUOTE(JSON_EXTRACT(products.title, '$.en')) AS product_name_en"),
                    // DB::raw("JSON_UNQUOTE(JSON_EXTRACT(products.short_description, '$.en')) AS product_short_description_en"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.sale_price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_sale_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.date_sale_start) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_start"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.date_sale_end) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_end"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as max_regular_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as min_regular_price"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.stock_quantity) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as total_stock_quantity"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.currency_alpha) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id AND vb_store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha")
                )->orderBy('products.created_at', 'DESC')->distinct()->get();

            $products_with_variations = Product::join('vb_store_products_variants', 'vb_store_products_variants.product_id', '=', 'products.id')
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->whereNotNull('products.warehouse_alpha')
                ->where(function ($query) use ($request) {
                    $query->where('products.title', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('products.short_description', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('products.product_sku', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('products.article_no', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('vb_store_products_variants.variation_sku', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('vb_store_products_variants.article_no', 'LIKE', ["%{$request->q}%"]);
                })
                ->select(
                    "products.*",
                    // DB::raw("JSON_UNQUOTE(JSON_EXTRACT(products.title, '$.en')) AS product_name_en"),
                    // DB::raw("JSON_UNQUOTE(JSON_EXTRACT(products.short_description, '$.en')) AS product_short_description_en"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.sale_price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_sale_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.date_sale_start) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_start"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.date_sale_end) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_end"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as max_regular_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as min_regular_price"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.stock_quantity) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as total_stock_quantity"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.currency_alpha) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id AND vb_store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha")
                )->orderBy('products.created_at', 'DESC')->distinct()->get();

            $total_products = $products->count() + $products_with_variations->count();
            $merged = $products->merge($products_with_variations)->skip(($page - 1) * $per_page)->take($per_page);
            $last_page = ceil($total_products / $per_page);

            return response()->json([
                'currency' => $currency,
                'products' => $merged,
                'current_page' => $page,
                'last_page' => $last_page
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function searchProductPreview(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $products = Product::where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->whereNotNull('products.warehouse_alpha')
                ->where(function ($query) use ($request) {
                    $query->where('products.title', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('products.short_description', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('products.product_sku', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('products.article_no', 'LIKE', ["%{$request->q}%"]);
                })
                ->select(
                    "products.*",
                    // DB::raw("JSON_UNQUOTE(JSON_EXTRACT(products.title, '$.en')) AS product_name_en"),
                    // DB::raw("JSON_UNQUOTE(JSON_EXTRACT(products.short_description, '$.en')) AS product_short_description_en"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.sale_price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_sale_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.date_sale_start) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_start"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.date_sale_end) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_end"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as max_regular_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as min_regular_price"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.stock_quantity) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as total_stock_quantity"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.currency_alpha) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id AND vb_store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha")
                )->orderBy('products.created_at', 'DESC')->distinct()->take(4)->get();

            $products_with_variations = Product::join('vb_store_products_variants', 'vb_store_products_variants.product_id', '=', 'products.id')
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.currency_alpha')
                ->whereNotNull('products.warehouse_alpha')
                ->where(function ($query) use ($request) {
                    $query->where('products.title', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('products.short_description', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('products.product_sku', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('products.article_no', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('vb_store_products_variants.variation_sku', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('vb_store_products_variants.article_no', 'LIKE', ["%{$request->q}%"]);
                })
                ->select(
                    "products.*",
                    // DB::raw("JSON_UNQUOTE(JSON_EXTRACT(products.title, '$.en')) AS product_name_en"),
                    // DB::raw("JSON_UNQUOTE(JSON_EXTRACT(products.short_description, '$.en')) AS product_short_description_en"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.sale_price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_sale_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.date_sale_start) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_start"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.date_sale_end) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_end"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as max_regular_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as min_regular_price"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.stock_quantity) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as total_stock_quantity"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.currency_alpha) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id AND vb_store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha")
                )->orderBy('products.created_at', 'DESC')->distinct()->take(4)->get();

            $merged = $products->merge($products_with_variations)->take(4);

            return response()->json($merged);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }
}
