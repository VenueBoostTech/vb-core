<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\DiningSpaceLocation;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use stdClass;
use function collect;
use function response;

/**
 * @OA\Info(
 *   title="Table Management API",
 *   version="1.0",
 *   description="This API allows use to retrieve all Table Management related data",
 * )
 */

/**
 * @OA\Tag(
 *   name="Table Management",
 *   description="Operations related to Table Management"
 * )
 */

class TableController extends Controller
{
    /**
     * @OA\Get(
     *     path="/tables",
     *     summary="List all tables",
     *     tags={"Table Management"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="seats", type="integer"),
     *                     @OA\Property(property="location", type="string"),
     *                     @OA\Property(property="shape", type="string"),
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Tables retrieved successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
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

        // Fetch unique dining space locations
        $uniqueLocations = DiningSpaceLocation::select('id', 'name')
            ->where('restaurant_id', $venue->id)
            ->distinct()
            ->get();


        $tables = Table::with('diningSpaceLocation')->select('tables.id', 'dining_space_location_id', 'number', 'tables.name', 'size', 'seats', 'shape', 'tables.created_at')
            ->leftJoin('dining_space_locations', 'tables.dining_space_location_id', '=', 'dining_space_locations.id')
            ->where('tables.restaurant_id',  $venue->id)
            ->get()
            ->map(function ($table) {
                return [
                    'id' => $table->id,
                    'number' => $table->number,
                    'name' => $table->name,
                    'size' => $table->size,
                    'seats' => $table->seats,
                    'location' => $table->diningSpaceLocation?->name,
                    'location_id' => $table->diningSpaceLocation?->id,
                    'shape' => $table->shape,
                    'status' => 'available',
                    'added_at' => date('F j, Y \a\t H:i', strtotime($table->created_at))
                ];
            });

        // Combine tables and unique dining space locations in the response
        $response = [
            'tables' => $tables,
            'unique_locations' => $uniqueLocations,
        ];
        return response()->json($response, 200);
    }

    /**
     * @OA\Get(
     *     path="/tables/shapes",
     *     summary="List all tables shapes",
     *     tags={"Table Management"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="enum", type="string"),
     *                     @OA\Property(property="shape", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Tables retrieved successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function shapes()
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

        $tableShapes = [
            [
                'id' => 1,
                'enum' => 'round',
                'shape' => 'Round',
                'description' => 'Good for conversation and encourages intimacy. Ideal for small to medium-sized groups.'
            ],
            [
                'id' => 2,
                'enum' => 'square',
                'shape' => 'Square',
                'description' => 'Great for group seating and easy to push together. Allows for a variety of seating configurations.'
            ],
            [   'id' => 3,
                'enum' => 'rectangular',
                'shape' => 'Rectangular',
                'description' => 'Can accommodate larger groups and encourages conversation between people sitting across from each other.'
            ],
            [
                'id' => 4,
                'enum' => 'oval',
                'shape' => 'Oval',
                'description' => 'Similar to round tables, but can seat more people. Ideal for family-style dining.'
            ],
            [
                'id' => 5,
                'enum' => 'booth',
                'shape' => 'Booths',
                'description' => 'Comfortable and private seating option that encourages intimacy. Great for couples and small groups.'
            ],
            [
                'id' => 6,
                'enum' => 'communal',
                'shape' => 'Communal',
                'description' => 'Promotes a sense of community and encourages conversation between strangers. Ideal for large groups and busy restaurants.'
            ],
            [
                'id' => 7,
                'enum' => 'bar',
                'shape' => 'Bar',
                'description' => 'Allows for casual seating and promotes social interaction with bartenders and other customers. Great for solo diners and small groups.'
            ],
            [
                'id' => 8,
                'enum' => 'bar',
                'shape' => 'High-Top or Pub Table',
                'description' => 'Allows for casual seating and promotes social interaction with bartenders and other customers. Great for solo diners and small groups.'
            ],
            [
                'id' => 9,
                'enum' => 'u-shape',
                'shape' => 'U-shape',
                'description' => 'U-shaped tables are typically used for larger groups or events, and can help to create a more inclusive dining experience. They consist of several tables arranged in a U-shape, with guests seated around the perimeter.'
            ],
            [
                'id' => 10,
                'enum' => 'horseshoe',
                'shape' => 'Horseshoe',
                'description' => 'Similar to the U-shape, horseshoe tables are used for events and meetings. They allow for better interaction among participants.'
            ],
            [
                'id' => 11,
                'enum' => 'round',
                'shape' => 'Cocktail Tables',
                'description' => 'Tall, small tables used for standing and mingling. They are often found at cocktail parties and events.'
            ],
            [
                'id' => 12,
                'enum' => 'round',
                'shape' => "Kid's Table",
                'description' => 'Smaller, kid-sized tables and chairs for families with children. These tables are designed to make dining more comfortable for kids.'
            ],
            [
                'id' => 13,
                'enum' => 'octagonal',
                'shape' => "Octagonal",
                'description' => 'Octagonal tables have eight sides and are suitable for larger groups or events. They provide a sense of exclusivity.'
            ],
            [
                'id' => 14,
                'enum' => 'picnic',
                'shape' => "Picnic",
                'description' => 'Outdoor seating options often found in parks or casual dining areas. They are ideal for communal dining.'
            ],
            [
                'id' => 15,
                'enum' => 'hexagonal',
                'shape' => "Hexagonal",
                'description' => 'Hexagonal tables have six sides and can be arranged in various configurations. They are unique and can accommodate different group sizes.'
            ],
            [
                'id' => 16,
                'enum' => 'triangle',
                'shape' => "Triangle",
                'description' => 'Triangle-shaped tables are often used in cozy corners or for smaller groups. They encourage conversation between guests.'
            ],
            [
                'id' => 17,
                'enum' => 'round',
                'shape' => "Outdoor Bistro Table",
                'description' => 'Small, often round or square tables used on outdoor patios for intimate dining.'
            ],
            [
                'id' => 18,
                'enum' => 'conference',
                'shape' => 'Conference Tables',
                'description' => 'Large, rectangular or oval tables used for meetings or events. They can accommodate a significant number of people.'
            ],
        ];
        return response()->json($tableShapes, 200);
    }

    /**
     * @OA\Put(
     *     path="/tables/move",
     *     summary="Move a table to a new location",
     *     tags={"Table Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="table_id",
     *                 type="integer",
     *                 example="1"
     *             ),
     *             @OA\Property(
     *                 property="new_location",
     *                 type="string",
     *                 example="Room 1"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Table moved successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Table not found",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function moveTable(Request $request)
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
            'table_id' => 'required|exists:tables,id',
            'new_location' => 'required|integer|exists:dining_space_locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $table = Table::find($request->input('table_id'));

        if (!$table || $table->restaurant_id != $venue->id) {
            return response()->json(['error' => 'Table not found'], 404);
        }

        $table->dining_space_location_id = $request->input('new_location');
        $table->save();

        return response()->json(['message' => 'Table moved successfully'], 200);
    }

    /**
     * @OA\Patch(
     *     path="/tables/merge",
     *     summary="Merge multiple tables into a single table",
     *     tags={"Table Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="table_numbers",
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer"
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="new_table_number",
     *                 type="integer"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Tables merged successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function mergeTables(Request $request)
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
            'table_numbers' => 'required|array',
            'table_numbers.*' => 'required|string|exists:tables,number,restaurant_id,' . $venue->id,
            'new_table_number' => 'required|string|unique:tables,number'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Retrieve the tables to be merged
        $tables = Table::whereIn('number', $request->input('table_numbers'))->get();

        $table = new Table();
        $table->merge($request->input('new_table_number'), $tables);

        return response()->json([
            'message' => 'Tables merged successfully'
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/tables/split",
     *     summary="Split a table",
     *     tags={"Table Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="table_id",
     *                 type="integer"
     *             ),
     *             @OA\Property(
     *                 property="new_table_numbers",
     *                 type="array",
     *                 @OA\Items(type="integer")
     *             ),
     *             @OA\Property(
     *                 property="new_table_seats",
     *                 type="array",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Table split successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Table not found",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function splitTable(Request $request)
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
            'table_id' => 'required|exists:tables,id',
            'new_table_numbers' => 'required|array|min:1',
            'new_table_numbers.*' => 'required|string|unique:tables,number',
            'new_table_seats' => 'required|array|min:1',
            'new_table_seats.*' => 'required|integer'

        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $table = Table::find($request->input('table_id'));

        if ($table->seats < array_sum($request->input('new_table_seats'))) {
            return response()->json(['error' => 'The number of seats in the new tables cannot be greater than the number of seats in the original table'], 400);
        }

        if (!$table || $table->restaurant_id != $venue->id) {
            return response()->json(['error' => 'Table not found'], 404);
        }

        // Create the new tables
        $newTables = collect($request->input('new_table_numbers'))->map(function($number, $index) use($table, $request) {
            return new Table([
                'number' => $number,
                'seats' => $request->input('new_table_seats')[$index],
                'shape' => $table->shape,
                'location' => $table->diningSpaceLocation?->name,
                'restaurant_id' => $table->restaurant_id
            ]);
        });

        // Persist the new tables
        $table->split($table->number, $newTables);

        // TODO: after v1 testing split history concept and is_splited column in tables table and the parent concept

        return response()->json([
            'message' => 'Table split successfully done'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/tables/assign-guests",
     *     summary="Assign guests to a table",
     *     tags={"Table Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="table_id",
     *                 type="integer"
     *             ),
     *             @OA\Property(
     *                 property="guest_ids",
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Guests assigned to table successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Table not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function assignGuests(Request $request)
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
        // Validate the input
        $validator = Validator::make($request->all(), [
            'table_id' => 'required|exists:tables,id',
            'guest_ids' => 'required|array',
            'guest_ids.*' => 'required|exists:guests,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Retrieve the table and the guests
        $table = Table::where('id', $request->input('table_id'))->first();

        if (!$table || $table->restaurant_id != $venue->id) {
            return response()->json(['error' => 'Table not found'], 404);
        }

        $guests = Guest::whereIn('id', $request->input('guest_ids'))->get();
        $mainGuest = $guests->where('is_main', 1)->first();

        if($mainGuest && count($mainGuest->reservations) > 0 && $mainGuest->reservations->first()->id) {
            $reservation = Reservation::find($mainGuest->reservations->first()->id);
            $reservation->table_id = $table->id;
            $reservation->save();
        }

        return response()->json(['message' => 'Guests assigned successfully']);
    }

    /**
     * @OA\Post(
     *     path="/tables",
     *     summary="Add a new table",
     *     description="Add a new table to the restaurant",
     *     operationId="addTable",
     *     tags={"Table Management"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *        @OA\Property(
     *            property="name",
     *       type="string",
     *     example="Table 1"
     *       ),
     *     @OA\Property(
     *     property="seats",
     *     type="integer",
     *     example=4
     *     ),
     *     @OA\Property(
     *     property="shape",
     *     type="string",
     *     example="square"
     *    ),
     *     @OA\Property(
     *     property="location",
     *     type="string",
     *     example="inside"
     *    ),
     *     @OA\Property(
     *     property="number",
     *     type="string",
     *      example="001"
     *   ),
     * )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *     @OA\Property(
     *     property="message",
     *     type="string",
     *     example="Table created successfully"
     *    ),
     * )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function create(Request $request)
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
            'name' => 'required|string|max:255',
            'number' => 'required|string|unique:tables,number',
            'size' => 'required|in:small,medium,large',
            'seats' => 'required|integer|min:1',
            'location_id' => 'required|integer|exists:dining_space_locations,id,restaurant_id,' . $venue->id,
            'shape' => 'required|integer|min:1',
            // 'shape' => 'required|in:round,rectangular,square,booth,bar,u-shape,communal,oval,picnic,hexagonal,triangle,conference,octagonal,horseshoe'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Additional validation for pricing, premium_table_bid, min_bid, and max_bid
        if ($request->has('has_pricing') && $request->input('has_pricing')) {
            $validator = Validator::make($request->all(), [
                'pricing' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }
        }

        if ($request->has('show_premium_table_bid') && $request->input('show_premium_table_bid')) {
            $validator = Validator::make($request->all(), [
                'premium_table_bid' => 'required',
                'min_bid' => 'required|numeric',
                'max_bid' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }
        }

        $tableShapes = [
            [
                'id' => 1,
                'enum' => 'round',
                'shape' => 'Round',
                'description' => 'Good for conversation and encourages intimacy. Ideal for small to medium-sized groups.'
            ],
            [
                'id' => 2,
                'enum' => 'square',
                'shape' => 'Square',
                'description' => 'Great for group seating and easy to push together. Allows for a variety of seating configurations.'
            ],
            [   'id' => 3,
                'enum' => 'rectangular',
                'shape' => 'Rectangular',
                'description' => 'Can accommodate larger groups and encourages conversation between people sitting across from each other.'
            ],
            [
                'id' => 4,
                'enum' => 'oval',
                'shape' => 'Oval',
                'description' => 'Similar to round tables, but can seat more people. Ideal for family-style dining.'
            ],
            [
                'id' => 5,
                'enum' => 'booth',
                'shape' => 'Booths',
                'description' => 'Comfortable and private seating option that encourages intimacy. Great for couples and small groups.'
            ],
            [
                'id' => 6,
                'enum' => 'communal',
                'shape' => 'Communal',
                'description' => 'Promotes a sense of community and encourages conversation between strangers. Ideal for large groups and busy restaurants.'
            ],
            [
                'id' => 7,
                'enum' => 'bar',
                'shape' => 'Bar',
                'description' => 'Allows for casual seating and promotes social interaction with bartenders and other customers. Great for solo diners and small groups.'
            ],
            [
                'id' => 8,
                'enum' => 'bar',
                'shape' => 'High-Top or Pub Table',
                'description' => 'Allows for casual seating and promotes social interaction with bartenders and other customers. Great for solo diners and small groups.'
            ],
            [
                'id' => 9,
                'enum' => 'u-shape',
                'shape' => 'U-shape',
                'description' => 'U-shaped tables are typically used for larger groups or events, and can help to create a more inclusive dining experience. They consist of several tables arranged in a U-shape, with guests seated around the perimeter.'
            ],
            [
                'id' => 10,
                'enum' => 'horseshoe',
                'shape' => 'Horseshoe',
                'description' => 'Similar to the U-shape, horseshoe tables are used for events and meetings. They allow for better interaction among participants.'
            ],
            [
                'id' => 11,
                'enum' => 'round',
                'shape' => 'Cocktail Tables',
                'description' => 'Tall, small tables used for standing and mingling. They are often found at cocktail parties and events.'
            ],
            [
                'id' => 12,
                'enum' => 'round',
                'shape' => "Kid's Table",
                'description' => 'Smaller, kid-sized tables and chairs for families with children. These tables are designed to make dining more comfortable for kids.'
            ],
            [
                'id' => 13,
                'enum' => 'octagonal',
                'shape' => "Octagonal",
                'description' => 'Octagonal tables have eight sides and are suitable for larger groups or events. They provide a sense of exclusivity.'
            ],
            [
                'id' => 14,
                'enum' => 'picnic',
                'shape' => "Picnic",
                'description' => 'Outdoor seating options often found in parks or casual dining areas. They are ideal for communal dining.'
            ],
            [
                'id' => 15,
                'enum' => 'hexagonal',
                'shape' => "Hexagonal",
                'description' => 'Hexagonal tables have six sides and can be arranged in various configurations. They are unique and can accommodate different group sizes.'
            ],
            [
                'id' => 16,
                'enum' => 'triangle',
                'shape' => "Triangle",
                'description' => 'Triangle-shaped tables are often used in cozy corners or for smaller groups. They encourage conversation between guests.'
            ],
            [
                'id' => 17,
                'enum' => 'round',
                'shape' => "Outdoor Bistro Table",
                'description' => 'Small, often round or square tables used on outdoor patios for intimate dining.'
            ],
            [
                'id' => 18,
                'enum' => 'conference',
                'shape' => 'Conference Tables',
                'description' => 'Large, rectangular or oval tables used for meetings or events. They can accommodate a significant number of people.'
            ],
        ];

        $tableShape = collect($tableShapes)->where('id', $request->input('shape'))->first();
        $table = new Table();
        $table->name = $request->input('name');
        $table->number = $request->input('number');
        $table->restaurant_id = $venue->id;
        $table->size = $request->input('size');
        $table->seats = $request->input('seats');
        $table->dining_space_location_id = $request->input('location_id');
        $table->shape = $tableShape['enum'];

        // Set pricing, premium_table_bid, min_bid, and max_bid based on request values
        if ($request->has('has_pricing')) {
            $table->pricing = $request->input('has_pricing') ? $request->input('pricing') : null;
        }

        if ($request->has('show_premium_table_bid')) {
            $table->show_premium_table_bid = $request->input('show_premium_table_bid');
            $table->premium_table_bid = $request->input('show_premium_table_bid') ? $request->input('premium_table_bid') : null;
            $table->min_bid = $request->input('show_premium_table_bid') ? $request->input('min_bid') : null;
            $table->max_bid = $request->input('show_premium_table_bid') ? $request->input('max_bid') : null;
        }

        $table->save();

        $table = Table::with('diningSpaceLocation')->find($table->id);

        return response()->json($table, 200);
    }

    /**
     * @OA\Get(
     *     path="/tables/dining-space-locations",
     *     summary="Get all dining space locations of the restaurant",
     *     description="Get all dining space locations of the restaurant",
     *     operationId="getDiningSpaceLocations",
     *     tags={"Table Management"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *     response=200,
     *     description="Success",
     *     @OA\JsonContent(
     *     @OA\Property(
     *     property="id",
     *     type="integer",
     *     example=1
     *     ),
     *     @OA\Property(
     *     property="name",
     *     type="string",
     *     example="Inside"
     *    ),
     *     @OA\Property(
     *     property="description",
     *     type="string",
     *     example="Inside the restaurant"
     *   ),
     *     )
     *    ),
     *     @OA\Response(
     *     response=400,
     *     description="Bad request"
     *   ),
     *     @OA\Response(
     *     response=401,
     *     description="Unauthorized"
     *  ),
     * )
     *
     */
    public function diningSpaceLocations(): \Illuminate\Http\JsonResponse
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

