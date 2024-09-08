<?php

namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


/**
 * @OA\Info(
 *   title="Superadmin Vendor Configuration API",
 *   version="1.0",
 *   description="This API allows use Superadmin Vendor Configuration Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="SuperadminVendorConfiguration",
 *   description="Operations related to Superadmin Vendor Configuration"
 * )
 */

class VendorConfigurationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/restaurants/register-config",
     *     summary="Get vendor registration configuration",
     *     tags={"SuperadminVendorConfiguration"},
     *     @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *     type="object",
     *     @OA\Property(
     *     property="data",
     *     type="object",
     *     @OA\Property(
     *     property="cuisine_types",
     *     type="array",
     *     @OA\Items(
     *     type="object",
     *     @OA\Property(
     *     property="id",
     *     type="integer",
     *     example=1
     *     ),
     *     @OA\Property(
     *     property="name",
     *     type="string",
     *     example="American"
     *    )
     *  )
     * ),
     *     @OA\Property(
     *     property="amenities",
     *     type="array",
     *     @OA\Items(
     *     type="object",
     *     @OA\Property(
     *     property="id",
     *     type="integer",
     *     example=1
     *     ),
     *     @OA\Property(
     *     property="name",
     *     type="string",
     *     example="Wifi"
     *   )
     * )
     * ),
     *     @OA\Property(
     *     property="states",
     *     type="array",
     *     @OA\Items(
     *     type="object",
     *     @OA\Property(
     *     property="id",
     *     type="integer",
     *     example=1
     *     ),
     *     @OA\Property(
     *     property="name",
     *     type="string",
     *     example="Alabama"
     *  )
     * )
     * ),
     *     @OA\Property(
     *     property="cities",
     *     type="array",
     *     @OA\Items(
     *     type="object",
     *     @OA\Property(
     *     property="id",
     *     type="integer",
     *     example=1
     *     ),
     *     @OA\Property(
     *     property="name",
     *     type="string",
     *     example="Abbeville"
     *  )
     * )
     * )
     * )
     * )
     * )
     * )
     * )
     * )
     * )
     * )
     * )
     */
    public function getRegisterConfig(): JsonResponse|array
    {
        try {
            $cuisine_types = DB::table('cuisine_types')->orderBy('name')->get();
            $amenities = DB::table('amenities')->orderBy('name')->get();
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
                'states' => $states,
                'cities' => $cities,
                'countries' => $countries
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/restaurants/payment-config",
     *     tags={"SuperadminVendorConfiguration"},
     *     summary="Get payment configuration",
     *     description="Get the payment configuration including pricing plans and addons",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="pricing_plans", type="array", @OA\Items()),
     *             @OA\Property(property="addons", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function getPaymentConfig(): JsonResponse|array
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
            \error_log($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/restaurant-config/cuisinetypes",
     *     tags={"SuperadminVendorConfiguration"},
     *     summary="Get all cuisine types",
     *     description="Retrieve a list of all cuisine types",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="cuisine_types",
     *                 type="array",
     *                 @OA\Items()
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function getCuisineTypes(): JsonResponse|array
    {
        try {
            $cuisine_types = DB::table('cuisine_types')->orderBy('name')->get();

            return [
                'cuisine_types' => $cuisine_types,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/restaurant-config/cuisinetypes",
     *     tags={"SuperadminVendorConfiguration"},
     *     summary="Create or update a cuisine type",
     *     description="Create a new cuisine type or update an existing one",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"name"},
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="id", type="integer", format="int64"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="cuisine_type", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function postCuisineType(Request $request): JsonResponse|array
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return new JsonResponse($validator->errors(), 400);
        }

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
            \error_log($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/restaurant-config/cuisinetypes/{id}",
     *     tags={"SuperadminVendorConfiguration"},
     *     summary="Delete a cuisine type",
     *     description="Delete a cuisine type by its ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the cuisine type to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cuisine type not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function deleteCuisineType(Request $request, $id): array|JsonResponse
    {
        $cuisine_type = DB::table('cuisine_types')->where("id", $id)->first();
        if (!$cuisine_type) {
            return new JsonResponse([], 404);
        }

        try {
            DB::table('cuisine_types')
                ->where("id", $id)
                ->delete();

            return [
                'success' => true,
                'message' => 'Cuisine type deleted successfully'
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/restaurant-config/amenities",
     *     tags={"SuperadminVendorConfiguration"},
     *     summary="Get all amenities",
     *     description="Retrieve a list of all amenities",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="amenities", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function getAmenities(): JsonResponse|array
    {
        try {
            $amenities = DB::table('amenities')->orderBy('name')->get();

            return [
                'amenities' => $amenities,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/restaurant-config/amenities",
     *     tags={"SuperadminVendorConfiguration"},
     *     summary="Create or update an amenity",
     *     description="Create a new amenity or update an existing amenity",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"name"},
     *                 @OA\Property(property="id", type="integer", description="ID of the amenity (optional)"),
     *                 @OA\Property(property="name", type="string", description="Name of the amenity"),
     *                 example={"name": "WiFi"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="amenity", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function postAmenity(Request $request): JsonResponse|array
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return [
                'errors' => $validator->errors(),
            ];
        }
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
            \error_log($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/restaurant-config/amenities/{id}",
     *     tags={"SuperadminVendorConfiguration"},
     *     summary="Delete an amenity",
     *     description="Delete an amenity by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the amenity",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function deleteAmenity(Request $request, $id): array|JsonResponse
    {
        $amenity = DB::table('amenities')->where("id", $id)->first();
        if (!$amenity) {
            return [
                'success' => false,
                'message' => 'Amenity not found',
            ];
        }
        try {
            DB::table('amenities')
                ->where("id", $id)
                ->delete();

            return [
                'success' => true,
                'message' => 'Amenity deleted',
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/restaurant-config/addons",
     *     tags={"SuperadminVendorConfiguration"},
     *     summary="Get all addons",
     *     description="Retrieve a list of all addons",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="addons",
     *                 type="array",
     *                 @OA\Items()
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function getAddons(): JsonResponse|array
    {
        try {
            $addons = DB::table('addons')->orderBy('name')->get();
            return [
                'addons' => $addons,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/restaurant-config/addons",
     *     tags={"SuperadminVendorConfiguration"},
     *     summary="Create or update an addon",
     *     description="Create a new addon or update an existing addon",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Addons data",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"name", "price"},
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     description="Name of the addon"
     *                 ),
     *                 @OA\Property(
     *                     property="price",
     *                     type="number",
     *                     format="float",
     *                     description="Price of the addon"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="addon"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function postAddon(Request $request): JsonResponse|array
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'price' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => $validator->errors()->first()], 400);
            }

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
            \error_log($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/restaurant-config/addons/{id}",
     *     tags={"SuperadminVendorConfiguration"},
     *     summary="Delete an addon",
     *     description="Delete an existing addon by its ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the addon to delete",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Addon not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function deleteAddon(Request $request, $id): array|JsonResponse
    {
        $addon = DB::table('addons')->where("id", $id)->first();
        if (!$addon) {
            return response()->json(['message' => 'Addon not found'], 404);
        }

        try {
            DB::table('addons')
                ->where("id", $id)
                ->delete();

            return [
                'success' => true,
                'message' => 'Addon deleted successfully'
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/restaurant-config/pricing-plans",
     *     tags={"SuperadminVendorConfiguration"},
     *     summary="Get all pricing plans",
     *     description="Retrieve a list of all pricing plans",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="pricing_plans",
     *
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function getPricePlans(): JsonResponse|array
    {
        try {
            $pricing_plans = DB::table('pricing_plans')->orderBy('name')->get();
            return [
                'pricing_plans' => $pricing_plans,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="restaurant-config/pricing-plans",
     *     tags={"SuperadminVendorConfiguration"},
     *     summary="Create or update a pricing plan",
     *     description="Create a new pricing plan or update an existing one",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="[]")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="pricing_plan",
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function postPricePlan(Request $request): JsonResponse|array
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'description' => 'required',
            'monthly_cost' => 'required|numeric',
            'yearly_cost' => 'required|numeric',
            'features' => 'required',
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
            $data['features'] = $request->input('features');
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

            $pricing_plan = DB::table('pricing_plans')->where("id", $id)->first();
            return [
                'pricing_plan' => $pricing_plan,
            ];

        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log($e->getMessage());
            return new JsonResponse([], $e);
        }
    }

    /**
     * @OA\Delete(
     *     path="/restaurant-config/pricing-plans/{id}",
     *     tags={"SuperadminVendorConfiguration"},
     *     summary="Delete a pricing plan",
     *     description="Delete a pricing plan by its ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the pricing plan",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Pricing plan not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function deletePricePlan(Request $request, $id): array|JsonResponse
    {
        $pricing_plan = DB::table('pricing_plans')->where("id", $id)->first();
        if (!$pricing_plan) {
            return response()->json(['message' => 'Pricing plan not found'], 404);
        }

        try {
            DB::table('pricing_plans')
                ->where("id", $id)
                ->delete();

            return [
                'success' => true,
                'message' => 'Pricing plan deleted successfully',
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log($e->getMessage());
            return new JsonResponse([], 500);
        }
    }
}
