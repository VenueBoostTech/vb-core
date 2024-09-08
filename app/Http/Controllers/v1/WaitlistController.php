<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Waitlist;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Twilio\Rest\Client;
use function env;
use function response;

/**
 * @OA\Info(
 *   title="Waitlist Management API",
 *   version="1.0",
 *   description="This API allows use Waitlist Management Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Waitlist Management",
 *   description="Operations related to Waitlist Management"
 * )
 */

class WaitlistController extends Controller
{

    /**
     * @OA\Get(
     *     path="/waitlists",
     *     summary="Get all waitlist entries",
     *     tags={"Waitlist Management"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="name", type="string", example="John Doe"),
     *                  @OA\Property(property="phone_number", type="string", example="555-555-5555"),
     *                  @OA\Property(property="email", type="string", example="johndoe@example.com"),
     *                  @OA\Property(property="party_size", type="integer", example=4),
     *                  @OA\Property(property="waitlist_time", type="string", format="date-time", example="2022-01-01T00:00:00Z"),
     *                  @OA\Property(property="reservation_id", type="integer", example=1)
     *            )
     *         ),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Waitlist entries not found",
     *     ),
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

        $waitlists = Waitlist::with(['reservation', 'guest'])
            ->where('restaurant_id', $venue->id)
            ->orderBy('created_at', 'desc') // Sort by the latest created date
            ->get();

        if (!$waitlists) {
            return response()->json(['error' => 'Waitlist entries not found'], 404);
        }
        return response()->json($waitlists);
    }

    public static function notifyBySMS($waitlist)
    {
        // Twilio account information
        $account_sid = env('TWILIO_ACCOUNT_SID');
        $auth_token = env('TWILIO_AUTH_TOKEN');
        $twilio_number = env('TWILIO_NUMBER');

        $client = new Client($account_sid, $auth_token);

        try {
            $client->messages->create(
                $waitlist->guest_phone,
                [
                    'from' => $twilio_number,
                    'body' => "Your table for " . $waitlist->party_size . " is ready at Restaurant! Please come to the host stand."
                ]
            );

            $waitlist->notified = true;
            $waitlist->guest_notified_at = Carbon::now();
            $waitlist->save();
            return true;
        } catch (Exception $e) {
            // log error
            return false;
        }
    }

    /**
     * @OA\Get(
     *   path="/waitlists/prioritize",
     *   summary="Prioritize guests on the waitlist",
     *   tags={"Waitlist Management"},
     *   @OA\Response(
     *       response=200,
     *       description="Successful operation",
     *       @OA\JsonContent(
     *          type="array",
     *          @OA\Items(
     *              @OA\Property(property="name", type="string"),
     *              @OA\Property(property="party_size", type="integer"),
     *              @OA\Property(property="estimated_wait_time", type="integer"),
     *              @OA\Property(property="is_vip", type="boolean"),
     *              @OA\Property(property="is_regular", type="boolean")
     *          )
     *      ),
     *    ),
     *   @OA\Response(
     *      response=404,
     *      description="Waitlist guests not found",
     *   ),
     * )
     */
    public function prioritizeWaitlist(Request $request) {

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

        $vipGuests = Waitlist::with(['reservation', 'guest'])->where('restaurant_id', $venue->id)->where('is_vip', true)->get();
        $regularGuests = Waitlist::with(['reservation', 'guest'])->where('restaurant_id', $venue->id)->where('is_regular', true)->get();
        $otherGuests = Waitlist::with(['reservation', 'guest'])->where('restaurant_id', $venue->id)->where('is_vip', false)->where('is_regular', false)->get();

        // Sort guests by party size
        $vipGuests = $vipGuests->sortByDesc('party_size');
        $regularGuests = $regularGuests->sortByDesc('party_size');
        $otherGuests = $otherGuests->sortByDesc('party_size');

        // Merge the three collections and re-index the keys
        $prioritizedGuests = $vipGuests->concat($regularGuests)->concat($otherGuests)->values();

        return response()->json($prioritizedGuests);
    }

