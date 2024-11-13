<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Currency;
use App\Models\BbMainMenu;
use App\Models\BbSlider;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\WhitelabelBanner;
use App\Models\WhitelabelBannerType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebController extends Controller
{
    public function appServiceProvider(): JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // Fetch all required data
        $data = [
            'top_bar' => $this->getTopBarData($venue->id),
            'header' => $this->getHeaderData(),
            'brands' => $this->getBrandsData(),
            'slider' => $this->getSliderData(),
        ];

        return response()->json($data);
    }

    private function getTopBarData($venueId)
    {
        return WhitelabelBanner::where('type_id', '=', 1)->where('status', '=', 1)->where('venue_id', '=', $venueId)->get();
    }

    private function getHeaderData()
    {
        $products = Product::with(['attribute.option', 'productImages'])
            ->join('product_groups', 'products.id', '=', 'product_groups.product_id')
            ->select("products.*",
                DB::raw("(SELECT MAX(vb_store_products_variants.regular_price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as max_regular_price"),
                DB::raw("(SELECT MIN(vb_store_products_variants.regular_price) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id) as min_regular_price"),
                DB::raw("(SELECT COUNT(vb_store_products_variants.currency_alpha) FROM vb_store_products_variants WHERE vb_store_products_variants.product_id = products.id AND vb_store_products_variants.currency_alpha IS NOT NULL) as count_currency_alpha"))
            ->where('products.product_status', '=', 1)
            ->where('products.stock_quantity', '>', 0)
            ->where('product_groups.group_id', '=', '5')
            ->whereNotNull('products.currency_alpha')
            ->orderBy('product_groups.created_at', 'DESC')
            ->limit(4)->get();

        return [
            'menu' => BBMainMenu::with('menuChildren')->orderBy('order', 'ASC')->get(),
            'products' => $products,
            'currencies' => Currency::all()
        ];
    }

    private function getBrandsData()
    {
        return Brand::where('brands.status_no', '=', '1')
            ->orderByRaw('LENGTH(brands.brand_order_no) asc')
            ->orderBy('brands.brand_order_no', 'ASC')
            ->get();
    }

    private function getSliderData()
    {
        return BbSlider::orderBy('slider_order', 'ASC')->get();
    }


}
