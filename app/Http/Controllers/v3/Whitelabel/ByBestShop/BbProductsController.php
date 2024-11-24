<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Currency;
use App\Models\Product;
use App\Models\VbStoreProductVariant;
use App\Models\Brand;
use App\Models\City;
use App\Models\Country;
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
                ->where('bybest_id', '=', $product_id)
                ->where('product_url', '=', $product_url)
                ->with(['attribute.option', 'productImages', 'postal'])
                ->first();

            if (!$product) {
                return response()->json(['error' => 'Not found product'], 404);
            }


            $currency = Currency::where('is_primary', '=', true)->first();
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
                );

            if (@$brand) {
                $related_products = $related_products->where('products.brand_id', '=', $brand->id);
            }

            $related_products = $related_products
                ->where('products.id', '<>', $product->id)
                ->where('products.stock_quantity', '>', 0)
                ->whereNotNull('products.warehouse_alpha')
                ->whereNotNull('products.currency_alpha')
                ->where('products.product_status', '=', 1)
                ->with(['attribute.option', 'productImages'])
                ->limit(4)->get();

            $related_products_cros = Product::where('products.id', '<>', $product->id);

            if (@$brand) {
                $related_products_cros = $related_products_cros->where('products.brand_id', '=', $brand->id);
            }

            $related_products_cros = $related_products_cros->where('products.product_status', '=', 1)
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

            $atributes = DB::table('vb_store_product_attributes')->join('vb_store_attributes_options', 'vb_store_attributes_options.id', '=', 'vb_store_product_attributes.attribute_id')
            ->join('vb_store_attributes', 'vb_store_attributes.id', '=', 'vb_store_attributes_options.attribute_id')
            ->select(
                // DB::raw("JSON_UNQUOTE(JSON_EXTRACT(vb_store_attributes.attr_name, '$." . App::getLocale() . "')) AS attr_name"),
                // DB::raw("JSON_UNQUOTE(JSON_EXTRACT(vb_store_attributes_options.option_name, '$." . App::getLocale() . "')) AS option_name"),
                'vb_store_attributes_options.order_id'
            )
                ->orderBy('vb_store_attributes_options.order_id', 'asc')
                ->where('vb_store_product_attributes.product_id', '=', $product->id)
                ->get();

            // $wish_list_products = app('wishlist')->getContent();
            // $payment_methods = PaymentMothod::where('status_id', '=', 1)->orderBy('id', 'ASC')->get();
            // Translation mappings
            $countryTranslations = [
                'Albania' => 'Shqipëria',
                'Kosovo' => 'Kosova',
                'North Macedonia' => 'Maqedonia e Veriut',
                'Unspecified' => 'E papërcaktuar'
            ];

            $stateTranslations = [
                // Albania
                'Tirana' => 'Tiranë',
                'Durres' => 'Durrës',
                'Vlore' => 'Vlorë',
                'Elbasan' => 'Elbasan',
                'Fier' => 'Fier',
                'Korce' => 'Korçë',
                'Shkoder' => 'Shkodër',
                'Berat' => 'Berat',
                'Lezhe' => 'Lezhë',
                'Diber' => 'Dibër',
                'Kukes' => 'Kukës',
                'Gjirokaster' => 'Gjirokastër',
                // Kosovo
                'Pristina' => 'Prishtinë',
                'Prizren' => 'Prizren',
                'Peja' => 'Pejë',
                'Gjakova' => 'Gjakovë',
                'Mitrovica' => 'Mitrovicë',
                'Gjilan' => 'Gjilan',
                'Ferizaj' => 'Ferizaj',
                // North Macedonia
                'Skopje' => 'Shkup',
                'Vardar' => 'Vardari',
                'East' => 'Lindja',
                'Southwest' => 'Jugperëndimi',
                'Southeast' => 'Juglindje',
                'Pelagonia' => 'Pellagonia',
                'Polog' => 'Pollogu',
                'Northeast' => 'Verilindja',
                'Unspecified' => 'E papërcaktuar'
            ];

            $cityTranslations = [
                // Albania - Tirana Region
                'Tirana' => 'Tiranë',
                'Kavaja' => 'Kavajë',
                'Vore' => 'Vorë',
                'Kamez' => 'Kamëz',
                'Rrogozhine' => 'Rrogozhinë',

                // Albania - Durres Region
                'Durres' => 'Durrës',
                'Shijak' => 'Shijak',
                'Kruje' => 'Krujë',
                'Manez' => 'Manëz',

                // Albania - Vlore Region
                'Vlore' => 'Vlorë',
                'Himare' => 'Himarë',
                'Selenice' => 'Selenicë',
                'Orikum' => 'Orikum',
                'Dhermi' => 'Dhërmi',

                // Albania - Elbasan Region
                'Elbasan' => 'Elbasan',
                'Cerrik' => 'Cërrik',
                'Belsh' => 'Belsh',
                'Peqin' => 'Peqin',
                'Gramsh' => 'Gramsh',
                'Librazhd' => 'Librazhd',

                // Albania - Fier Region
                'Fier' => 'Fier',
                'Patos' => 'Patos',
                'Roskovec' => 'Roskovec',
                'Mallakaster' => 'Mallakastër',
                'Lushnje' => 'Lushnjë',
                'Divjake' => 'Divjakë',

                // Albania - Other Regions
                'Shkoder' => 'Shkodër',
                'Lezhe' => 'Lezhë',
                'Korce' => 'Korçë',
                'Berat' => 'Berat',
                'Kucove' => 'Kuçovë',
                'Gjirokaster' => 'Gjirokastër',
                'Permet' => 'Përmet',
                'Tepelene' => 'Tepelenë',
                'Sarande' => 'Sarandë',
                'Delvine' => 'Delvinë',
                'Konispol' => 'Konispol',
                'Kukes' => 'Kukës',
                'Has' => 'Has',
                'Tropoje' => 'Tropojë',
                'Peshkopi' => 'Peshkopi',
                'Bulqize' => 'Bulqizë',
                'Mat' => 'Mat',

                // Kosovo - Pristina Region
                'Pristina' => 'Prishtinë',
                'Podujevo' => 'Podujevë',
                'Obilic' => 'Obiliq',
                'Lipjan' => 'Lipjan',
                'Gllogovc' => 'Gllogoc',
                'Gracanice' => 'Graçanicë',
                'Fushe Kosove' => 'Fushë Kosovë',

                // Kosovo - Prizren Region
                'Prizren' => 'Prizren',
                'Dragash' => 'Dragash',
                'Suva Reka' => 'Suharekë',
                'Mamusha' => 'Mamushë',

                // Kosovo - Peja Region
                'Peja' => 'Pejë',
                'Decan' => 'Deçan',
                'Klina' => 'Klinë',
                'Istog' => 'Istog',
                'Junik' => 'Junik',

                // Kosovo - Mitrovica Region
                'Mitrovica' => 'Mitrovicë',
                'Skenderaj' => 'Skënderaj',
                'Vushtrri' => 'Vushtrri',
                'Zubin Potok' => 'Zubin Potok',
                'Zvecan' => 'Zveçan',

                // Kosovo - Gjakova Region
                'Gjakova' => 'Gjakovë',
                'Rahovec' => 'Rahovec',

                // Kosovo - Gjilan Region
                'Gjilan' => 'Gjilan',
                'Kamenica' => 'Kamenicë',
                'Vitia' => 'Viti',
                'Novoberdo' => 'Novobërdë',
                'Ranillug' => 'Ranillug',
                'Partesh' => 'Partesh',
                'Kllokot' => 'Kllokot',

                // Kosovo - Ferizaj Region
                'Ferizaj' => 'Ferizaj',
                'Kacanik' => 'Kaçanik',
                'Shtime' => 'Shtime',
                'Shterpce' => 'Shtërpcë',
                'Elez Han' => 'Hani i Elezit',

                // North Macedonia - Skopje Region
                'Skopje' => 'Shkup',
                'Aerodrom' => 'Aerodrom',
                'Butel' => 'Butel',
                'Cair' => 'Çair',
                'Centar' => 'Qendër',
                'Gazi Baba' => 'Gazi Babë',
                'Gjorce Petrov' => 'Gjorçe Petrov',
                'Karpos' => 'Karposh',
                'Kisela Voda' => 'Kisella Vodë',
                'Saraj' => 'Saraj',
                'Suto Orizari' => 'Shuto Orizarë',

                // North Macedonia - Polog Region
                'Tetovo' => 'Tetovë',
                'Gostivar' => 'Gostivar',
                'Brvenica' => 'Bërvenicë',
                'Bogovinje' => 'Bogovinë',
                'Zelino' => 'Zhelinë',
                'Jegunovce' => 'Jegunovcë',
                'Mavrovo' => 'Mavrovë',
                'Tearce' => 'Tearcë',
                'Vrapciste' => 'Vrapçishtë',

                // North Macedonia - Northeast Region
                'Kumanovo' => 'Kumanovë',
                'Kriva Palanka' => 'Kriva Pallankë',
                'Kratovo' => 'Kratovë',
                'Lipkovo' => 'Lipkovë',
                'Rankovce' => 'Rankovcë',
                'Staro Nagoricane' => 'Nagoriçan i Vjetër',

                // North Macedonia - Southwest Region
                'Ohrid' => 'Ohër',
                'Struga' => 'Strugë',
                'Debar' => 'Dibër',
                'Vevcani' => 'Vevçani',
                'Plasnica' => 'Pllasnicë',
                'Centar Zupa' => 'Qendra Zhupë',

                // Default
                'Unspecified' => 'E papërcaktuar'
            ];

            $countries = Country::whereIn('code', ['AL', 'XK', 'MK', 'XX'])
                ->with(['states' => function ($query) {
                    $query->with('cities');
                }])
                ->get()
                ->map(function ($country) use ($countryTranslations, $stateTranslations, $cityTranslations) {
                    return [
                        'id' => $country->id,
                        'code' => $country->code,
                        'names' => [
                            'en' => $country->name,
                            'sq' => $countryTranslations[$country->name] ?? $country->name
                        ],
                        'states' => $country->states->map(function ($state) use ($stateTranslations, $cityTranslations) {
                            return [
                                'id' => $state->id,
                                'names' => [
                                    'en' => $state->name,
                                    'sq' => $stateTranslations[$state->name] ?? $state->name
                                ],
                                'cities' => $state->cities->map(function ($city) use ($cityTranslations) {
                                    return [
                                        'id' => $city->id,
                                        'names' => [
                                            'en' => $city->name,
                                            'sq' => $cityTranslations[$city->name] ?? $city->name
                                        ]
                                    ];
                                })
                            ];
                        })
                    ];
                });


            $response_data = [
                'currency' => $currency,
                'product' => $product,
                'brand' => $brand,
                'bb_members' => $bb_memebers,
                'countries' => $countries,
                'atributes' => $atributes,
                'related_products' => $related_products
                //'cities' => $cities,
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
