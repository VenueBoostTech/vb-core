<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\SeatingArrangement;
use App\Models\TableReservations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

class SeatingArrangementController extends Controller
{

    /**
     * @OA\Get(
     *     path="/tables/seating-arrangements",
     *     tags={"Table Management"},
     *     summary="List all seating arrangements of the restaurant",
     *     @OA\Response(response="200", description="List of seating arrangements"),
     * )
     */
    public function index()
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


        $seating_arrangements = SeatingArrangement::with('table')->where('restaurant_id', $venue->id)->get();
        foreach ($seating_arrangements as $seating_arrangement) {
            $guest_ids = str_replace(['[', ']'], '', $seating_arrangement->guest_ids);
            $guest_ids = explode(',', $guest_ids);
            $guests = Guest::whereIn('id', $guest_ids)->get();
            $mainGuest = $guests->where('is_main', 1)->first();

            if($mainGuest && count($mainGuest->reservations) > 0 && $mainGuest->reservations->first()->id) {
                $reservation = Reservation::find($mainGuest->reservations->first()->id);
                $seating_arrangement->reservation = $reservation;
            }
            $seating_arrangement->guests = $guests;
            $seating_arrangement->dining_location = $seating_arrangement->table->diningSpaceLocation?->name;
            $seating_arrangement->time = explode(' ', $seating_arrangement->start_time)[1] .' - '.explode(' ', $seating_arrangement->end_time)[1];
        }
        return response()->json([
            'data' => $seating_arrangements
        ]);
    }

    /**
     * @OA\Post(
     *     path="/tables/seating-arrangements",
     *     summary="Create a new seating arrangement",
     *     tags={"Table Management"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             type="object",
     *             required={"table_id", "guest_ids", "start_time", "end_time"},
     *             @OA\Property(property="table_id", type="integer", example=1),
     *             @OA\Property(property="guest_ids", type="array", @OA\Items(type="integer"), example={1, 2, 3}),
     *             @OA\Property(property="start_time", type="string", format="date-time", example="2022-01-01T12:00:00Z"),
     *             @OA\Property(property="end_time", type="string", format="date-time", example="2022-01-01T14:00:00Z"),
     *             @OA\Property(property="notes", type="string", example="Special requests or notes"),
     *         )
     *     ),
     *     @OA\Response(response="201", description="Seating arrangement created successfully"),
     *     @OA\Response(response="400", description="Validation error"),
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
                'table_id' => 'required|integer',
                'guest_ids' => 'required|array',
                'start_time' => 'required|date_format:Y-m-d H:i',
                'end_time' => 'required|date_format:Y-m-d H:i',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $mainGuest = Guest::with(['reservations' => function ($query) {
            $query->whereNull('table_id');
        }])
            ->where('id',($request->guest_ids)[0])
            ->first();

        $seatingArrangement = new SeatingArrangement();
        $seatingArrangement->table_id = $request->table_id;
        $seatingArrangement->guest_ids = json_encode( $request->guest_ids);
        $seatingArrangement->start_time = $request->start_time;
        $seatingArrangement->end_time = $request->end_time;
        $seatingArrangement->restaurant_id = $venue->id;
        $seatingArrangement->save();

        if($mainGuest && count($mainGuest->reservations) > 0 && $mainGuest->reservations->first()->id) {

            Reservation::where('id', $mainGuest->reservations->first()->id)->update(['table_id' => $seatingArrangement->table_id]);
            Reservation::where('id', $mainGuest->reservations->first()->id)->update(['seating_arrangement' => $seatingArrangement->id]);
            $reservation = Reservation::find($mainGuest->reservations->first()->id);
        } else {
            return response()->json(['error' => 'bad request data'], 400);
        }

        TableReservations::create(
            [
                'table_id' => $seatingArrangement->table_id,
                'reservation_id' => $reservation->id,
                'start_time' => $seatingArrangement->start_time,
                'end_time' => $seatingArrangement->end_time,
            ]
        );

        return response()->json(['message' => 'Seating arrangement created successfully']);
    }

    /**
     * @OA\Put(
     *     path="/tables/seating-arrangements",
     *     summary="Update an existing seating arrangement",
     *     tags={"Table Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the seating arrangement to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="table_id", type="integer", example=1),
     *             @OA\Property(property="guest_ids", type="array", @OA\Items(type="integer"), example={1, 2, 3}),
     *             @OA\Property(property="start_time", type="string", format="date-time", example="2022-01-01T12:00:00Z"),
     *             @OA\Property(property="end_time", type="string", format="date-time", example="2022-01-01T14:00:00Z"),
     *             @OA\Property(property="notes", type="string", example="Special requests or notes"),
     *         )
     *     ),
     *     @OA\Response(response="200", description="Seating arrangement updated successfully"),
     *     @OA\Response(response="404", description="Seating arrangement not found"),
     *     @OA\Response(response="400", description="Validation error"),
     * )
     */
    public function update(Request $request)
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
            'id' => 'required|integer',
            'table_id' => 'required|integer',
        ]);

        $seatingArrangement = SeatingArrangement::find($request->input('id'));


        if (!$seatingArrangement) {
            return response()->json(['message' => 'The requested Seating Arrangement does not exist'], 404);
        }

        if($seatingArrangement->restaurant_id !== $venue->id) {
            return response()->json(['message' => 'The requested Seating Arrangement does not exist'], 404);
        }

        if($seatingArrangement->table_id === $request->input('table_id')) {
            return response()->json(['message' => 'Seating arrangement updated successfully']);
        }

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }


        $seatingArrangement->update($request->all());

        return response()->json(['message' => 'Seating arrangement updated successfully']);
    }

    /**
     * @OA\Delete(
     *     path="/seating-arrangements/{id}",
     *     tags={"Table Management"},
     *     summary="Delete an existing seating arrangement",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the seating arrangement to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response="200", description="Seating arrangement deleted successfully"),
     *     @OA\Response(response="404", description="Seating arrangement not found"),
     *     @OA\Response(response="400", description="Validation error"),
     * )
     */
    public function destroy(Request $request)
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

        $seatingArrangement = SeatingArrangement::find($request->input('id'));

        if (!$seatingArrangement) {
            return response()->json(['message' => 'The requested Seating Arrangement does not exist'], 404);
        }

        if($seatingArrangement->restaurant_id !== $venue->id) {
            return response()->json(['message' => 'The requested Seating Arrangement does not exist'], 404);
        }

        $seatingArrangement->delete();
        return response()->json(['message' => 'The Seating Arrangement was deleted successfully']);
    }

}
