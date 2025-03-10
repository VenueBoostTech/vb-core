<?php
namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\RentalUnit;
use App\Models\StoreSetting;
use App\Rules\MaxSpentRule;
use App\Rules\NumericRangeRule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use function response;

/**
 * @OA\Info(
 *   title="Promotions API",
 *   version="1.0",
 *   description="This API allows use Promotions Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Promotions",
 *   description="Operations related to Promotions"
 * )
 */


class PromotionsController extends Controller
{
    /**
     * @OA\Get(
     *    path="/promotions",
     *     summary="Get list of promotions by Venue ID",
     *     tags={"Promotions"},
     *     @OA\Parameter(
     *     name="venue_short_code",
     *     in="query",
     *     description="Venue Short Code",
     *     required=true,
     *     @OA\Schema(
     *     type="string"
     *    )
     *  ),
     *     @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *     @OA\Property(
     *     property="message",
     *     type="string",
     *     example="Promotions retrieved successfully"
     *    ),
     *     @OA\Property(
     *     property="data"
     *   )
     * )
     * ),
     *     @OA\Response(
     *     response=400,
     *     description="Bad Request"
     *   ),
     *     @OA\Response(
     *     response=404,
     *     description="Not Found"
     *  ),
     *     @OA\Response(
     *     response=500,
     *     description="Internal Server Error"
     * )
     * )
     *
     */
    public function index(): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $promotions = Promotion::where('venue_id', $venue->id)->get();

        // Load the associated discounts or coupons for each promotion
        $promotions->load('discounts');
        $promotions->load('coupons');

        // Transform each promotion to include the correct start and end times
        $promotions->transform(function ($promotion) {
            if ($promotion->type === 'discount' && $promotion->discounts->count() > 0) {
                $discount = $promotion->discounts->first();
                $promotion->start_time = $discount->start_time;
                $promotion->end_time = $discount->end_time;
            } elseif ($promotion->type === 'coupon' && $promotion->coupons->count() > 0) {
                $coupon = $promotion->coupons->first();
                $promotion->start_time = $coupon->start_time;
                $promotion->end_time = $coupon->expiry_time;
            }

            return $promotion;
        });

