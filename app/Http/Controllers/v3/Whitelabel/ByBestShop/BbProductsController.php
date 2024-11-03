<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Currency;
use App\Models\Product;
use App\Models\VbStoreProductVariant;
use App\Models\Brand;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use DateTime;

class BbProductsController extends Controller
{

    public function singleProduct($product_id, $product_url): \Illuminate\Http\JsonResponse
    {
        try {
            $currency = Currency::where('is_primary', '=', true)->first();

            $product = Product::select("store_products.*",
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(store_products.product_name, '$.en')) AS product_name_en"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(store_products.product_short_description, '$.en')) AS product_short_description_en"),
                DB::raw("(SELECT MAX(store_products_variants.sale_price) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as var_sale_price"),
                DB::raw("(SELECT MIN(store_products_variants.date_sale_start) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as var_date_sale_start"),
                DB::raw("(SELECT MAX(store_products_variants.date_sale_end) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as var_date_sale_end"),
                DB::raw("(SELECT MAX(store_products_variants.regular_price) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as max_regular_price"),
                DB::raw("(SELECT MIN(store_products_variants.regular_price) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id) as min_regular_price"),
                DB::raw("(SELECT COUNT(store_products_variants.currency_alpha) FROM store_products_variants WHERE store_products_variants.product_id = store_products.id AND store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha"))
                ->where('id', '=', $product_id)
                ->where('product_url', '=', $product_url)
                ->with(['attribute.option', 'galley', 'postal'])
                ->first();

            if (!$product) {
                return response()->json(['error' => 'Product not found'], 404);
            }

            $brand = Brand::where('id', '=', $product->brand_id)->first();
            $bb_members = Member::where('status_id', '=', 1)->first();

            // ... (keep other queries as needed, adjusting for API use)

            $response_data = [
                'currency' => $currency,
                'product' => $product,
                'brand' => $brand,
                'bb_members' => $bb_members,
                // Add other necessary data
            ];

            return response()->json($response_data);

        } catch (\Throwable $th) {
            return response()->json(['error' => 'An error occurred'], 500);
        }
    }

    public function changeProductVariant(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $currency = Currency::where('is_primary', '=', true)->first();

            $variants = VbStoreProductVariant::where('product_id', '=', $request->product_id)
                ->select('store_products_variants.*')
                ->with('attribues')
                ->get();

            $selected = array_reverse(collect($request->options_selected)->sortDesc()->toArray());

            foreach ($variants as $variant) {
                $is_matched = true;

                foreach ($variant->attribues as $key => $variant_attr) {
                    $selected_key = 0;
                    try {
                        $selected_key = intval($selected[$key]);
                    } catch(\Throwable $th) {}

                    if (intval($variant_attr->atribute_id) != $selected_key) {
                        $is_matched = false;
                    }
                }

                if ($is_matched) {
                    // Prepare the response data
                    $response_data = [
                        'variation_id' => $variant->id,
                        'image_url' => env('BACKEND_DOMAIN') . '/storage/products/' . $variant->variation_image,
                        'variation_data' => $variant,
                        'price_info' => $this->getPriceInfo($variant, $currency)
                    ];

                    return response()->json($response_data);
                }
            }

            return response()->json(['error' => 'Variant not found'], 404);

        } catch (\Throwable $th) {
            return response()->json(['error' => 'An error occurred'], 500);
        }
    }

    private function getPriceInfo($variant, $currency)
    {
        // ... (implement price calculation logic here)
        // Return an array with price information
    }
}
