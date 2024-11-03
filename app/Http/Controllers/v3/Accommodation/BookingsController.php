<?php

namespace App\Http\Controllers\v3\Accommodation;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Gallery;
use App\Models\Guest;
use App\Models\RentalUnit;
use App\Services\EndUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BookingsController extends Controller
{
    protected EndUserService $endUserService;

    public function __construct(EndUserService $endUserService)
    {
        $this->endUserService = $endUserService;
    }

    public function index(Request $request): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse; // If it's a JsonResponse, return it immediately
        }

        $user = $userOrResponse; // Now we know it's a User object

        $guest = Guest::where('user_id', $user->id)->first();

        if (!$guest) {
            return response()->json(['error' => 'Guest not found'], 404);
        }

        // Rest of your existing code...
        $bookings = Booking::with([
            'rentalUnit.venue.addresses.country',
            'rentalUnit.accommodation_detail',
            'rentalUnit.rooms',
            'guest'
        ])
            ->where('guest_id', $guest->id)
            ->orderBy('check_in_date', 'desc')
            ->get();

        $groupedBookings = $this->groupBookings($bookings);

        return response()->json($groupedBookings);
    }

    public function bookingDetails(Request $request, $bookingId): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse; // If it's a JsonResponse, return it immediately
        }

        $user = $userOrResponse; // Now we know it's a User object

        $guest = Guest::where('user_id', $user->id)->first();

        if (!$guest) {
            return response()->json(['error' => 'Guest not found'], 404);
        }

        $booking = Booking::with([
            'rentalUnit.venue.addresses.country',
            'rentalUnit.accommodation_detail',
            'rentalUnit.rooms',
            'guest'
        ])
            ->where('guest_id', $guest->id)
            ->where('id', $bookingId)
            ->first();

        if (!$booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        $gallery = Gallery::where('rental_unit_id', $booking->rentalUnit->id)->with('photo')->get();

        $modifiedGallery = $gallery->map(function ($item) {
            return [
                'photo_id' => $item->photo_id,
                'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
            ];
        });

        $bookingDetails = [
            'id' => $booking->id,
            'rental_unit_name' => $booking->rentalUnit->name,
            'check_in' => $booking->check_in_date,
            'check_out' => $booking->check_out_date,
            'total' => $booking->total_amount,
            'currency' => $booking->rentalUnit->currency,
            'status' => $booking->status,
            'guest_count' => $booking->guest_nr,
            'rental_unit_photo' => count($modifiedGallery) > 0 ? $modifiedGallery[0]['photo_path'] : null
        ];

        return response()->json($bookingDetails);
    }

    private function groupBookings($bookings)
    {
        $groupedByCountry = $bookings->groupBy(function ($booking) {
            return $booking->rentalUnit->venue->addresses->first()->country->name ?? 'Unknown';
        });

        $result = [];

        foreach ($groupedByCountry as $country => $countryBookings) {
            $result[$country] = [
                'total' => $countryBookings->count(),
                'periods' => $this->getBookingsByPeriod($countryBookings),
            ];
        }

        return $result;
    }

    private function getBookingsByPeriod($bookings): array
    {
        $groupedBookings = $bookings->groupBy(function ($booking) {
            return Carbon::parse($booking->check_in_date)->format('Y-m');
        })->sortKeysDesc();

        $periods = [];

        foreach ($groupedBookings as $yearMonth => $periodBookings) {
            $periods[$yearMonth] = [
                'count' => $periodBookings->count(),
                'bookings' => $periodBookings->map(function ($booking) {

                    $gallery = Gallery::where('rental_unit_id', $booking->rentalUnit->id)->with('photo')->get();

                    $modifiedGallery = $gallery->map(function ($item) {
                        return [
                            'photo_id' => $item->photo_id,
                            'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
                        ];
                    });

                    return [
                        'id' => $booking->id,
                        'rental_unit_name' => $booking->rentalUnit->name,
                        'check_in' => $booking->check_in_date,
                        'check_out' => $booking->check_out_date,
                        'total' => $booking->total_amount,
                        'currency' => $booking->rentalUnit->currency,
                        'status' => $booking->status,
                        'guest_count' => $booking->guest_nr,
                        'rental_unit_photo' => count($modifiedGallery) > 0 ? $modifiedGallery[0]['photo_path'] : null
                    ];
                }),
            ];
        }

        return $periods;
    }

}
