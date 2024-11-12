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
            $product = Product::select(
                "products.*",
                DB::raw("(SELECT MAX(vb_store_products_variants.sale_price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_sale_price"),
                DB::raw("(SELECT MIN(vb_store_products_variants.date_sale_start) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_start"),
                DB::raw("(SELECT MAX(vb_store_products_variants.date_sale_end) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_end"),
                DB::raw("(SELECT MAX(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as max_regular_price"),
                DB::raw("(SELECT MIN(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as min_regular_price"),
                DB::raw("(SELECT COUNT(vb_store_products_variants.currency_alpha) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id AND vb_store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha")
            )
                ->where('id', '=', $product_id)
                ->where('product_url', '=', $product_url)
                ->with(['attribute.option', 'productImages', 'postal'])
                ->first();

            if (!$product) {
                return response()->json(['error' => 'Not found product'], 404);
            }


            // $currency = Currency::where('is_primary', '=', true)->first();
            $brand = Brand::where('id', '=', $product->brand_id)->first();
            $bb_memebers = Member::first();

            $related_products = Product
                ::where('products.title', 'LIKE', '%' . substr($product->title, 0, strlen($product->title) / 2) . '%')
                ->select(
                    "products.*",
                    DB::raw("(SELECT MAX(vb_store_products_variants.sale_price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_sale_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.date_sale_start) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_start"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.date_sale_end) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_end"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as max_regular_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as min_regular_price"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.currency_alpha) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id AND vb_store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha")
                )
                ->where('products.brand_id', '=', $brand->id)
                ->where('products.id', '<>', $product->id)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.warehouse_alpha')
                ->whereNotNull('products.currency_alpha')
                ->where('products.product_status', '=', 1)
                ->with(['attribute.option', 'productImages'])
                ->limit(4)->get();

            $related_products_cros = Product
                ::where('products.id', '<>', $product->id)
                ->where('products.brand_id', '=', $brand->id)
                ->where('products.product_status', '=', 1)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.warehouse_alpha')
                ->whereNotNull('products.currency_alpha')
                ->select(
                    "products.*",
                    DB::raw("(SELECT MAX(vb_store_products_variants.sale_price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_sale_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.date_sale_start) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_start"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.date_sale_end) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_end"),
                    DB::raw("(SELECT MAX(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as max_regular_price"),
                    DB::raw("(SELECT MIN(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as min_regular_price"),
                    DB::raw("(SELECT COUNT(vb_store_products_variants.currency_alpha) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id AND vb_store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha")
                )
                ->with(['attribute.option', 'productImages'])
                ->limit(4 - count($related_products))->get();

            $related_products = $related_products->concat($related_products_cros);

            $atributes = DB::table('store_product_atributes')->join('store_attributes_options', 'store_attributes_options.id', '=', 'store_product_atributes.attribute_id')
                ->join('store_attributes', 'store_attributes.id', '=', 'store_attributes_options.attribute_id')
                ->select(
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(store_attributes.attr_name, '$." . App::getLocale() . "')) AS attr_name"),
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(store_attributes_options.option_name, '$." . App::getLocale() . "')) AS option_name"),
                    'store_attributes_options.order_id'
                )
                ->orderBy('store_attributes_options.order_id', 'asc')
                ->where('store_product_atributes.product_id', '=', $product->id)
                ->get();

            $wish_list_products = app('wishlist')->getContent();
            $payment_methods = PaymentMothod::where('status_id', '=', 1)->orderBy('id', 'ASC')->get();
            $countries = Country::all();
            $cities = City::all();


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
                    } catch (\Throwable $th) {
                    }

                    if (intval($variant_attr->attribute_id) != $selected_key) {
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