    /**
     * @OA\Put(
     *   path="/waitlists/update-wait-time/{id}",
     *   summary="Update wait time for a specific guest on the waitlist",
     *   tags={"Waitlist Management"},
     *      @OA\Parameter(
     *           name="id",
     *           in="path",
     *           description="ID of the guest on the waitlist",
     *           required=true,
     *           @OA\Schema(
     *               type="integer"
     *           )
     *       ),
     *        @OA\RequestBody(
     *           required=true,
     *               @OA\JsonContent(
     *               @OA\Property(property="estimated_wait_time", type="integer"),
     *           )
     *       ),
     *       @OA\Response(
     *           response=200,
     *           description="Successful operation",
     *           @OA\JsonContent(
     *               @OA\Property(property="message", type="string", example="Wait time updated successfully.")
     *           )
     *       ),
     *       @OA\Response(
     *           response=404,
     *           description="Guest not found on the waitlist",
     *       ),
     *   )
    */
    public function updateWaitTime(Request $request, $id) {

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
            'estimated_wait_time' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $waitlist = Waitlist::find($id);

        if (!$waitlist || $waitlist->restaurant_id != $venue->id) {
            return response()->json(['error' => 'Waitlist entry not found'], 404);
        }

        $waitlist->estimated_wait_time = $request->input('estimated_wait_time');
        $waitlist->save();

        return response()->json($waitlist);
    }

    /**
     * @OA\Get(
     *      path="/waitlist/guests/{id}/history",
     *      operationId="guestsHistory",
     *      tags={"Waitlist Management"},
     *      summary="Get guests waitlist history",
     *      description="Returns the waitlist history for a specific guest, including the number of times they have been waitlisted, how long they have waited, and how many times they have been seated.",
     *      @OA\Parameter(
     *          name="id",
     *          description="Guest ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="guest",
     *                  type="object",
     *                  @OA\Property(
     *                      property="name",
     *                      type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="email",
     *                      type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="phone",
     *                      type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="address",
     *                      type="string"
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="waitlist_count",
     *                  type="integer"
     *              ),
     *              @OA\Property(
     *                  property="seated_count",
     *                  type="integer"
     *              ),
     *              @OA\Property(
     *                  property="total_wait_time",
     *                  type="integer"
     *              ),
     *              @OA\Property(
     *                  property="average_wait_time",
     *                  type="integer"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Guest not found"
     *      )
     * )
     */
    public function guestsHistory($id): \Illuminate\Http\JsonResponse
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

        $guest = Guest::find($id);

        if(!$guest || $guest->restaurant_id != $venue->id) {
            return response()->json(['error' => 'Guest not found'], 404);
        }

        $waitlistHistory = Waitlist::where('guest_id', $id)->where('restaurant_id', $venue->id)
            ->select('arrival_time', 'seat_time', 'left_time')
            ->get();

        $waitlistCount = $waitlistHistory->count();
        $totalWaitTime = 0;
        $seatedCount = 0;

        $waitlistHistory->transform(function ($waitlistHist) {
            $waitlistHist->arrival_time = Carbon::parse($waitlistHist->arrival_time);
            $waitlistHist->seat_time = Carbon::parse($waitlistHist->seat_time);
            return $waitlistHist;
        });

        foreach ($waitlistHistory as $history) {
            if (!is_null($history->seat_time)) {
                $seatedCount++;
                $totalWaitTime += $history->seat_time->diffInMinutes($history->arrival_time);
            }
        }
        $averageWaitTime = $seatedCount ? round($totalWaitTime / $seatedCount) : 0;