        return response()->json(['message' => 'Promotions retrieved successfully', 'data' => $promotions], 200);
    }

    /**
     * @OA\Post(
     *     path="/promotions",
     *     tags={"Promotions"},
     *     summary="Store a new promotion",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="venue_short_code",
     *                     type="string",
     *                     description="Venue short code",
     *                 ),
     *                 @OA\Property(
     *                     property="discount_id",
     *                     type="integer",
     *                     description="Discount ID",
     *                 ),
     *                 @OA\Property(
     *                     property="title",
     *                     type="string",
     *                     description="Title",
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     description="Description",
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     description="Type (discount/coupon)",
     *                 ),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Promotion added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Success message",
     *             ),
     *             @OA\Property(
     *                 property="promotion",
     *                 type="object",
     *                 description="Promotion details",
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 description="Error message",
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Venue not found",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 description="Error message",
     *             ),
     *         ),
     *     ),
     * )
     */
    public function store(Request $request): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'discount_id' => 'nullable|exists:discounts,id',
            'coupon_id' => 'nullable|exists:coupons,id',
            'title' => 'required|string',
            'description' => 'required|string',
            'type' => 'required|in:discount,coupon'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        if ($request->input('type') === 'discount') {
            $discount = Discount::where('id', $request->input('discount_id'))
                ->where('status', true)
                ->where(function ($query) use ($request) {
                    $query->whereNull('promotion_id')
                        ->where('end_time', '>=', now());
                })
                ->first();

            if (!$discount) {
                return response()->json(['error' => 'Invalid or inactive discount provided'], 400);
            }

            $promotion = Promotion::create([
                'venue_id' => $venue->id,
                'discount_id' => $request->input('discount_id'),
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'type' => $request->input('type'),
                'start_time' => $discount->start_time,
                'end_time' => $discount->end_time,
            ]);

            // Update discount with the promotion ID
            $discount->update(['promotion_id' => $promotion->id]);

            return response()->json(['message' => 'Promotion added successfully', 'promotion' => $promotion], 201);
        }

        if ($request->input('type') === 'coupon') {
            $coupon = Coupon::where('id', $request->input('coupon_id'))
                ->where('status', true)
                ->where(function ($query) use ($request) {
                    $query->whereNull('promotion_id')
                        ->where('expiry_time', '>=', now());
                })
                ->first();

            if (!$coupon) {
                return response()->json(['error' => 'Invalid or inactive coupon provided'], 400);
            }

            $promotion = Promotion::create([
                'venue_id' => $venue->id,
                'discount_id' => $request->input('discount_id'),
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'type' => $request->input('type'),
                'start_time' => $coupon->start_time,
                'end_time' => $coupon->expiry_time,
            ]);

            // Update coupon with the promotion ID
            $coupon->update(['promotion_id' => $promotion->id]);


            return response()->json(['message' => 'Promotion added successfully', 'promotion' => $promotion], 201);

        }





    }

    /**
     * @OA\Post(
     *     path="/promotions/discounts",
     *     tags={"Promotions"},
     *     summary="Store a discount",
     *     @OA\Response(
     *         response=201,
     *         description="Discount added successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Venue not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     ),
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="venue_short_code",
     *                     type="string",
     *                     description="Venue short code",
     *                     example="ABC123"
     *                 ),
     *                 @OA\Property(
     *                     property="reservation_count",
     *                     type="integer",
     *                     description="Reservation count",
     *                     example=10
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     description="Discount type",
     *                     enum={"fixed", "percentage"}
     *                 ),
     *                 @OA\Property(
     *                     property="value",
     *                     type="number",
     *                     description="Discount value",
     *                     example=5.99
     *                 ),
     *                 @OA\Property(
     *                     property="start_time",
     *                     type="string",
     *                     format="date-time",
     *                     description="Discount start time",
     *                     example="2023-07-02 10:00:00"
     *                 ),
     *                 @OA\Property(
     *                     property="end_time",
     *                     type="string",
     *                     format="date-time",
     *                     description="Discount end time",
     *                     example="2023-07-02 18:00:00"
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function storeDiscount(Request $request): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }


        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|integer|exists:products,id',
            'rental_unit_id' => 'nullable|integer|exists:rental_units,id', // 'rental_unit_id' => 'nullable|integer|exists:rental_units,id
            'reservation_count' => 'integer',
            'type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric|min:0',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after_or_equal:start_time',
            'usage_limit_per_coupon'=>'nullable|integer',
            'minimum_spent'=>'nullable|integer',
        ]);


        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $product = null;
        if ($request->has('selected_product')) {
            $product = Product::where('id', $request->input('selected_product'))
                ->where('restaurant_id', $venue->id)
                ->first();

            if (!$product) {
                return response()->json(['error' => 'Product not found or does not belong to the venue'], 404);
            }
        }

        $category = null;
        if ($request->has('category_id')) {
            $category = Category::where('id', $request->input('category_id'))
                ->where('restaurant_id', $venue->id)
                ->first();

            if (!$category) {
                return response()->json(['error' => 'Category not found or does not belong to the venue'], 404);
            }
        }

        $rentalUnit = null;
        if ($request->has('rental_unit_id')) {
            $rentalUnit = RentalUnit::where('id', $request->input('rental_unit_id'))
                ->where('venue_id', $venue->id)
                ->first();

            if (!$rentalUnit) {
                return response()->json(['error' => 'Rental unit not found or does not belong to the venue'], 404);
            }
        }

        $product_ids = null;
        if ($request->has('product_ids')) {
            $product_ids = $request->input('product_ids');

            $product_id_values = array_map(function($item) {
                return $item['id'];
            }, $product_ids);

            $product_ids = implode(',', $product_id_values);
        }

        $discount = Discount::create([
            'venue_id' => $venue->id,
            'product_id' => $product?->id,  //
            'rental_unit_id' => $rentalUnit?->id, //
            'category_id' => $category?->id, //
            'product_ids' => $product_ids ?? null,
            'reservation_count' => $request->input('reservation_count') ?? 0,
            'type' => $request->input('type'),
            'usage_limit_per_coupon' => $request->input('usage_limit_per_coupon') ?? null,
            'usage_limit_per_customer' => $request->input('usage_limit_per_customer') ?? null,
            'minimum_spent' => $request->input('minimum_spent') ?? null,
            'coupon_use' => $request->input('coupon_use') ?? 0,
            'user_id'=> $request->input('selected_customer') ?? null,
            'selected_product'=> $request->input('selected_product') ?? mull,
            'value' => $request->input('value'),
            'start_time' => $request->input('start_time'),
            'end_time' => $request->input('end_time'),
            'apply_for' => $request->input('apply_for') ?? null,
        ]);

        return response()->json(['message' => 'Discount added successfully', 'discount' => $discount], 201);

    }

    /**
     * @OA\Get(
     *     path="/promotions/discounts",
     *     tags={"Promotions"},
     *     summary="Get all discounts for a venue",
     *     @OA\Parameter(
     *         name="venue_short_code",
     *         in="query",
     *         required=true,
     *         description="Venue short code",
     *         example="ABC123"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Discounts retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Venue not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function discounts(): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // sort by latest
        $discounts = Discount::where('venue_id', $venue->id)->orderBy('created_at', 'desc')->get();

        // Load the associated promotion, product for each discount
        $discounts->load('promotion');
        $discounts->load('product');
        $discounts->load('category');
        $discounts->load('rental_unit');
        $discounts->load('selectedProduct');

        // Get currency setting for the venue
        $storeSetting = StoreSetting::where('venue_id', $venue->id)->first();
        $currency = $storeSetting ? $storeSetting->currency : null;

        // add currency for each discount
        foreach ($discounts as $discount) {
            $discount->currency = $currency;
            $discount->product_original_price = $discount->product ? $discount->product->price : null;
            $discount->selected_customer = $discount->user_id ? $discount->user : null;
            $discount->selected_product = $discount->selected_product ? $discount->product : null;
            // discount value can be also percentage
            if ($discount->type === 'percentage') {
                $discount->price_with_discount = $discount->product ? $discount->product->price - ($discount->product->price * $discount->value / 100) : null;
            } else if ($discount->type === 'fixed') {
                $discount->price_with_discount = $discount->product ? $discount->product->price - $discount->value : null;
            }
            else {
                $discount->price_with_discount = null;
            }

            // read product_ids and add product names to the discount

            if ($discount->product_ids) {
                $product_ids = explode(',', $discount->product_ids);

                $discount->product_names = Product::whereIn('id', $product_ids)->pluck('title')->toArray();
            }

        }

        return response()->json(['message' => 'Discounts retrieved successfully', 'data' => $discounts], 200);
    }

    /**
     * @OA\Get(
     *     path="/promotions/active-discounts",
     *     summary="List active discounts",
     *     description="Retrieves a list of active discounts for the authenticated user's restaurant.",
     *     tags={"Promotions"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(
     *         name="venue_short_code",
     *         in="query",
     *         required=true,
     *         description="Venue short code",
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="discounts"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="error",
     *                 type="string"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="error",
     *                 type="string"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Venue not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="error",
     *                 type="string"
     *             )
     *         )
     *     )
     * )
     */
    public function listActiveDiscounts(): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $discounts = Discount::where('status', true)
            ->where('venue_id', $venue->id)
            ->whereNull('promotion_id')
            ->whereNull('product_id')
            ->where('end_time', '>=', now())
            ->get();

        return response()->json(['discounts' => $discounts]);
    }

    /**
     * @OA\Get(
     *     path="/promotions/{id}",
     *     summary="Show promotion details",
     *     tags={"Promotions"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="venue_short_code",
     *         in="query",
     *         description="Venue short code",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion not found",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function show($id): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }


        $promotion = Promotion::where('id', $id)
            ->where('venue_id', $venue->id)
            ->first();

        if (!$promotion) {
            return response()->json(['error' => 'Promotion not found'], 404);
        }

        // Load the associated discount or coupon
        $promotion->load('discounts');
        $promotion->load('coupons');

        return response()->json(['message' => 'Promotion retrieved successfully', 'data' => $promotion], 200);
    }

    /**
     * @OA\Get(
     *     path="/promotions/discounts/{id}",
     *     summary="Show a discount",
     *     tags={"Promotions"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Discount ID",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Discount not found",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function showDiscount($id): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $discount = Discount::where('id', $id)
            ->where('venue_id', $venue->id)
            ->first();

        if (!$discount) {
            return response()->json(['error' => 'Discount not found'], 404);
        }

        // Load the associated promotion, product
        $discount->load('promotion');
        $discount->load('product');
        $discount->load('category');
        $discount->load('rental_unit');

        return response()->json(['message' => 'Discount retrieved successfully', 'data' => $discount], 200);
    }

    /**
     * @OA\Put(
     *     path="/promotions",
     *     summary="Update a promotion",
     *     tags={"Promotions"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Promotion status details",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="discount_id",
     *                 description="Discount ID",
     *                 type="integer",
     *                 example="1"
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 description="Discount status",
     *                 type="integer",
     *                 enum={0, 1},
     *                 example="1"
     *             ),
     *        @OA\Property(
     *            property="title",
     *       description="Promotion title",
     *     type="string",
     *     example="Promotion title"
     *        ),
     *          *        @OA\Property(
     *            property="Description",
     *       description="Promotion Description",
     *     type="string",
     *     example="Promotion Description"
     *        ),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion not found",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function update(Request $request): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:promotions,id',
            'discount_id' => 'nullable|exists:discounts,id',
            'coupon_id' => 'nullable|exists:coupons,id',
            'title' => 'required|string',
            'description' => 'required|string',
            'type' => 'required|in:discount,coupon'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $promotion = Promotion::where('id', $request->id)
            ->where('venue_id', $venue->id)
            ->first();

        if (!$promotion) {
            return response()->json(['error' => 'Promotion not found'], 404);
        }

        $existingCouponOfPromotion = $promotion->coupons->first();
        $existingDiscountOfPromotion = $promotion->discounts->first();

        if (!$existingDiscountOfPromotion && !$existingCouponOfPromotion) {
            return response()->json(['error' => 'Promotion is not valid'], 400);
        }

        // Discount section
        // Check if the provided discount_id is different from the current discount_id of the promotion
        if ($request->input('type') === 'discount' && $request?->discount_id != $existingDiscountOfPromotion?->id) {
            $existingPromotion = Discount::where('id', $request->discount_id)
                ->where('promotion_id', '!=', $promotion->id)
                ->first();

            if ($existingPromotion) {
                return response()->json(['error' => 'Discount is already assigned to another promotion'], 400);
            }

            $discount = Discount::find($request->discount_id);

            if (!$discount) {
                return response()->json(['error' => 'Discount not found'], 404);
            }

            // Disconnect the previous discount from the promotion
            if ($existingDiscountOfPromotion) {
                $existingDiscountOfPromotion->promotion_id = null;
                $existingDiscountOfPromotion->save();
            }


            $discount->promotion_id = $promotion->id;
            $discount->save();

            // Add any additional validation rules for the discount, similar to the create method
        }


        // Coupon section

        // Check if the provided coupon_id is different from the current coupon_id of the promotion
        if ($request->input('type') === 'coupon' && $request?->coupon_id != $existingCouponOfPromotion?->id) {
            $existingPromotion = Coupon::where('id', $request->coupon_id)
                ->where('promotion_id', '!=', $promotion->id)
                ->first();

            if ($existingPromotion) {
                return response()->json(['error' => 'Coupon is already assigned to another promotion'], 400);
            }

            $coupon = Coupon::find($request->coupon_id);

            if (!$coupon) {
                return response()->json(['error' => 'Coupon not found'], 404);
            }

            if ($existingCouponOfPromotion) {
                // Disconnect the previous coupon from the promotion
                $existingCouponOfPromotion->promotion_id = null;
                $existingCouponOfPromotion->save();
            }

            $coupon->promotion_id = $promotion->id;
            $coupon->save();
        }


        // Update the promotion attributes
        $promotion->title = $request->title;
        $promotion->description = $request->description;
        $promotion->save();

        // Load the associated discount and coupons
        $promotion->load('discounts');
        $promotion->load('coupons');

        return response()->json(['message' => 'Promotion updated successfully', 'data' => $promotion], 200);
    }

    /**
     * @OA\Put(
     *     path="/promotions/discounts",
     *     summary="Update a discount",
     *     tags={"Promotions"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Discount details",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="id",
     *                 description="Discount ID",
     *                 type="integer",
     *                 example="1"
     *             ),
     *             @OA\Property(
     *                 property="type",
     *                 description="Discount type",
     *                 type="string",
     *                 enum={"fixed", "percentage"},
     *                 example="fixed"
     *             ),
     *             @OA\Property(
     *                 property="reservation_count",
     *                 description="Minimum reservation count",
     *                 type="integer",
     *                 example="5"
     *             ),
     *             @OA\Property(
     *                 property="value",
     *                 description="Discount value",
     *                 type="number",
     *                 example="10.5"
     *             ),
     *             @OA\Property(
     *                 property="start_time",
     *                 description="Discount start time",
     *                 type="string",
     *                 format="date-time",
     *                 example="2023-07-01 10:00:00"
     *             ),
     *             @OA\Property(
     *                 property="end_time",
     *                 description="Discount end time",
     *                 type="string",
     *                 format="date-time",
     *                 example="2023-07-01 18:00:00"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Discount not found",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function updateDiscount(Request $request): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:discounts,id',
//            'product_id' => 'exists:products,id',
            'rental_unit_id' => 'exists:rental_units,id',
            'type' => 'required|in:fixed,percentage',
            'reservation_count' => 'integer',
            'value' => 'required|numeric|min:0',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'usage_limit_per_coupon'=>'nullable|integer',
            'minimum_spent'=>'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $product = null;
//        if ($request->has('product_id')) {
//            $product = Product::where('id', $request->input('product_id'))
//                ->where('restaurant_id', $venue->id)
//                ->first();
//
//            if (!$product) {
//                return response()->json(['error' => 'Product not found or does not belong to the venue'], 404);
//            }
//        }

        $category = null;
        if ($request->has('category_id')) {
            $category = Category::where('id', $request->input('category_id'))
                ->where('restaurant_id', $venue->id)
                ->first();

            if (!$category) {
                return response()->json(['error' => 'Category not found or does not belong to the venue'], 404);
            }
        }

        $rentalUnit = null;
        if ($request->has('rental_unit_id')) {
            $rentalUnit = RentalUnit::where('id', $request->input('rental_unit_id'))
                ->where('venue_id', $venue->id)
                ->first();

            if (!$rentalUnit) {
                return response()->json(['error' => 'Rental unit not found or does not belong to the venue'], 404);
            }
        }


        $product_ids = null;
        if ($request->has('product_ids')) {
            $product_ids = $request->input('product_ids');
        }

        $discount = Discount::where('id', $request->id)
            ->where('venue_id', $venue->id)
            ->first();

        $discount->type = $request->type;
        // if product_id is provided, then category_id and product_ids are ignored
        if ($product) {
            $discount->category_id = null;
            $discount->product_ids = null;
            $discount->product_id = $product->id;
        }

        // if category_id is provided, then product_ids is ignored
        if ($category) {
            $discount->product_ids = null;
            $discount->product_id = null;
            $discount->category_id = $category->id;
        }

        // if product_ids is provided, then category_id is ignored
        if ($product_ids) {
            $discount->category_id = null;
            $discount->product_id = null;
            $product_id_values = array_map(function($item) {
                return $item['id'];
            }, $product_ids);

            $product_ids_string = implode(',', $product_id_values);

            $discount->product_ids = $product_ids_string;
        }

        $discount->rental_unit_id = $rentalUnit ? $rentalUnit->id : $discount->rental_unit_id;
        $discount->reservation_count = $request->reservation_count ?? $discount->reservation_count;
        $discount->value = $request->value;
        $discount->start_time = $request->start_time;
        $discount->end_time = $request->end_time;


        $discount->selected_product = $request->selected_product ? $request->selected_product : null;
        $discount->usage_limit_per_coupon = $request->usage_limit_per_coupon ? $request->usage_limit_per_coupon : null;
        $discount->usage_limit_per_customer = $request->usage_limit_per_customer ? $request->usage_limit_per_customer : null;
        $discount->coupon_use = $request->coupon_use ? $request->coupon_use : 0;
        $discount->user_id = $request->selected_customer ? $request->selected_customer : null;
        $discount->minimum_spent = $request->minimum_spent ? $request->minimum_spent : null;

        $discount->save();

        $discount->load('promotion');
        $discount->load('product');
        $discount->load('category');
        $discount->load('rental_unit');

        return response()->json(['message' => 'Discount updated successfully', 'data' => $discount], 200);
    }

    /**
     * @OA\Patch (
     *     path="/promotions/{id}/update-status",
     *     summary="Update promotion status",
     *     tags={"Promotions"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Promotion status details",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="promotion_id",
     *                 description="Promotion ID",
     *                 type="integer",
     *                 example="1"
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 description="Promotion status",
     *                 type="integer",
     *                 enum={0, 1},
     *                 example="1"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion not found",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function updateStatus(Request $request): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'promotion_id' => 'required|exists:promotions,id',
            'status' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $promotion = Promotion::where('id', $request->promotion_id)
            ->where('venue_id', $venue->id)
            ->first();

        $promotion->status = $request->status;
        $promotion->save();

        // Update associated discount status
        if ($promotion->discounts->count()) {

            foreach ($promotion->discounts as $discount) {
                $discount->status = $request->status;
                $discount->save();
            }
        }

        // Update associated coupons status
        if ($promotion->coupons->count()) {

            foreach ($promotion->coupons as $coupon) {
                $coupon->status = $request->status;
                $coupon->save();
            }
        }

        $promotion->load('discounts');
        $promotion->load('coupons');

        return response()->json(['message' => 'Promotion status updated successfully', 'data' => $promotion], 200);
    }

    /**
     * @OA\Patch(
     *     path="/promotions/discounts/{id}/update-status",
     *     summary="Update discount status",
     *     tags={"Promotions"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Discount status details",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="discount_id",
     *                 description="Discount ID",
     *                 type="integer",
     *                 example="1"
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 description="Discount status",
     *                 type="integer",
     *                 enum={0, 1},
     *                 example="1"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Discount not found",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function updateDiscountStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'discount_id' => 'required|exists:discounts,id',
            'status' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $discount = Discount::findOrFail($request->discount_id);
        $discount->status = $request->status;
        $discount->save();

        if ($discount->promotion_id) {
            $promotion = Promotion::findOrFail($discount->promotion_id);
            $promotion->status = $request->status;
            $promotion->save();
        }

        $discount->load('promotion');
        $discount->load('product');
        $discount->load('category');
        $discount->load('rental_unit');

        return response()->json(['message' => 'Discount status updated successfully', 'data' => $discount], 200);
    }


    public function calendar(Request $request): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            "year" => "required|integer",
            "month" => "required|integer",
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        };


        if ($venue->venueType->name !== 'Golf Venue' && $request->type === 'golf') {
            return response()->json(['error' => 'Venue is not a golf venue'], 400);
        }

        if ($venue->venueType->name !== 'Hotel' &&
            ($request->type === 'hotel_events_hall' || $request->type === 'hotel_restaurant' || $request->type === 'hotel_gym')) {
            return response()->json(['error' => 'Venue is not a hotel venue'], 400);
        }

        $formattedPromotions = [];

        // Get the year and month from the request
        $year = $request->input('year');
        $month = $request->input('month');

        // Calculate the start and end dates for the specified month
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Query promotions for the specified venue and dates
        $promotions = Promotion::with(['discounts', 'coupons'])->where('venue_id', $venue->id)
            ->whereBetween('start_time', [$startDate, $endDate])
            ->get();


        foreach ($promotions as $promotion) {
            $startTime = Carbon::parse($promotion->start_time);
            $endTime = Carbon::parse($promotion->end_time);

            // Check if the promotion extends to multiple days
            while ($endTime->greaterThanOrEqualTo($startTime)) {
                // Add a new entry for each day the promotion covers
                $formattedPromotions[] = [
                    'day' => $startTime->day,
                    'date' => $startTime->format('d M Y  h:i A'),
                    'title' => $promotion->title,
                    'description' => $promotion->description,
                    'type' => $promotion->type,
                    'status' => $promotion->status,
                    'discount' => $promotion->discounts->count() ? $promotion->discounts[0] : null,
                    'coupon' => $promotion->coupons->count() ? $promotion->coupons[0] : null,
                    'nr_of_times_used' => 0
                ];

                // Move to the next day
                $startTime->addDay();
            }
        }

        return response()->json($formattedPromotions, 200);


    }


    // COUPONS SECTION
    public function storeCoupon(Request $request): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'nullable|unique:coupons,code',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:fixed_cart_discount,percentage_cart_discount',
            'discount_amount' => 'required|numeric|min:0',
            'start_time' => 'required|date',
            'expiry_time' => 'required|date|after_or_equal:start_time',
            'minimum_spent' => ['nullable', 'numeric', new NumericRangeRule()],
            'maximum_spent' => ['nullable', 'numeric', new NumericRangeRule()],
            'usage_limit_per_coupon' =>  ['nullable', 'integer', new NumericRangeRule()],
            'usage_limit_per_customer' => ['nullable', 'integer', new NumericRangeRule()],
        ]);


        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $code = $request->input('code') ? strtoupper($request->input('code')) : $this->generateCouponCode();
        $coupon = Coupon::create([
            'code' => $code,
            'description' => $request->input('description'),
            'venue_id' => $venue->id,
            'discount_type' => $request->input('discount_type'),
            'discount_amount' => $request->input('discount_amount'),
            'start_time' => $request->input('start_time'),
            'expiry_time' => $request->input('expiry_time'),
            'minimum_spent' => $request->input('minimum_spent'),
            'maximum_spent' => $request->input('maximum_spent'),
            'selected_product' => $request->input('selected_product'),
            'selected_customer' => $request->input('selected_customer'),
            'product_id' => $request->input('selected_product'),
            'user_id' => $request->input('selected_customer'),
            'usage_limit_per_coupon' => $request->input('usage_limit_per_coupon'),
            'usage_limit_per_customer' => $request->input('usage_limit_per_customer'),
        ]);

        return response()->json(['message' => 'Coupon added successfully', 'coupon' => $coupon], 201);

    }

    public function coupons(): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // sort by latest
        $coupons = Coupon::where('venue_id', $venue->id)->orderBy('created_at', 'desc')->get();

        // Load the associated promotion for each coupon
        $coupons->load('promotion');


        // Get currency setting for the venue
        $storeSetting = StoreSetting::where('venue_id', $venue->id)->first();
        $currency = $storeSetting ? $storeSetting->currency : null;

        // add currency for each coupon
        foreach ($coupons as $coupon) {
            $coupon->currency = $currency;
            $coupon->selected_customer_name = $coupon->user ?? null;
            $coupon->selected_product_name = $coupon->product ?? null;
        }

        return response()->json(['message' => 'Coupons retrieved successfully', 'data' => $coupons], 200);
    }


    public function showCoupon($id): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $coupon = Coupon::where('id', $id)
            ->where('venue_id', $venue->id)
            ->first();

        if (!$coupon) {
            return response()->json(['error' => 'Coupon not found'], 404);
        }

        // Load the associated coupon
        $coupon->load('promotion');

        return response()->json(['message' => 'Coupon retrieved successfully', 'data' => $coupon], 200);
    }

    public function updateCouponStatus(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'coupon_id' => 'required|exists:coupons,id',
            'status' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $coupon = Coupon::findOrFail($request->coupon_id);
        $coupon->status = $request->status;
        $coupon->save();

        if ($coupon->promotion_id) {
            $promotion = Promotion::findOrFail($coupon->promotion_id);
            $promotion->status = $request->status;
            $promotion->save();
        }

        $coupon->load('promotion');

        return response()->json(['message' => 'Coupon status updated successfully', 'data' => $coupon], 200);
    }

    public function listActiveCoupons(): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $coupons = Coupon::where('status', true)
            ->where('venue_id', $venue->id)
            ->whereNull('promotion_id')
            ->where('expiry_time', '>=', now())
            ->get();

        return response()->json(['coupons' => $coupons]);
    }

    public function updateCoupon(Request $request): JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'nullable|string|unique:coupons,code,' . $request->input('id'),
            'id' => 'required|exists:coupons,id',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:fixed_cart_discount,percentage_cart_discount',
            'discount_amount' => 'required|numeric|min:0',
            'start_time' => 'required|date',
            'expiry_time' => 'required|date|after_or_equal:start_time',
            'minimum_spent' => 'nullable|numeric|min:0',
            'maximum_spent' => ['nullable', 'numeric', 'min:0', new MaxSpentRule($request->input('minimum_spent'))],
            'usage_limit_per_coupon' => 'nullable|integer',
            'usage_limit_per_customer' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $coupon = Coupon::where('id', $request->id)
        ->where('venue_id', $venue->id)
        ->first();

        $coupon->code = $request->input('code', $coupon->code);
        $coupon->description = $request->input('description', $coupon->description);
        $coupon->discount_type = $request->input('discount_type', $coupon->discount_type);
        $coupon->discount_amount = $request->input('discount_amount', $coupon->discount_amount);
        $coupon->start_time = $request->input('start_time', $coupon->start_time);
        $coupon->expiry_time = $request->input('expiry_time', $coupon->expiry_time);
        $coupon->minimum_spent = $request->input('minimum_spent', $coupon->minimum_spent);
        $coupon->maximum_spent = $request->input('maximum_spent', $coupon->maximum_spent);
        $coupon->product_id = $request->input('selected_product', $coupon->product_id);
        $coupon->user_id = $request->input('selected_customer', $coupon->user_id);
        $coupon->usage_limit_per_coupon = $request->input('usage_limit_per_coupon', $coupon->usage_limit_per_coupon);
        $coupon->usage_limit_per_customer = $request->input('usage_limit_per_customer', $coupon->usage_limit_per_customer);

        $coupon->save();


        $coupon->load('promotion');

        return response()->json(['message' => 'Coupon updated successfully', 'data' => $coupon], 200);
    }

    private function generateCouponCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Coupon::where('code', $code)->exists());

        return $code;
    }

}
