<?php
namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\LoyaltyProgram;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use function response;

/**
 * @OA\Info(
 *   title="Loyalty API",
 *   version="1.0",
 *   description="This API allows use Loyalty Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Loyalty",
 *   description="Operations related to Loyalty"
 * )
 */


class LoyaltyController extends Controller
{
    /**
     * @OA\Get(
     *    path="/loyalty/enrolled-guests",
     *     summary="Get list of enrolled guests by Loyalty ID",
     *     tags={"Loyalty"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Loyalty Program ID",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
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
     *     example="Enrolled guests retrieved successfully"
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
    public function getEnrolledGuests($id): JsonResponse
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


        $loyaltyProgram = LoyaltyProgram::where('id', $id)->where('venue_id', $venue->id)->first();

        if (!$loyaltyProgram) {
            return response()->json(['error' => 'LoyaltyProgram not found'], 404);
        }


        $enrolledGuests = $loyaltyProgram->guests()->select('name', 'email', 'phone')
            ->withPivot('created_at')->get();

        return response()->json([
            'data' => $enrolledGuests,
            'message' => 'Enrolled guests retrieved successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/loyalty",
     *     tags={"Loyalty"},
     *     summary="Store a new loyalty",
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
     *                     property="title",
     *                     type="string",
     *                     description="Title",
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     description="Description",
     *                 ),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Loyalty program created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Success message",
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Loyalty program details",
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
            'title' => 'required|string',
            'description' => 'required|string',
            'reward_value' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $loyaltyProgram = LoyaltyProgram::create([
            'title' => $request->title,
            'description' => $request->description,
            'reward_value' => $request->reward_value,
            'venue_id' => $venue->id,
        ]);

        return response()->json(['message' => 'Loyalty program created successfully', 'data' => $loyaltyProgram], 201);

    }

    /**
     * @OA\Get(
     *     path="/loyalty/{id}",
     *     summary="Show loyalty details",
     *     tags={"Loyalty"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Loyalty ID",
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
     *         description="Loyalty not found",
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


        $loyalty = LoyaltyProgram::where('id', $id)
            ->where('venue_id', $venue->id)
            ->first();

        if (!$loyalty) {
            return response()->json(['error' => 'Loyalty not found'], 404);
        }


        return response()->json(['message' => 'Loyalty retrieved successfully', 'data' => $loyalty], 200);
    }


    /**
     * @OA\Put(
     *     path="/loyalty",
     *     summary="Update a loyalty program",
     *     tags={"Loyalty"},
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
            'id' => 'required|exists:loyalty_programs,id',
            'title' => 'required|string',
            'description' => 'required|string',
            'reward_value' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $loyalty = LoyaltyProgram::where('id', $request->id)
            ->where('venue_id', $venue->id)
            ->first();

        if (!$loyalty) {
            return response()->json(['error' => 'Loyalty not found'], 404);
        }

        // Update the loyalty attributes
        $loyalty->title = $request->title;
        $loyalty->description = $request->description;
        $loyalty->reward_value = $request->reward_value;
        $loyalty->save();


        return response()->json(['message' => 'Loyalty updated successfully', 'data' => $loyalty], 200);
    }



}
