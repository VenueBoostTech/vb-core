<?php

namespace App\Http\Controllers\v3\Accommodation;

use App\Http\Controllers\Controller;

use App\Models\Booking;
use App\Models\CalendarConnection;
use App\Models\ConnectionRefreshLog;
use App\Models\RentalUnit;
use App\Models\ThirdPartyBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use ICal\ICal;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

class CalendarConnectionController extends Controller
{
    private function validateVenueAndRentalUnit(Request $request, $rentalUnitId = null): \Illuminate\Http\JsonResponse|array
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        if ($rentalUnitId) {
            $rentalUnit = RentalUnit::where('id', $rentalUnitId)->where('venue_id', $venue->id)->first();
            if (!$rentalUnit) {
                return response()->json(['message' => 'The requested rental unit does not exist'], 404);
            }
        }

        return compact('venue', 'rentalUnit');
    }

    public function index(Request $request, $rentalUnitId): \Illuminate\Http\JsonResponse
    {
        $validation = $this->validateVenueAndRentalUnit($request, $rentalUnitId);
        if (!isset($validation['venue'])) {
            return $validation;
        }

        // Get the venue's timezone from the validated venue
        $venueTimezone = $validation['venue']->timezone;

        $connections = CalendarConnection::where('venue_id', $validation['venue']->id)
            ->where('rental_unit_id', $validation['rentalUnit']->id)
            ->get()
            ->map(function ($connection) use ($venueTimezone) {
                // Format last_synced in the venue's timezone
                $connection->formatted_last_synced = $connection->last_synced
                    ? Carbon::parse($connection->last_synced)->setTimezone($venueTimezone)->format('M d, g:i A')
                    : null;

                return $connection;
            });

        return response()->json($connections);
    }

    public function store(Request $request, $rentalUnitId): \Illuminate\Http\JsonResponse|array
    {
        $validation = $this->validateVenueAndRentalUnit($request, $rentalUnitId);
        if (!isset($validation['venue'])) {
            return $validation;
        }

        $validator = Validator::make($request->all(), [
            'connection_name' => 'required|string',
            'ics_link' => 'required|url',
            'type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $connection = CalendarConnection::create([
            'venue_id' => $validation['venue']->id,
            'rental_unit_id' => $validation['rentalUnit']->id,
            'connection_name' => $request->connection_name,
            'ics_link' => $request->ics_link,
            'type' => $request->type,
        ]);

        $syncResults = $this->syncBookings($connection);

        return response()->json([
            'connection' => $connection,
            'sync_results' => $syncResults
        ], 201);
    }

    public function update(Request $request, $rentalUnitId, $connectionId): \Illuminate\Http\JsonResponse|array
    {
        $validation = $this->validateVenueAndRentalUnit($request, $rentalUnitId);
        if (!isset($validation['venue'])) {
            return $validation;
        }

        $connection = CalendarConnection::where('id', $connectionId)
            ->where('venue_id', $validation['venue']->id)
            ->where('rental_unit_id', $validation['rentalUnit']->id)
            ->first();

        if (!$connection) {
            return response()->json(['error' => 'Calendar connection not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'connection_name' => 'string',
            'ics_link' => 'url',
            'type' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $connection->update($request->all());

        $syncResults = null;
        if ($request->has('ics_link')) {
            $syncResults = $this->syncBookings($connection);
        }

        return response()->json([
            'connection' => $connection,
            'sync_results' => $syncResults
        ]);
    }

    public function refresh(Request $request, $rentalUnitId, $connectionId): \Illuminate\Http\JsonResponse|array
    {
        $validation = $this->validateVenueAndRentalUnit($request, $rentalUnitId);
        if (!isset($validation['venue'])) {
            return $validation;
        }

        $connection = CalendarConnection::where('id', $connectionId)
            ->where('venue_id', $validation['venue']->id)
            ->where('rental_unit_id', $validation['rentalUnit']->id)
            ->first();

        if (!$connection) {
            return response()->json(['error' => 'Calendar connection not found'], 404);
        }

        try {
            $syncResults = $this->syncBookings($connection);

            return response()->json([
                'message' => 'Connection refreshed successfully',
                'last_synced' => $connection->last_synced,
                'status' => $connection->status,
                'sync_results' => $syncResults
            ]);
        } catch (\Exception $e) {
            Log::error('Calendar refresh failed: ' . $e->getMessage(), [
                'connection_id' => $connection->id,
                'ics_link' => $connection->ics_link
            ]);

            return response()->json([
                'error' => 'Failed to refresh calendar connection',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function refreshCron(Request $request, $rentalUnitId, $connectionId): \Illuminate\Http\JsonResponse|array
    {

        $rentalUnit = null;
        if ($rentalUnitId) {
            $rentalUnit = RentalUnit::where('id', $rentalUnitId)->first();
            if (!$rentalUnit) {
                return response()->json(['message' => 'The requested rental unit does not exist'], 404);
            }
        }

        $connection = CalendarConnection::where('id', $connectionId)
            ->where('rental_unit_id', $rentalUnitId)
            ->first();

        if (!$connection) {
            return response()->json(['error' => 'Calendar connection not found'], 404);
        }

        try {
            $syncResults = $this->syncBookings($connection);

            // Log the refresh attempt with the connection type
            ConnectionRefreshLog::create([
                'connection_id' => $connection->id,
                'venue_id' => $rentalUnit->venue_id,
                'rental_unit_id' => $rentalUnitId,
                'connection_type' => $connection->type,
                'status' => 'success',
                'message' => 'Connection refreshed successfully'
            ]);



            return response()->json([
                'message' => 'Connection refreshed successfully',
                'last_synced' => $connection->last_synced,
                'status' => $connection->status,
                'sync_results' => $syncResults
            ]);
        } catch (\Exception $e) {
            Log::error('Calendar refresh failed: ' . $e->getMessage(), [
                'connection_id' => $connection->id,
                'ics_link' => $connection->ics_link
            ]);
            // Log the failed refresh attempt with the connection type
            ConnectionRefreshLog::create([
                'connection_id' => $connection->id,
                'connection_type' => $connection->type,
                'venue_id' => $rentalUnit->venue_id,
                'rental_unit_id' => $rentalUnitId,
                'status' => 'error',
                'message' => 'Failed to refresh calendar connection: ' . $e->getMessage()
            ]);


            return response()->json([
                'error' => 'Failed to refresh calendar connection',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function disconnect(Request $request, $rentalUnitId, $connectionId): \Illuminate\Http\JsonResponse|array
    {
        $validation = $this->validateVenueAndRentalUnit($request, $rentalUnitId);
        if (!isset($validation['venue'])) {
            return $validation;
        }

        $connection = CalendarConnection::where('id', $connectionId)
            ->where('venue_id', $validation['venue']->id)
            ->where('rental_unit_id', $validation['rentalUnit']->id)
            ->first();

        if (!$connection) {
            return response()->json(['error' => 'Calendar connection not found'], 404);
        }

        $connection->update(['status' => 'disconnected']);

        return response()->json([
            'message' => 'Connection disconnected successfully',
            'connection' => $connection
        ]);
    }

    private function syncBookings(CalendarConnection $connection): array
    {
        try {
            $icsData = file_get_contents($connection->ics_link);
            $ical = new ICal();
            $ical->initString($icsData);
            $events = $ical->events();

            $syncedBookings = [];
            $newBookings = 0;
            $updatedBookings = 0;
            $unchangedBookings = 0;

            foreach ($events as $event) {
                try {
                    $startDate = Carbon::parse($event->dtstart);
                    $endDate = Carbon::parse($event->dtend);
                } catch (\Exception $e) {
                    Log::warning("Failed to parse date for event in calendar connection {$connection->id}", [
                        'event' => $event,
                        'error' => $e->getMessage()
                    ]);
                    continue;  // Skip this event and move to the next one
                }

                $booking = ThirdPartyBooking::updateOrCreate(
                    [
                        'venue_id' => $connection->venue_id,
                        'rental_unit_id' => $connection->rental_unit_id,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ],
                    [
                        'title' => $event->summary ?? $connection->connection_name,
                        'description' => $event->description ?? '',
                        'type' => $connection->type,
                        'summary' => $event->summary ?? '',
                    ]
                );

                $syncedBookings[] = $booking->id;

                if ($booking->wasRecentlyCreated) {
                    $newBookings++;
                } elseif ($booking->wasChanged()) {
                    $updatedBookings++;
                } else {
                    $unchangedBookings++;
                }
            }

            $removedBookings = ThirdPartyBooking::where('venue_id', $connection->venue_id)
                ->where('rental_unit_id', $connection->rental_unit_id)
                ->where('type', $connection->type)
                ->whereNotIn('id', $syncedBookings)
                ->delete();

            $connection->update([
                'status' => 'connected',
                'last_synced' => now()
            ]);

            Log::info('Calendar synced successfully', [
                'connection_id' => $connection->id,
                'new_bookings' => $newBookings,
                'updated_bookings' => $updatedBookings,
                'unchanged_bookings' => $unchangedBookings,
                'removed_bookings' => $removedBookings,
            ]);

            return [
                'new_bookings' => $newBookings,
                'updated_bookings' => $updatedBookings,
                'unchanged_bookings' => $unchangedBookings,
                'removed_bookings' => $removedBookings,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to sync calendar connection {$connection->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $connection->update([
                'status' => 'error',
                'last_synced' => now()
            ]);

            throw $e;
        }
    }


    public function generateIcs($obfuscatedId, $token): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $rentalUnitId = RentalUnit::deobfuscateId($obfuscatedId);
        $rentalUnit = RentalUnit::where('id', $rentalUnitId)
            ->where('ics_token', $token)
            ->firstOrFail();

        $calendar = Calendar::create($rentalUnit->name)
            ->refreshInterval(5)
            ->productIdentifier('VenueBoost');

        // Retrieve bookings for the next two years
        $twoYearsFromNow = now()->addYears(2);
        $bookings = Booking::where('rental_unit_id', $rentalUnit->id)
            ->where('check_out_date', '>=', now())
            ->where('check_in_date', '<=', $twoYearsFromNow)
            ->orderBy('check_in_date')
            ->get();

        foreach ($bookings as $booking) {
            $event = Event::create()
                ->name('Blocked') // Use 'Blocked' instead of 'Booked'
                ->uniqueIdentifier($booking->id)
                ->startsAt(Carbon::parse($booking->check_in_date))
                ->endsAt(Carbon::parse($booking->check_out_date));

            $calendar->event($event);
        }

        return response($calendar->get())
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $rentalUnit->name . '.ics"');
    }

    public function generateIcsComplete($obfuscatedId, $token): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $rentalUnitId = RentalUnit::deobfuscateId($obfuscatedId);
        $rentalUnit = RentalUnit::where('id', $rentalUnitId)
            ->where('ics_token', $token)
            ->firstOrFail();

        $calendar = Calendar::create($rentalUnit->name)
            ->refreshInterval(5)
            ->productIdentifier('VenueBoost');

        // Retrieve native bookings for the next two years
        $twoYearsFromNow = now()->addYears(2);
        $bookings = Booking::where('rental_unit_id', $rentalUnit->id)
            ->where('check_out_date', '>=', now())
            ->where('check_in_date', '<=', $twoYearsFromNow)
            ->orderBy('check_in_date')
            ->get();

        // Add native bookings to calendar
        foreach ($bookings as $booking) {
            $event = Event::create()
                ->name('Blocked') // Use 'Blocked' instead of 'Booked'
                ->uniqueIdentifier('native-' . $booking->id) // Prefix with 'native-' to avoid ID conflicts
                ->startsAt(Carbon::parse($booking->check_in_date))
                ->endsAt(Carbon::parse($booking->check_out_date));

            $calendar->event($event);
        }

        // Retrieve third-party bookings for the next two years
        $thirdPartyBookings = ThirdPartyBooking::where('rental_unit_id', $rentalUnit->id)
            ->where('end_date', '>=', now())
            ->where('start_date', '<=', $twoYearsFromNow)
            ->orderBy('start_date')
            ->get();

        // Add third-party bookings to calendar
        foreach ($thirdPartyBookings as $booking) {
            $event = Event::create()
                ->name($booking->title) // Use the original title from the third-party
                ->uniqueIdentifier('third-party-' . $booking->id) // Prefix with 'third-party-' to avoid ID conflicts
                ->startsAt(Carbon::parse($booking->start_date))
                ->endsAt(Carbon::parse($booking->end_date));

            if (!empty($booking->description)) {
                $event->description($booking->description);
            }

            $calendar->event($event);
        }

        return response($calendar->get())
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $rentalUnit->name . '-complete.ics"');
    }

    public function logs(Request $request, $rentalUnitId): \Illuminate\Http\JsonResponse
    {

        $validation = $this->validateVenueAndRentalUnit($request, $rentalUnitId);
        if (!isset($validation['venue'])) {
            return $validation;
        }

        // Get the venue's timezone from the validated venue
        $venueTimezone = $validation['venue']->timezone;

        $connectionLogs = ConnectionRefreshLog::where('venue_id', $validation['venue']->id)
            ->where('rental_unit_id', $validation['rentalUnit']->id)
            ->get()
            ->map(function ($connectionLog) use ($venueTimezone) {
                $connectionLog->formatted_created_at = $connectionLog->created_at
                    ?  Carbon::parse($connectionLog->created_at)->setTimezone($venueTimezone)->format('M d, g:i A')
                    : null;
                return $connectionLog;
            });

        return response()->json($connectionLogs);
    }
}