        return response()->json([
            'guest' => $guest,
            'waitlist_count' => $waitlistCount,
            'seated_count' => $seatedCount,
            'total_wait_time' => $totalWaitTime,
            'average_wait_time' => $averageWaitTime,
        ]);
    }


    /**
     * @OA\Post(
     *     path="/waitlist",
     *     tags={"Waitlist Management"},
     *     summary="Create a waitlist entry",
     *     @OA\RequestBody(
     *      required=true,
     *      description="Waitlist object that needs to be added to the system",
     *      @OA\JsonContent(
     *          required={"guest_id", "estimated_wait_time", "is_vip", "is_regular"},
     *          @OA\Property(property="guest_id", type="integer", example="1"),
     *          @OA\Property(property="estimated_wait_time", type="integer", example="23"),
     *          @OA\Property(property="is_vip", type="boolean", example="true"),
     *          @OA\Property(property="is_regular", type="boolean", example="false"),
     *     ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Waitlist created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Waitlist created successfully")
     *         )
     *     ),
     *     @OA\Response(response="400", description="Validation errors"),
     *     @OA\Response(response="401", description="Unauthorized"),
     *     @OA\Response(response="403", description="Forbidden"),
     *     @OA\Response(response="404", description="Not found"),
     *     @OA\Response(response="500", description="Internal server error")
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
            'guest_id' => 'required|integer|exists:guests,id',
            'estimated_wait_time' => 'required|integer',
            'is_vip' => 'required|boolean',
            'is_regular' => 'required|boolean',
        ]);

        if ($validator->fails() || $request->is_vip == $request->is_regular) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $mainGuest = Guest::with(['reservations' => function ($query) {
            $query->whereNull('table_id');
        }])
            ->where('id',($request->guest_id))
            ->first();

        if($mainGuest && count($mainGuest->reservations) > 0 && $mainGuest->reservations->first()->id) {

            $guestName = $mainGuest->name;
            $guestEmail = $mainGuest->email;
            $guestPhone = $mainGuest->phone;
            $reservationId = $mainGuest->reservations->first()->id;
            $addedAt = now();
            $reservation = Reservation::find($reservationId);
            $partySize = $reservation->guest_count;
        } else {
            return response()->json(['error' => 'bad request data'], 400);
        }

        // create the waitlist record
        $waitlist = new Waitlist();
        $waitlist->guest_id = $request->guest_id;
        $waitlist->guest_name = $guestName;
        $waitlist->guest_email = $guestEmail;
        $waitlist->guest_phone = $guestPhone;
        $waitlist->reservation_id = $reservationId;
        $waitlist->party_size = $partySize;
        $waitlist->estimated_wait_time = $request->estimated_wait_time;
        $waitlist->notified = false;
        $waitlist->added_at = $addedAt;
        $waitlist->guest_notified_at = null;
        $waitlist->is_vip = $request->is_vip;
        $waitlist->is_regular = $request->is_regular;
        $waitlist->restaurant_id = $venue->id;
        $waitlist->save();

        return response()->json(['message' => 'Waitlist created successfully']);
    }

    /**
     * @OA\Put(
     *     path="/waitlists",
     *     summary="Update an existing waitlist",
     *     tags={"Waitlist Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the waitlist to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="arrival_time", type="string", format="date-time", example="2022-01-01T12:00:00Z"),
     *             @OA\Property(property="seat_time", type="string", format="date-time", example="2022-01-01T14:00:00Z"),
     *             @OA\Property(property="left_time", type="string", format="date-time", example="2022-01-01T14:00:00Z"),
     *             @OA\Property(property="is_vip", type="boolean", example="true"),
     *             @OA\Property(property="is_regular", type="boolean", example="false"),
     *         )
     *     ),
     *     @OA\Response(response="200", description="Waitlist updated successfully"),
     *     @OA\Response(response="404", description="Waitlist not found"),
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
            'is_vip' => 'required|boolean',
            'is_regular' => 'required|boolean',
            'arrival_time' => 'date_format:Y-m-d H:i:s',
            'seat_time' => 'date_format:Y-m-d H:i:s',
            'left_time' => 'date_format:Y-m-d H:i:s',

        ]);

        $waitlist = Waitlist::find($request->input('id'));

        if (!$waitlist) {
            return response()->json(['message' => 'The requested Waitlist does not exist'], 404);
        }

        if($waitlist->restaurant_id !== $venue->id) {
            return response()->json(['message' => 'The requested Waitlist does not exist'], 404);
        }

        if ($validator->fails() || $request->is_vip == $request->is_regular) {
            return response()->json(['error' => $validator->errors()], 422);
        }


        $waitlist->update($request->all());

        return response()->json(['message' => 'Waitlist updated successfully']);
    }


    // TODO: after v1 testing add option to notify guest via email or sms manually

}
