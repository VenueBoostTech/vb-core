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
            $currency = Currency::where('is_primary', '=', true)->first();

            $products = Product::where('store_products.product_status', '=', 1)
                ->where('store_products.stock_quantity', '>', 0)
                ->whereNotNull('store_products.currency_alpha')
                ->whereNotNull('store_products.warehouse_alpha')
                ->where(function ($query) use ($request) {
                    $query->where('store_products.product_name', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('store_products.product_short_description', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('store_products.product_sku', 'LIKE', ["%{$request->q}%"])
                        ->orWhere('store_products.article_no', 'LIKE', ["%{$request->q}%"]);
                })
                ->select("store_products.*",
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(store_products.product_name, '$.en')) AS product_name_en"),
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(store_products.product_short_description, '$.en')) AS product_short_description_en"),
                    DB::raw("(SELECT MAX(store_products_variants.sale_price) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as var_sale_price"),
                    DB::raw("(SELECT MIN(store_products_variants.date_sale_start) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as var_date_sale_start"),
                    DB::raw("(SELECT MAX(store_products_variants.date_sale_end) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as var_date_sale_end"),
                    DB::raw("(SELECT MAX(store_products_variants.regular_price) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as max_regular_price"),
                    DB::raw("(SELECT MIN(store_products_variants.regular_price) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as min_regular_price"),
                    DB::raw("(SELECT COUNT(store_products_variants.stock_quantity) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as total_stock_quantity"),
                    DB::raw("(SELECT COUNT(store_products_variants.currency_alpha) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id AND store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha")
                )->orderBy('store_products.created_at', 'DESC')->distinct()->get();

            $products_with_variations = Product::join('store_products_variants', 'store_products_variants.product_id', '=', 'store_products.id')
                // ... [same query as in the original code]
                ->orderBy('store_products.created_at', 'DESC')->distinct()->get();

            $merged = $products->merge($products_with_variations);

            return response()->json([
                'currency' => $currency,
                'products' => $merged
            ]);

        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function searchProductPreview(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $products = Product::where('store_products.product_status', '=', 1)
                ->whereNotNull('store_products.currency_alpha')
                ->whereNotNull('store_products.warehouse_alpha')
                ->where(function ($query) use ($request) {
                    $query->where('store_products.product_name', 'LIKE', ["%{$request->filter_keyword}%"])
                        ->orWhere('store_products.product_short_description', 'LIKE', ["%{$request->filter_keyword}%"])
                        ->orWhere('store_products.product_sku', 'LIKE', ["%{$request->filter_keyword}%"])
                        ->orWhere('store_products.article_no', 'LIKE', ["%{$request->filter_keyword}%"]);
                })
                ->select("store_products.*",
                    DB::raw("(SELECT MAX(store_products_variants.sale_price) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as var_sale_price"),
                    DB::raw("(SELECT MIN(store_products_variants.date_sale_start) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as var_date_sale_start"),
                    DB::raw("(SELECT MAX(store_products_variants.date_sale_end) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as var_date_sale_end"),
                    DB::raw("(SELECT MAX(store_products_variants.regular_price) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as max_regular_price"),
                    DB::raw("(SELECT MIN(store_products_variants.regular_price) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as min_regular_price"),
                    DB::raw("(SELECT COUNT(store_products_variants.stock_quantity) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as total_stock_quantity"),
                    DB::raw("(SELECT COUNT(store_products_variants.currency_alpha) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id AND store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha")
                )->orderBy('store_products.created_at', 'DESC')->distinct()->limit(4)->get();

            $products_with_variations = Product::join('store_products_variants', 'store_products_variants.product_id', '=', 'store_products.id')
                // ... [same query as in the original code]
                ->orderBy('store_products.created_at', 'DESC')->distinct()->limit(4)->get();

            $merged = $products->merge($products_with_variations);

            return response()->json($merged);

        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }
}