        $locations = DiningSpaceLocation::where('restaurant_id', $venue->id)->get()->map(function ($diningSpaceLocation) {
            return [
                'id' => $diningSpaceLocation->id,
                'name' => $diningSpaceLocation->name,
                'description' => $diningSpaceLocation->description,
                'added_at' => date('F j, Y \a\t H:i', strtotime($diningSpaceLocation->created_at))
            ];
        });;

        return response()->json($locations);
    }

    /**
     * @OA\Post(
     *     path="/tables/dining-space-locations",
     *     summary="Create a new dining space location for the restaurant",
     *     tags={"Table Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string", example="Main Dining Room"),
     *                 @OA\Property(property="description", type="string", example="This is the main dining area of the restaurant"),
     *                 required={"name"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Dining space location created successfully",
     *         @OA\JsonContent(
     *            @OA\Property(property="message", type="string", example="Dining space location created successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function createDiningSpaceLocations(Request $request): \Illuminate\Http\JsonResponse
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $location = new DiningSpaceLocation();
        $location->name = $request->input('name');
        $location->description = $request->input('description');
        $location->restaurant_id = $venue->id;
        $location->save();

        return response()->json(['data' => $location], 201);
    }


    /**
     * @OA\Get(
     *     path="/tables/details/{id}",
     *     summary="Get details of a specific table and its associated reservations and seating arrangements",
     *     tags={"Table Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the table to retrieve",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example="1"),
     *             @OA\Property(property="number", type="string", example="001"),
     *             @OA\Property(property="name", type="string", example="Table 1"),
     *             @OA\Property(property="restaurant_id", type="integer", example="1"),
     *             @OA\Property(property="size", type="string", example="small"),
     *             @OA\Property(property="seats", type="integer", example="4"),
     *             @OA\Property(property="location", type="string", example="Outdoor patio"),
     *             @OA\Property(property="shape", type="string", example="round"),
     *             @OA\Property(property="created_at", type="string", example="April 25, 2023 at 12:52:56"),
     *             @OA\Property(property="updated_at", type="string", example="April 25, 2023 at 12:53:11"),
     *             @OA\Property(
     *                 property="reservations",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example="1"),
     *                     @OA\Property(property="guest_id", type="integer", example="1"),
     *                     @OA\Property(property="table_id", type="integer", example="1"),
     *                     @OA\Property(property="start_time", type="string", example="April 25, 2023 at 13:00:00"),
     *                     @OA\Property(property="end_time", type="string", example="April 25, 2023 at 14:30:00"),
     *                     @OA\Property(property="created_at", type="string", example="April 25, 2023 at 12:54:11"),
     *                     @OA\Property(property="updated_at", type="string", example="April 25, 2023 at 12:54:11"),
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="seating_arrangements",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example="1"),
     *                     @OA\Property(property="name", type="string", example="Window seat"),
     *                     @OA\Property(property="description", type="string", example="Seat next to the window with a view"),
     *                     @OA\Property(property="created_at", type="string", example="April 25, 2023 at 12:55:11"),
     *                     @OA\Property(property="updated_at", type="string", example="April 25, 2023 at 12:55:
        *                     11"),
     *            )
     *       )
     *    )
     * )
     *    ),
     *  )
     * )
     */
    public function details($tableId)
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


        $table = Table::with('seatingArrangements', 'reservations')->findOrFail($tableId);

        if($table->restaurant_id != $venue->id) {
            return response()->json(['error' => 'Table not found'], 404);
        }

        $reservations = $table->reservations->map(function ($reservation) {
            return $reservation;
        });

        $seatingArrangements = $table->seatingArrangements->map(function ($seatingArrangement) use ($table) {
            $guestIds = json_decode($seatingArrangement->guest_ids);
            $guests = Guest::whereIn('id', $guestIds)->get();
            $seatingArrangement->guests = $guests;

            return [
                'id' => $seatingArrangement->id,
                'guest_name' => $seatingArrangement->start_time,
                'time' => explode(' ', $seatingArrangement->start_time)[1] .' - '.explode(' ', $seatingArrangement->end_time)[1],
                'end_time' => $seatingArrangement->end_time,
                'guests' => $guests,
                'reservation_id' => $seatingArrangement->guests->first()?->reservations?->first()->id,
                'dining_location' => $table->diningSpaceLocation?->name,
            ];
        });


        $locationToReturn = new StdClass();
        $locationToReturn->id = $table->diningSpaceLocation?->id;
        $locationToReturn->name = $table->diningSpaceLocation?->name;

        $data = [
            'id' => $table->id,
            'number' => $table->number,
            'name' => $table->name,
            'size' => $table->size,
            'seats' => $table->seats,
            'location' => $table->diningSpaceLocation?->name,
            'locationForMove' => $locationToReturn,
            'shape' => $table->shape,
            'seating_arrangements' => $seatingArrangements,
            'reservations' => $reservations,
            'show_table_name' => $table->show_table_name,
            'show_table_number' => $table->show_table_number,
            'show_floorplan' => $table->show_floorplan,
            'pricing' => $table->pricing,
            'show_premium_table_bid' => $table->show_premium_table_bid,
            'premium_table_bid' => $table->premium_table_bid,
            'min_bid' => $table->min_bid,
            'max_bid' => $table->max_bid,
        ];

        return response()->json(['data' => $data]);
    }


    /**
     * @OA\Get(
     *     path="/api/tables/available",
     *     summary="Get all available tables",
     *     description="Returns a list of all tables that are available for reservation during the specified time period",
     *     operationId="getAvailableTables",
     *     tags={"Table Management"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="start_time",
     *         in="query",
     *         description="The start time of the reservation in format Y-m-d H:i:s",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date-time"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="end_time",
     *         in="query",
     *         description="The end time of the reservation in format Y-m-d H:i:s",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date-time"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     description="The ID of the table"
     *                 ),
     *                 @OA\Property(
     *                     property="number",
     *                     type="string",
     *                     description="The number of the table"
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     description="The name of the table"
     *                 ),
     *                 @OA\Property(
     *                     property="size",
     *                     type="string",
     *                     description="The size of the table"
     *                 ),
     *                 @OA\Property(
     *                     property="seats",
     *                     type="integer",
     *                     description="The number of seats at the table"
     *                 ),
     *                 @OA\Property(
     *                     property="location",
     *                     type="string",
     *                     description="The location of the table"
     *                 ),
     *                 @OA\Property(
     *                     property="shape",
     *                     type="string",
     *                     description="The shape of the table"
     *                 ),
     *                 @OA\Property(
     *                     property="added_at",
     *                     type="string",
     *                     description="The date and time when the table was added"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 description="The error message"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="The error message"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable entity",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 description="The validation errors"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *            type="string",
     *            description="The error message"
     *            )
     *        )
     *    )
     * )
     */
    public function getAvailableTables(Request $request)
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

            'start_time' => 'required|date_format:Y-m-d H:i:s',
            'end_time' => 'required|date_format:Y-m-d H:i:s|after:start_time'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $startTime = strtotime($request->input('start_time'));
        $endTime = strtotime($request->input('end_time'));


        $tables = Table::with('diningSpaceLocation')
            ->where('restaurant_id', $venue->id)
            ->whereDoesntHave('reservations', function ($query) use ($startTime, $endTime) {
                $query->where(function ($query) use ($startTime, $endTime) {
                    $query->where('start_time', '>=', date('Y-m-d H:i:s', $startTime))
                        ->where('start_time', '<', date('Y-m-d H:i:s', $endTime));
                })
                    ->orWhere(function ($query) use ($startTime, $endTime) {
                        $query->where('end_time', '>', date('Y-m-d H:i:s', $startTime))
                            ->where('end_time', '<=', date('Y-m-d H:i:s', $endTime));
                    })
                    ->orWhere(function ($query) use ($startTime, $endTime) {
                        $query->where('start_time', '<', date('Y-m-d H:i:s', $startTime))
                            ->where('end_time', '>', date('Y-m-d H:i:s', $startTime));
                    })
                    ->orWhere(function ($query) use ($startTime, $endTime) {
                        $query->where('start_time', '<', date('Y-m-d H:i:s', $endTime))
                            ->where('end_time', '>', date('Y-m-d H:i:s', $endTime));
                    });
            })
            ->get()
            ->map(function ($table) {
                return [
                    'id' => $table->id,
                    'number' => $table->number,
                    'name' => $table->name,
                    'size' => $table->size,
                    'seats' => $table->seats,
                    'location' => $table->diningSpaceLocation?->name,
                    'shape' => $table->shape,
                    'added_at' => date('F j, Y \a\t H:i', strtotime($table->created_at))
                ];
            });

        return response()->json(['data' => $tables]);
    }

    public function webAvailableTables(Request $request)
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'start_time' => 'required|date_format:Y-m-d H:i:s'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $start_time = $request->input('start_time');
        $end_time = Carbon::parse($start_time)->addHours(2)->toDateString();

        $startTime = strtotime($start_time);
        $endTime = strtotime($end_time);


        $tables = Table::with('diningSpaceLocation')
            ->where('restaurant_id', $venue->id)
            ->whereDoesntHave('reservations', function ($query) use ($startTime, $endTime) {
                $query->where(function ($query) use ($startTime, $endTime) {
                    $query->where('start_time', '>=', date('Y-m-d H:i:s', $startTime))
                        ->where('start_time', '<', date('Y-m-d H:i:s', $endTime));
                })
                    ->orWhere(function ($query) use ($startTime, $endTime) {
                        $query->where('end_time', '>', date('Y-m-d H:i:s', $startTime))
                            ->where('end_time', '<=', date('Y-m-d H:i:s', $endTime));
                    })
                    ->orWhere(function ($query) use ($startTime, $endTime) {
                        $query->where('start_time', '<', date('Y-m-d H:i:s', $startTime))
                            ->where('end_time', '>', date('Y-m-d H:i:s', $startTime));
                    })
                    ->orWhere(function ($query) use ($startTime, $endTime) {
                        $query->where('start_time', '<', date('Y-m-d H:i:s', $endTime))
                            ->where('end_time', '>', date('Y-m-d H:i:s', $endTime));
                    });
            })
            ->get()
            ->map(function ($table) {
                return [
                    'id' => $table->id,
                    'number' => $table->number,
                    'name' => $table->name,
                    'size' => $table->size,
                    'seats' => $table->seats,
                    'location' => $table->diningSpaceLocation?->name,
                    'shape' => $table->shape,
                    'added_at' => date('F j, Y \a\t H:i', strtotime($table->created_at))
                ];
            });

        return response()->json(['data' => $tables]);
    }
}
