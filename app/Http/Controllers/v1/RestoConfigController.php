<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\PricingPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RestoConfigController extends Controller
{
    public function getRegisterConfig()
    {
        try {
            $cuisine_types = DB::table('cuisine_types')->orderBy('name')->get();
            $amenities = DB::table('amenities')->orderBy('name')->get();
            $venue_types = DB::table('venue_types')->orderBy('name')->get();
            $states = DB::table('states')->orderBy('name')->get();
            $cities = DB::table('cities')->orderBy('name')->get();
            $countries = DB::table('countries')->orderBy('name')->get();

            // Group states by country_id
            $groupedStates = $states->groupBy('country_id');
            // Group cities by state_id
            $groupedCities = $cities->groupBy('states_id');

            // Nest states within countries
            $countries->transform(function ($country) use ($groupedStates, $groupedCities) {
                $countryStates = $groupedStates->get($country->id) ?? collect([]);
                // Nest cities within states
                $countryStates->transform(function ($state) use ($groupedCities) {
                    $state->cities = $groupedCities->get($state->id) ?? collect([]);
                    return $state;
                });

                $country->states = $countryStates;
                return $country;
            });

            return [
                'cuisine_types' => $cuisine_types,
                'amenities' => $amenities,
                'venue_types' => $venue_types,
                'states' => $states,
                'cities' => $cities,
                'countries' => $countries,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log ($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    public function getPaymentConfig()
    {
        try {
            $pricing_plans = DB::table('pricing_plans')->orderBy('monthly_cost')->get();
            $addons = DB::table('addons')->orderBy('name')->get();

            return [
                'pricing_plans' => $pricing_plans,
                'addons' => $addons,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log ($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    public function getCusinTypes()
    {
        try {
            $cuisine_types = DB::table('cuisine_types')->orderBy('name')->get();

            return [
                'cuisine_types' => $cuisine_types,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log ($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    public function postCuisineType(Request $request)
    {
        try {
            $id = $request->input('id');
            $data['name'] = $request->input('name');
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');

            if ($id) {
                DB::table('cuisine_types')
                    ->where("id", $id)
                    ->update($data);
            } else {
                $id = DB::table('cuisine_types')
                    ->insertGetId($data);
            }

            $cuisine_type = DB::table('cuisine_types')->where("id", $id)->first();
            return [
                'cuisine_type' => $cuisine_type,
            ];

        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log ($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    public function deleteCuisineType(Request $request, $id)
    {
        try {
            DB::table('cuisine_types')
                ->where("id", $id)
                ->delete();

            return [
                'success' => true,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log ($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    public function getAmenities()
    {
        try {
            $amenities = DB::table('amenities')->orderBy('name')->get();

            return [
                'amenities' => $amenities,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log ($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    public function postAmenity(Request $request)
    {
        try {
            $id = $request->input('id');
            $data['name'] = $request->input('name');
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');

            if ($id) {
                DB::table('amenities')
                    ->where("id", $id)
                    ->update($data);
            } else {
                $id = DB::table('amenities')
                    ->insertGetId($data);
            }

            $amenity = DB::table('amenities')->where("id", $id)->first();
            return [
                'amenity' => $amenity,
            ];

        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log ($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    public function deleteAmenity(Request $request, $id)
    {
        try {
            DB::table('amenities')
                ->where("id", $id)
                ->delete();

            return [
                'success' => true,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log ($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    public function getAddons()
    {
        try {
            $addons = DB::table('addons')->orderBy('name')->get();
            return [
                'addons' => $addons,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log ($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    public function postAddon(Request $request)
    {
        try {
            $id = $request->input('id');
            $data['name'] = $request->input('name');
            $data['price'] = $request->input('price');
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');

            if ($id) {
                DB::table('addons')
                    ->where("id", $id)
                    ->update($data);
            } else {
                $id = DB::table('addons')
                    ->insertGetId($data);
            }

            $addon = DB::table('addons')->where("id", $id)->first();
            return [
                'addon' => $addon,
            ];

        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log ($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    public function deleteAddon(Request $request, $id)
    {
        try {
            DB::table('addons')
                ->where("id", $id)
                ->delete();

            return [
                'success' => true,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log ($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    public function getPricePlans(Request $request): JsonResponse|array
    {
        try {
            $id = $request->input('id');
            $apiCallVenueShortCode = request()->get('venue_short_code');

            $pricingPlans = PricingPlan::select([
                'pricing_plans.id',
                'pricing_plans.category',
                'pricing_plans.name',
                'pricing_plans.monthly_cost',
                'pricing_plans.yearly_cost',
                'pricing_plans.currency',
                'pricing_plans.short_code',
                'pricing_plans.active',
            ])
                ->with(['featuresList', 'subFeatures'])
                ->get();

            $restAddons = Addon::select([
                'addons.id',
                'addons.name',
            ])
                ->with(['addonFeatures', 'addonSubFeatures'])
                ->get();


            if ($id) {
                if ($apiCallVenueShortCode) {

//                    $planId = DB::table('restaurants')->where('short_code', $apiCallVenueShortCode)->first();
//                    $restaurantId = DB::table('restaurants')->where('short_code', $apiCallVenueShortCode)->first();
//                    $addonIds = DB::table('restaurant_addons')->where('restaurants_id', $restaurantId->id)->get();

//                    $pricing_plans = $pricingPlans->where('id', $planId->plan_id)->first();
                    $pricing_plans = new \stdClass();

                    $menu_features = array();
                    $menu_sub_features = array();

//                    if ($addonIds->count()) {
//                        foreach ($addonIds as $addonId) {
//                            $addonIdValue = $addonId->addons_id;
//                            $final_addon = $restAddons->where('id', $addonIdValue)->first();
//                                $menu_features[] = $final_addon->addonFeatures;
//                                $menu_sub_features[] = $final_addon->addonSubFeatures;
//
//                        };
//                    }
//
//                    $menu_features[] = $pricing_plans?->featuresList;
//                    $menu_sub_features[] = $pricing_plans?->subFeatures;
//                    // Features re-arrangement
//                    $displayedMenuFeatures = $menu_features;
//                    $indexedArray = [];
//                    $id = 1;
//
//
//
//                    foreach ($displayedMenuFeatures as $objects) {
//                        foreach ($objects as $object) {
//                            $link = $object->link;
//
//                            // Check if link already exists in the indexed array
//                            if (!isset($indexedArray[$link])) {
//                                $object->id = $id;
//                                $indexedArray[$link] = $object;
//                                $id++;
//                            }
//                        }
//                    }
//
//                    $finalArrayMenuFeatures = array_values($indexedArray);
//
//
//                    // Sub-features re-arrangement
//                    $displayedMenuSubFeatures = array_filter($menu_sub_features, function($item) {
//                        return !is_null($item);
//                    });;
//                    $indexedSubFeaturesArray = [];
//                    $subFid = 1;
//
//
//                    foreach ($displayedMenuSubFeatures as $objects) {
//                        foreach ($objects as $object) {
//                            $link = $object->link;
//
//                            // Check if link already exists in the indexed array
//                            if (!isset($indexedSubFeaturesArray[$link])) {
//                                $object->id = $subFid;
//                                $indexedSubFeaturesArray[$link] = $object;
//                                $subFid++;
//                            }
//                        }
//                    }
//
//                    $finalArrayMenuSubFeatures = array_values($indexedSubFeaturesArray);

//                    $pricing_plans->menu_features = $finalArrayMenuFeatures;
                    $pricing_plans->menu_features = null;
//                    $pricing_plans->menu_sub_features = $finalArrayMenuSubFeatures;
                    $pricing_plans->menu_sub_features = null;
                }

            } else {

                $venueIndustry = 'Food';
                $pricingPlans =
//                    PricingPlan::where('category', $venueIndustry)
                    PricingPlan::where('is_custom', 0)
                    ->where('active', 1)
                    ->where('stripe_id', '!=', null)
                    ->get();


                // create a combination of industry name and short name
                $venueIndustryCombinations = [
                    'Food' => 'food',
                    'Sport & Entertainment' => 'sport_entertainment',
                    'Accommodation' => 'accommodation',
                    'Retail' => 'retail',
                ];

                $industryShortName = $venueIndustryCombinations[$venueIndustry];

                // format returned pricing plans to return
                foreach ($pricingPlans as $plan) {

                    $prices = $plan->pricingPlanPrices()->select('unit_amount', 'recurring', 'trial_period_days', 'stripe_id')->get();

                    $planData = [
                        'name' => $plan->name,
                        'description' => $plan->description,
                        'stripe_id' => $plan->stripe_id, // Include the Stripe ID here
                        'prices' => $prices
                    ];

                    $returnedPricingPlans[] = $planData;
                }

                return response()->json(
                    [
                        'message' => 'Successfully retrieved pricing plans',
                        'count' => count($returnedPricingPlans),
                        'strPlans' => $returnedPricingPlans
                    ], 200);
                $pricing_plans = $pricingPlans->orderBy('name')->get();
            }

            return [
                'pricing_plans' => $pricing_plans,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log ($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
            return new JsonResponse([], 500);
        }
    }

    public function postPricePlan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'description' => 'nullable|string',
            'monthly_cost' => 'required|integer',
            'yearly_cost' => 'required|integer',
            'features' => 'required|array',
            'sub_features' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $id = $request->input('id');
            $data['name'] = $request->input('name');
            $data['description'] = $request->input('description');
            $data['monthly_cost'] = $request->input('monthly_cost');
            $data['yearly_cost'] = $request->input('yearly_cost');
            $data['currency'] = 'USD';
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');

            if ($id) {
                DB::table('pricing_plans')
                    ->where("id", $id)
                    ->update($data);
            } else {
                $id = DB::table('pricing_plans')
                    ->insertGetId($data);
            }

            $features = $request->input('features');
            $sub_features = $request->input('sub_features');

            if ($features) {
                DB::table('plan_features')->where('plan_id', $id)->delete();
                foreach ($features as $key => $feature) {
                    DB::table('plan_features')->insert([
                        'plan_id' => $id,
                        'feature_id' => $feature,
                    ]);
                }
            }
            if ($sub_features) {
                DB::table('plan_sub_features')->where('plan_id', $id)->delete();
                foreach ($sub_features as $key => $feature) {
                    DB::table('plan_sub_features')->insert([
                        'plan_id' => $id,
                        'sub_feature_id' => $feature,
                    ]);
                }
            }

            $pricing_plan = DB::table('pricing_plans')->where("id", $id)->first();
            return [
                'pricing_plan' => $pricing_plan,
            ];

        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log ($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    public function deletePricePlan(Request $request, $id)
    {
        try {
            DB::table('pricing_plans')
                ->where("id", $id)
                ->delete();

            return [
                'success' => true,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log ($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    public function fetchFeatures()
    {
        $features = DB::table('features')->orderBy('name')->get();
        return response()->json(['features' => $features], 200);
    }

    public function fetchSubFeatures(Request $request)
    {
        $feature_id = $request->input('feature_id');
        $sub_features = DB::table('sub_features');
        if ($feature_id) {
            $sub_features = $sub_features->where('feature_id', $feature_id);
        }

        $sub_features = $sub_features->orderBy('name')->get();
        return response()->json(['sub_features' => $sub_features], 200);
    }

    public function storeFeature(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'name' => 'required|string',
            'link' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $id = $request->input('id');
        $featureData = $request->only('name', 'link');

        if ($id) {
            $feature = DB::table('features')->where('id', $id)->first();
            if (!$feature) {
                return response()->json(['error' => 'Feature not found'], 404);
            }

            DB::table('features')->update($featureData);
        } else {
            $id = DB::table('features')->insertGetId($featureData);
        }

        $feature = DB::table('features')->where('id', $id)->first();

        return response()->json(['feature' => $feature], 201);
    }

    public function storeSubFeature(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'feature_id' => 'required|integer',
            'name' => 'required|string',
            'link' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $feature_id = $request->input('feature_id');
        $feature = DB::table('features')->where('id', $feature_id)->first();
        if (!$feature) {
            return response()->json(['error' => 'Feature not found'], 404);
        }

        $id = $request->input('id');
        $featureData = $request->only('name', 'feature_id', 'link');
        if ($id) {
            $feature = DB::table('sub_features')->where('id', $id)->first();
            if (!$feature) {
                return response()->json(['error' => 'Sub Feature not found'], 404);
            }

            DB::table('sub_features')->update($featureData);
        } else {
            $id = DB::table('sub_features')->insertGetId($featureData);
        }

        $sub_feature = DB::table('sub_features')->where('id', $id)->first();

        return response()->json(['sub_feature' => $sub_feature], 201);
    }

    public function destroyFeature($id)
    {
        $feature = DB::table('features')->where('id', $id)->first();
        if (!$feature) {
            return response()->json(['error' => 'Feature not found'], 404);
        }

        DB::table('features')->where('id', $id)->delete();
        return response()->json(['feature' => $feature], 204);
    }

    public function destroySubFeature($id)
    {
        $sub_feature = DB::table('sub_features')->where('id', $id)->first();
        if (!$sub_feature) {
            return response()->json(['error' => 'Sub Feature not found'], 404);
        }

        DB::table('sub_features')->where('id', $id)->delete();
        return response()->json(['sub_feature' => $sub_feature], 204);
    }
}
