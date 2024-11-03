<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use App\Models\BbSlider;
use App\Models\Brand;
use App\Models\Restaurant;
use App\Models\Product;
use App\Models\BbMainMenu;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\WhitelabelBannerType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HomePageController extends Controller
{
    private function getVenue(): ?Restaurant
    {
        if (!auth()->user()->restaurants->count()) {
            return null;
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return null;
        }

        return auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
    } 

    public function get(Request $request)
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found or user not eligible'], 404);
        }

        $sliders = BbSlider::where('venue_id', $venue->id)->orderBy('slider_order', 'ASC')->get();

        $homepage_brands = Brand::where('venue_id', $venue->id)
                ->where('status_no', '=', '1')
                ->whereNotNull('bybest_id')
                ->orderBy('brand_order_no', 'ASC')->get();

        foreach ($homepage_brands as $home_brand) {
            $data = Product::with(['attribute.option', 'galley'])
                            ->select("products.*",
                                DB::raw("(SELECT MAX(vb_store_products_variants.sale_price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_sale_price"),
                                DB::raw("(SELECT MIN(vb_store_products_variants.date_sale_start) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_start"),
                                DB::raw("(SELECT MAX(vb_store_products_variants.date_sale_end) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as var_date_sale_end"),
                                DB::raw("(SELECT MAX(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as max_regular_price"),
                                DB::raw("(SELECT MIN(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as min_regular_price"),
                                DB::raw("(SELECT COUNT(vb_store_products_variants.currency_alpha) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id AND vb_store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha"))
                            ->where('products.product_status', '=', 1)
                            ->where('products.featured', '=', true)
                            ->where('products.stock_quantity', '>', 0)
                            ->whereNotNull('products.warehouse_alpha')
                            ->whereNotNull('products.currency_alpha')
                            ->where('restaurant_id', $venue->id)
                            ->where('products.brand_id', '=', $home_brand->id)
                            ->orderBy('created_at', 'DESC')
                            ->limit(6)
                            ->get();
            $home_brand['products'] = $data;
        }
            
        return response()->json([
            'homepage_brands' => $homepage_brands,
            'sliders' => $sliders
        ]);
    }

    public function getMenus(Request $request)
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found or user not eligible'], 404);
        }

        $products = Product::with(['attribute.option', 'galley'])
            ->join('product_groups', 'products.id', '=', 'product_groups.product_id')
            ->select("products.*",
                DB::raw("(SELECT MAX(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as max_regular_price"),
                DB::raw("(SELECT MIN(vb_store_products_variants.price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as min_regular_price"),
                DB::raw("(SELECT COUNT(vb_store_products_variants.currency_alpha) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id AND vb_store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha"))
            ->where('products.product_status', '=', 1)
            ->where('products.stock_quantity', '>', 0)
            ->where('product_groups.group_id', '=', '5')
            ->where('products.restaurant_id', $venue->id)
            ->whereNotNull('products.currency_alpha')
            ->orderBy('product_groups.created_at', 'DESC')
            ->limit(4)->get();

        $menus = BbMainMenu::with('menuChildren')->orderBy('order', 'ASC')->get();
 
        return response()->json([
            'menus' => $menus,
            'products' => $products
        ]);
    }
}
