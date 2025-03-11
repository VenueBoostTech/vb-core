<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\LoyaltyTier;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Promotion;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Str;
use stdClass;
use function response;

/**
 * @OA\Info(
 *   title="Guests API",
 *   version="1.0",
 *   description="This API allows users to retrieve and manage Guests.",
 * )
 */

/**
 * @OA\Tag(
 *   name="Guests",
 *   description="Operations related to Guests"
 * )
 */
class GuestsController extends Controller
{

    /**
     * @OA\Get(
     *     path="/guests",
     *     summary="Get a list of guests",
     *     tags={"Guests"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Guests retrieved successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                    type="object",
     *                    @OA\Property(property="id", type="integer"),
     *                    @OA\Property(property="name", type="string"),
     *                    @OA\Property(property="email", type="string"),
     *                    @OA\Property(property="address", type="string"),
     *                    @OA\Property(property="phone", type="string"),
     *                    @OA\Property(property="notes", type="string"),
     *                    @OA\Property(property="is_main", type="boolean"),
     *                    @OA\Property(property="sn_platform_user", type="integer"),
     *                 )
     *              )
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

        $defaultType = 'is_for_food_and_beverage';
        $type = request()->get('type', $defaultType);

        if ($type === 'is_for_accommodation') {
            $search = request()->get('search');
            $tierFilter = request()->get('tier');

            $guests = Guest::where([
                ['is_main', true],
                ['restaurant_id', $venue->id],
                [$type, true]
            ])
                ->when($search, function ($query) use ($search) {
                    return $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                })
                ->with(['wallet.loyaltyTier', 'user.receivedMessages', 'bookings'])
                ->orderBy('created_at', 'desc')
                ->get();

            $data = $guests->map(function($guest) use ($venue) {
                $lastBooking = $guest->bookings->sortByDesc('check_out_date')->first();
                $lastBookingDate = $lastBooking ? Carbon::parse($lastBooking->check_out_date)->setTimezone($venue->timezone) : null;
                $lastBookingStr = $lastBookingDate ? $this->formatDate($lastBookingDate, $venue->timezone) : 'No bookings yet';

                $lastCommunication = $guest->user?->receivedMessages->sortByDesc('created_at')->first();
                $lastCommunicationDate = $lastCommunication ? Carbon::parse($lastCommunication->created_at)->setTimezone($venue->timezone) : null;
                $lastCommunicationStr = $lastCommunicationDate ? $this->formatDate($lastCommunicationDate, $venue->timezone) : 'No communications yet';

                $notes = $this->generateNotes($guest);

                return [
                    'id' => $guest->id,
                    'user_id' => $guest->user_id,
                    'name' => $guest->name,
                    'email' => $guest->email,
                    'phone' => $guest->phone,
                    'registered_date' => $this->formatDate($guest->created_at, $venue->timezone)['exact'],
                    'notes' => $notes,
                    'points' => $guest->wallet ? $guest->wallet->balance : 0,
                    'tier_name' => $guest->wallet && $guest->wallet->loyaltyTier ? $guest->wallet->loyaltyTier->name : 'No tier',
                    'last_touchpoint' => $lastBookingStr,
                    'last_communication' => $lastCommunicationStr,
                ];
            });

            if ($tierFilter) {
                $data = $data->filter(function ($guest) use ($tierFilter) {
                    return $guest['tier_name'] === $tierFilter;
                });
            }

            return response()->json(['message' => 'Guests retrieved successfully', 'data' => $data->values()], 200);
        }

        $guests = Guest::where([
            ['is_main', true],
            ['restaurant_id', $venue->id],
            [$type, true]
        ])->orderBy('created_at', 'desc')->get();

        return response()->json(['message' => 'Guests retrieved successfully', 'data' => $guests], 200);
    }


    public function guestsWihoutTableReservations() {
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

        $reservations = Reservation::whereDoesntHave('table')
            ->whereHas('restaurant', function ($query) use ($venue) {
                $query->where('id', $venue->id);
            })
            ->with('guests')
            ->get();

        $guests = collect([]);

        foreach ($reservations as $reservation) {
            $guests = $guests->merge($reservation->guests);
        }

        return response()->json(['data' => $guests], 200);

    }

    public function show($id): \Illuminate\Http\JsonResponse
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

        $guest = Guest::where([
            ['is_main', true],
            ['restaurant_id', $venue->id],
        ])->with(['wallet.loyaltyTier', 'user.receivedMessages', 'bookings.rentalUnit'])->find($id);

        if (!$guest) {
            return response()->json(['error' => 'Guest not found'], 404);
        }

        $firstRegistration = $this->formatDateForGuest($guest->created_at, $venue->timezone);

        $firstBooking = $guest->bookings->sortBy('check_in_date')->first();
        $firstBookingDate = $firstBooking ? $this->formatDateForGuest($firstBooking->check_in_date, $venue->timezone) : null;

        $totalBookings = $guest->bookings->count();

        $lastBooking = $guest->bookings->sortByDesc('check_out_date')->first();
        $lastTouchpoint = $lastBooking ? $this->formatDateForGuest($lastBooking->check_out_date, $venue->timezone) : null;

        $lastCommunication = $guest->user?->receivedMessages->sortByDesc('created_at')->first();
        $lastCommunicationDate = $lastCommunication ? $this->formatDateForGuest($lastCommunication->created_at, $venue->timezone) : null;

        $points = $guest->wallet ? $guest->wallet->balance : 0;
        $tier = $guest->wallet && $guest->wallet->loyaltyTier ? $guest->wallet->loyaltyTier->name : 'No tier';

        $guestData = [
            'id' => $guest->id,
            'user_id' => $guest->user_id,
            'name' => $guest->name,
            'email' => $guest->email,
            'phone' => $guest->phone,
            'registered_date' => $firstRegistration['exact'],
            'first_booking' => $firstBookingDate ? $firstBookingDate['exact'] : 'No bookings yet',
            'total_bookings' => $totalBookings,
            'last_touchpoint' => $lastTouchpoint ? $lastTouchpoint['exact'] : 'No bookings yet',
            'last_communication' => $lastCommunicationDate ? $lastCommunicationDate['exact'] : 'No communications yet',
            'points' => $points,
            'tier' => $tier,
        ];

        $notes = $this->generateNotes($guest);

        $bookings = $guest->bookings->map(function($booking) use ($venue) {
            $checkInDate = Carbon::parse($booking->check_in_date);
            $checkOutDate = Carbon::parse($booking->check_out_date);

            return [
                'id' => $booking->id,
                'check_in_date' => $this->formatDateForGuest($checkInDate, $venue->timezone)['exact'],
                'check_out_date' => $this->formatDateForGuest($checkOutDate, $venue->timezone)['exact'],
                'rental_unit' => $booking->rentalUnit ? $booking->rentalUnit->name : 'N/A',
                'total_amount' => $booking->total_amount,
                'status' => $booking->status,
                'number_of_guests' => $booking->number_of_guests ?? 1,
                'nights' => $checkInDate->diffInDays($checkOutDate),
            ];
        })->sortByDesc('check_in_date')->values();

        return response()->json([
            'guest' => [
                'data' => $guestData,
                'notes' => $notes,
                'bookings' => $bookings,
            ],
            'message' => 'Guest retrieved successfully'
        ]);
    }

    private function formatDateForGuest($date, $timezone)
    {
        $carbonDate = Carbon::parse($date)->setTimezone($timezone);
        return [
            'exact' => $carbonDate->format('Y-m-d H:i:s'),
            'human' => $carbonDate->diffForHumans(),
        ];
    }

    public function storeGuest(Request $request): \Illuminate\Http\JsonResponse
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
            'email' => 'required|string|email|max:255|unique:guests',
            'phone' => 'required|string|max:15',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            // make hash random password
            'password' => Hash::make(Str::random(8)),
            'country_code' => 'US',
            'enduser' => true
        ]);

        $guest = Guest::create([
            'restaurant_id' => $venue->id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'notes' => $request->notes,
            'is_for_accommodation' => true,
            'is_main' => true,
            'created_manually' => true,
            'user_id' => $user->id
        ]);

        // get first tier based on name
        $tier = LoyaltyTier::where('name', 'Bronze Tier')->first();


        Wallet::create([
            'guest_id' => $guest->id,
            'venue_id' => $venue->id,
            'loyalty_tier_id' => $tier->id,
            'balance' => 0,
        ]);

        return response()->json(['message' => 'Guest created successfully', 'data' => $guest], 201);
    }

    private function formatDate(Carbon $date, string $venueTimezone): array
    {
        if ($date->timezone->getName() !== $venueTimezone) {
            $date = $date->setTimezone($venueTimezone);
        }
        $now = Carbon::now($venueTimezone);
        $diff = $now->diffInDays($date);

        if ($diff == 0) {
            $relativeDate = 'today';
        } elseif ($diff == 1) {
            $relativeDate = 'yesterday';
        } elseif ($diff < 7) {
            $relativeDate = $date->format('l');
        } elseif ($date->year === $now->year) {
            $relativeDate = $date->format('j F');
        } else {
            $relativeDate = "$diff days ago";
        }

        return [
            'relative' => $relativeDate,
            'exact' => $date->format('d/m/Y | h:i A')
        ];
    }

    private function generateNotes($guest): string
    {
        $now = Carbon::now();
        $notes = [];

        // Check if the guest is new
        if ($guest->created_at->diffInDays($now) < 30) {
            return 'New guest';
        }

        // Check if the guest is a serial booker
        $totalBookings = $guest->bookings()->count();
        if ($totalBookings > 5) { // Assuming more than 5 bookings makes a guest a serial booker
            return 'Serial booker';
        }

        // Check if the guest has made a recent booking
        $recentBooking = $guest->bookings()
            ->where('check_out_date', '>=', $now->subDays(30))
            ->orderBy('check_out_date', 'desc')
            ->first();
        if ($recentBooking) {
            return 'Recently made a booking';
        }

        // Default note if none of the above conditions are met
        return 'Regular guest';
    }


    /**
     * Delete a guest for OmniStack integration
     *
     * @param Request $request
     * @param int $id Guest ID
     * @return JsonResponse
     */
    public function destroyForOmnistack(Request $request, $id): JsonResponse
    {

        // Validate incoming request
        $validator = Validator::make($request->all(), [
            'omnigateway_api_key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Get the API key from the request
        $omnigatewayApiKey = $request->input('omnigateway_api_key');

        // Find the venue by the API key
        $venue = Restaurant::where('omnigateway_api_key', $omnigatewayApiKey)->first();

        if (!$venue) {
            return response()->json(['error' => 'Invalid API key or venue not found'], 401);
        }

        $guest = Guest::where('id', $id)
            ->where('restaurant_id', $venue->id)
            ->first();

        if (!$guest) {
            return response()->json(['error' => 'Guest not found'], 404);
        }

        // Check if guest has bookings
        if ($guest->bookings()->count() > 0) {
            $deleteWithBookings = $request->input('force_delete', false);

            if (!$deleteWithBookings) {
                return response()->json([
                    'error' => 'Guest has active bookings. Use force_delete=true to delete the guest and all associated bookings.',
                    'bookings_count' => $guest->bookings()->count()
                ], 422);
            }

            // Delete all associated bookings if force_delete is true
            foreach ($guest->bookings as $booking) {
                // Delete receipt and price breakdowns
                if ($booking->receipt) {
                    $booking->receipt->delete();
                }

                $booking->priceBreakdowns()->delete();
                $booking->delete();
            }
        }

        // Delete related records
        if ($guest->wallet) {
            $guest->wallet->delete();
        }

        $guest->earnPointsHistory()->delete();
        $guest->usePointsHistory()->delete();

        if ($guest->guestMarketingSettings) {
            $guest->guestMarketingSettings->delete();
        }

        // Optionally delete the associated user
        $deleteUser = $request->input('delete_user', false);
        if ($deleteUser && $guest->user_id) {
            $user = User::find($guest->user_id);
            if ($user) {
                // Only delete if this is the only guest record for the user
                $guestCount = Guest::where('user_id', $user->id)->count();
                if ($guestCount <= 1) {
                    $user->delete();
                }
            }
        }

        // Finally delete the guest
        $guest->delete();

        return response()->json([
            'message' => 'Guest deleted successfully',
            'user_deleted' => $deleteUser && $guest->user_id ? true : false
        ]);
    }


}
