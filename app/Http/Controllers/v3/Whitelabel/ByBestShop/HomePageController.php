<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use App\Models\BbSlider;
use App\Models\Brand;
use App\Models\Restaurant;
use App\Models\Product;
use App\Models\BbMainMenu;
use App\Models\Group;
use Illuminate\Support\Facades\Cache;
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
            $data = Product::with(['attribute.option', 'productImages'])
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

//    public function get(Request $request)
//    {
//        $apiCallVenueAppKey = request()->get('venue_app_key');
//
//        // Get venue info
//        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
//
//        if (!$venue) {
//            return response()->json(['error' => 'Venue not found or user not eligible'], 404);
//        }
//
//        // Get sliders
//        $sliders = BbSlider::where('venue_id', $venue->id)
//            ->orderBy('slider_order', 'ASC')
//            ->get();
//
//        // Get homepage brands
//        $homepage_brands = Brand::where('venue_id', $venue->id)
//            ->where('status_no', '=', '1')
//            ->whereNotNull('bybest_id')
//            ->orderBy('brand_order_no', 'ASC')
//            ->get();
//
//        // Get all product data for the selected brands
//        $brand_ids = $homepage_brands->pluck('id');
//
//        // Use join to avoid subqueries for better performance
//        $products = Product::with(['attribute.option', 'productImages'])
//            ->select(
//                'products.*',
//                DB::raw('MAX(vb_store_products_variants.sale_price) AS var_sale_price'),
//                DB::raw('MIN(vb_store_products_variants.date_sale_start) AS var_date_sale_start'),
//                DB::raw('MAX(vb_store_products_variants.date_sale_end) AS var_date_sale_end'),
//                DB::raw('MAX(vb_store_products_variants.price) AS max_regular_price'),
//                DB::raw('MIN(vb_store_products_variants.price) AS min_regular_price'),
//                DB::raw('COUNT(vb_store_products_variants.currency_alpha) AS count_currency_alpha')
//            )
//            ->join('vb_store_products_variants', 'products.id', '=', 'vb_store_products_variants.product_id')
//            ->whereIn('products.brand_id', $brand_ids)
//            ->where('products.product_status', '=', 1)
//            ->where('products.featured', '=', true)
//            ->where('products.stock_quantity', '>', 0)
//            ->whereNotNull('products.warehouse_alpha')
//            ->whereNotNull('products.currency_alpha')
//            ->where('products.restaurant_id', $venue->id)
//            ->groupBy(
//                'products.id',
//                'products.brand_id',
//                'products.restaurant_id',
//                'products.product_status',
//                'products.stock_quantity',
//                'products.featured',
//                'products.warehouse_alpha',
//                'products.currency_alpha'
//            )
//            ->orderBy('products.created_at', 'DESC')
//            ->limit(6)
//            ->get()
//            ->groupBy('brand_id'); // Group by brand_id
//
//        // Assign products to their respective brands
//        foreach ($homepage_brands as $home_brand) {
//            $home_brand->products = isset($products[$home_brand->id]) ? $products[$home_brand->id] : collect();
//        }
//
//        return response()->json([
//            'homepage_brands' => $homepage_brands,
//            'sliders' => $sliders
//        ]);
//    }


    public function getMenus(Request $request)
    {
        $apiCallVenueAppKey = $request->get('venue_app_key');

        // Marrim të dhënat e venues bazuar në `app_key`
        $venue = Cache::remember("venue:{$apiCallVenueAppKey}", 3600, function () use ($apiCallVenueAppKey) {
            return Restaurant::select('id', 'app_key')->where('app_key', $apiCallVenueAppKey)->first();
        });

        if (!$venue) {
            return response()->json(['error' => 'Venue not found or user not eligible'], 404);
        }

        // Marrim menus të cache-uar
        $cacheKey = "menus:{$venue->id}";
        $menus = Cache::remember($cacheKey, 500, function () use ($venue) {
            return BbMainMenu::with(['menuChildren', 'group:id,bybest_id'])
                // select all columns from `bb_main_menus` table
                ->select('*')
                ->where('venue_id', $venue->id)
                ->orderBy('order', 'ASC')
                ->get();
        });

        $group = Group::where('group_name', 'SALE')->first();
        if (!$group) {
            return response()->json(['message' => 'Group not found.']);
        }

        $products = Product::with(['attribute.option', 'productImages'])
            ->join('product_groups', 'products.id', '=', 'product_groups.product_id')
            ->join('vb_store_products_variants as variants', 'products.id', '=', 'variants.product_id')
            ->select("products.*",
                DB::raw("MAX(variants.price) as max_regular_price"),
                DB::raw("MIN(variants.price) as min_regular_price"),
                DB::raw("COUNT(variants.currency_alpha) as count_currency_alpha")
            )
            ->where('products.product_status', '=', 1)
            ->where('products.stock_quantity', '>', 0)
            ->where('product_groups.group_id', '=', $group->id)
            ->where('products.restaurant_id', $venue->id)
            ->whereNotNull('products.currency_alpha')
            ->groupBy('products.id', 'product_groups.created_at') // Add created_at to GROUP BY
            ->orderBy('product_groups.created_at', 'DESC')
            ->limit(4)
            ->get();

        return response()->json([
            'menus' => $menus,
            'products' => $products
        ]);
    }


}
