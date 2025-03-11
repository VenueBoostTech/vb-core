<?php

namespace App\Http\Controllers\v1;

use App\Mail\GuestReceiptEmail;
use App\Mail\NewBookingEmail;
use App\Mail\RentalUnitBookingConfirmationEmail;
use App\Mail\RentalUnitBookingDeclinedEmail;
use App\Models\Chat;
use App\Models\Gallery;
use App\Models\Guest;
use App\Models\LoyaltyTier;
use App\Models\PriceBreakdown;
use App\Models\Receipt;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Wallet;
use ICal\ICal;
use Carbon\Carbon;
use App\Models\Booking;
use App\Models\RentalUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\ThirdPartyBooking;
use App\Http\Controllers\Controller;
use App\Models\PricePerNight;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Http;


class BookingController extends Controller
{

    // Add static property to control mail/SMS sending
    private static $activateMS = false;  // Set to false by default

    public function index(Request $request): \Illuminate\Http\JsonResponse
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

        $perPage = 10;

        $status = $request->input('status');
        $currentPage = 1;
        if($request->input('page') > 1) {
            $currentPage = $request->input('page');
        }

        $excludedIds = [9, 10, 11, 12, 13, 16, 17, 18, 19, 20];

        $query = Booking::query();

        $query->where('venue_id', $venue->id)
            ->whereNotIn('id', $excludedIds);

        if ($status !== null) {
            if($status == 'upcoming') {
                $query->whereDate('check_in_date', '>=', now());
            } else {
                $query->where('status', $status);
            }
        }

        $query->orderBy('created_at', 'desc');
        $bookings = $query->paginate($perPage, ['*'], 'page', $currentPage);

        if ($bookings->total() > 0) {
            $bookingsArray = $bookings->toArray();

            foreach ($bookingsArray['data'] as &$booking) {
                $booking['rental_unit'] = RentalUnit::where('id', $booking['rental_unit_id'])->first();
                $booking['guest'] = Guest::where('id', $booking['guest_id'])->first();

                // Format the date fields directly
                $booking['check_in_date'] = Carbon::parse($booking['check_in_date'])->format('Y-m-d');
                $booking['check_out_date'] = Carbon::parse($booking['check_out_date'])->format('Y-m-d');
            }

            $bookings->setCollection(collect($bookingsArray['data']));

            return response()->json(['message' => 'Booking Found', 'bookings' => $bookings], 200);
        } else {
            return response()->json(['message' => 'No Booking Found', 'bookings' => $bookings], 200);
        }
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $rentalUnitCode = request()->get('rental_unit_code');
        if (!$rentalUnitCode) {
            return response()->json(['error' => 'rental unit code is required'], 400);
        }

        $rentalUnit = RentalUnit::where('unit_code', $rentalUnitCode)->first();

        if (!$rentalUnit) {
            return response()->json(['error' => 'Rental Unit not found'], 404);
        }

        try {
            $validator = Validator::make($request->all(), [
                'guest' => 'array:email,first_name,last_name,phone,address',
                'guest.email' => 'nullable|email',
                'guest_nr' => 'required|numeric',
                'check_in_date' => 'required|date',
                'check_out_date' => 'required|date',
                'paid_with' => [
                    'required',
                    Rule::in(['card', 'cash'])
                ],
                'prepayment_amount' => 'required|numeric',
                'subtotal' => 'required|numeric',
                'total_amount' => 'required|numeric',
                'discount_price' => 'required|numeric',
                'stripe_payment_id' => "required_if:paid_with,card|nullable|string",
                'price_per_night_id' => 'required|numeric',
                'guest_id' => 'nullable|numeric|exists:guests,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $venueId = $rentalUnit->venue_id;
            $rentalUnitId = $rentalUnit->id;
            $checkInDate = $request->input('check_in_date');
            $checkOutDate = $request->input('check_out_date');

            // Check for overlapping bookings
            $overlappingBooking = Booking::where(function ($query) use ($venueId, $rentalUnitId) {
                $query->where('venue_id', $venueId)
                    ->where('rental_unit_id', $rentalUnitId);
            })->where(function ($query) use ($checkInDate, $checkOutDate) {
                $query->where(function ($query) use ($checkInDate, $checkOutDate) {
                    $query->where('check_in_date', '>=', $checkInDate)
                        ->where('check_in_date', '<', $checkOutDate);
                })->orWhere(function ($query) use ($checkInDate, $checkOutDate) {
                    $query->where('check_out_date', '>', $checkInDate)
                        ->where('check_out_date', '<=', $checkOutDate);
                });
            })->exists();

            if ($overlappingBooking) {
                return response()->json([
                    'error' => 'Overlapping booking found. The booking already exist please select the other date range.'
                ], 400);
            }

            // Validate total amount calculation
            if($request->total_amount != ($request->subtotal - $request->discount_price)) {
                return response()->json(['error' => 'Booking not confirmed. The sub total is not correct.'], 400);
            }

            // Get the venue for API call metadata
            $venue = Restaurant::find($venueId);
            if (!$venue) {
                return response()->json(['error' => 'Venue not found'], 404);
            }

            $guestDetail = null;
            $user = null;
            $randomPassword = Str::random(8);

            // STEP 1: First get or create user and guest in PHP Laravel
            if ($request->guest_id) {
                // Use existing guest
                $guestDetail = Guest::where('id', $request->guest_id)->first();
                if (!$guestDetail) {
                    return response()->json(['error' => 'Guest not found'], 404);
                }

                if ($guestDetail->restaurant_id != $venueId) {
                    return response()->json(['error' => 'Guest does not belong to the venue'], 400);
                }

                if (!$guestDetail->is_for_accommodation) {
                    return response()->json(['error' => 'Guest is not for accommodation'], 400);
                }

                // Find user for this guest
                if ($guestDetail->user_id) {
                    $user = User::find($guestDetail->user_id);
                }
            }
            elseif (isset($request->guest['email']) && !empty($request->guest['email'])) {
                // Find or create user by email
                $user = User::where('email', $request->guest['email'])->first();

                if (!$user) {
                    // Create new user
                    $user = User::create([
                        'name' => $request->guest['first_name'] . ' ' . $request->guest['last_name'],
                        'email' => $request->guest['email'],
                        'password' => Hash::make($randomPassword),
                        'country_code' => 'US',
                        'enduser' => true
                    ]);
                }

                // Find or create guest
                $guestDetail = Guest::where('email', $request->guest['email'])->first();

                if (!$guestDetail) {
                    // Create new guest
                    $guestDetail = Guest::create([
                        'restaurant_id' => $venueId,
                        'name' => $request->guest['first_name'] . ' ' . $request->guest['last_name'],
                        'email' => $request->guest['email'],
                        'phone' => $request->guest['phone'] ?? null,
                        'is_for_accommodation' => true,
                        'is_main' => true,
                        'created_manually' => false,
                        'user_id' => $user->id
                    ]);

                    // Get first tier based on name
                    $tier = LoyaltyTier::where('name', 'Bronze Tier')->first();

                    Wallet::create([
                        'guest_id' => $guestDetail->id,
                        'venue_id' => $venueId,
                        'loyalty_tier_id' => $tier->id,
                        'balance' => 0,
                    ]);
                }
                else if (!$guestDetail->user_id) {
                    // Update guest with user_id if not set
                    $guestDetail->user_id = $user->id;
                    $guestDetail->save();
                }
            }
            else {
                return response()->json(['error' => 'Guest information is required'], 400);
            }

            // STEP 2: Now sync the guest with OmniStack using getOrCreateGuest
            if ($user && $guestDetail) {
                try {
                    // Prepare data for OmniStack getOrCreateGuest API
                    $data_string = [
                        "external_ids" => [
                            "venueBoostUserId" => $user->id,
                            "venueBoostGuestId" => $guestDetail->id
                        ],
                        "name" => $user->first_name ?? explode(' ', $guestDetail->name)[0],
                        "surname" => $user->last_name ?? (count(explode(' ', $guestDetail->name)) > 1 ? explode(' ', $guestDetail->name)[1] : ''),
                        "email" => $guestDetail->email,
                        "phone" => $guestDetail->phone ?? null,
                        "password" => $randomPassword,
                        "registrationSource" => "metrosuites"
                    ];

                    // Get venue's webhook URL and construct full endpoint
                    $omniStackUrl = rtrim(env('OMNISTACK_GATEWAY_BASEURL'), '/') . '/users/' . $venue->short_code . '/get-or-create-guest';

                    // Send API request
                    $response = Http::withHeaders([
                        'webhook-api-key' => env('OMNISTACK_GATEWAY_MSUITES_API_KEY'),
                        'x-api-key' => env('OMNISTACK_GATEWAY_API_KEY'),
                        'Content-Type' => 'application/json',
                    ])->post($omniStackUrl, $data_string);

                    if ($response->successful()) {
                        // Update user with external IDs
                        $userExternalIds = json_decode($user->external_ids ?? '{}', true);
                        $userExternalIds['omniStackGateway'] = $response->json('userId');
                        $user->external_ids = json_encode($userExternalIds);
                        $user->save();

                        // Update guest with external IDs
                        $guestExternalIds = json_decode($guestDetail->external_ids ?? '{}', true);
                        $guestExternalIds['omniStackGateway'] = $response->json('guestId');
                        $guestDetail->external_ids = json_encode($guestExternalIds);
                        $guestDetail->save();
                    } else {
                        \Log::error('Failed to sync guest with OmniStack', [
                            'status' => $response->status(),
                            'response' => $response->json()
                        ]);
                    }
                } catch (\Throwable $th) {
                    \Sentry\captureException($th);
                    \Log::error('Exception in guest sync with OmniStack', [
                        'error' => $th->getMessage(),
                        'trace' => $th->getTraceAsString()
                    ]);
                    // Continue execution even if OmniStack sync fails
                }
            }

            // STEP 3: Create the booking
            $booking = new Booking;
            $booking->venue_id = $venueId;
            $booking->rental_unit_id = $rentalUnit->id;
            $booking->guest_id = $guestDetail->id;
            $booking->guest_nr = $request->guest_nr;
            $booking->check_in_date = $request->check_in_date;
            $booking->check_out_date = $request->check_out_date;
            $booking->discount_price = $request->discount_price;
            $booking->total_amount = $request->total_amount;
            $booking->discount_id = $request->discount_id;
            $booking->subtotal = $request->subtotal;
            $booking->status = "Pending";
            $booking->paid_with = $request->paid_with;
            $booking->prepayment_amount = $request->prepayment_amount;
            if ($request->paid_with == 'card')
                $booking->stripe_payment_id = $request->stripe_payment_id;

            $booking->confirmation_code = $this->generateConfirmationCode();
            $booking->save();

            // Create a receipt
            $receipt = new Receipt();
            $receipt->booking_id = $booking->id;
            $receipt->receipt_id = $this->generateReceiptId();
            $receipt->rental_unit_id = $booking->rental_unit_id;
            $receipt->venue_id = $booking->venue_id;

            if ($request->paid_with !== 'card') {
                $receipt->status = 'not_paid';
            } elseif ($request->prepayment_amount > 0) {
                $receipt->status = 'partially_paid';
            } else {
                $receipt->status = 'fully_paid';
            }

            $receipt->total_amount = $booking->total_amount;
            $receipt->save();

            // Create price breakdown record
            $pricingBreakdown = new PriceBreakdown();
            $pricingBreakdown->booking_id = $booking->id;
            $pricingBreakdown->type = 'booking_create';
            $pricingBreakdown->price_difference = 0.00;
            $pricingBreakdown->total_adjustment = 0.00;
            $pricingBreakdown->previous_total = $booking->total_amount;
            $pricingBreakdown->new_total = $booking->total_amount;
            $pricingBreakdown->venue_id = $booking->venue_id;
            $pricingBreakdown->rental_unit_id = $booking->rental_unit_id;
            $pricingBreakdown->save();

            // Get venue logo for emails
            $venueLogo = $venue->logo ? Storage::disk('s3')->temporaryUrl($venue->logo, '+8000 minutes') : null;

            // Prepare data for guest receipt email
            $guestReceiptData = [
                'receipt_id' => $receipt->receipt_id,
                'venue_logo' => $venueLogo,
                'receipt_created_at' => Carbon::parse($receipt->created_at)->format('M d, Y'),
                'rental_unit_name' => $rentalUnit->name,
                'nr_of_nights' => Carbon::parse($booking->check_in_date)->diffInDays(Carbon::parse($booking->check_out_date)),
                'booking_date' => Carbon::parse($booking->created_at)->format('D, M d, Y'),
                'rental_type' => $rentalUnit->accommodation_type,
                'nr_of_guests' => $booking->guest_nr,
                'confirmation_code' => $booking->confirmation_code,
                'pricing_breakdown' => $pricingBreakdown,
                'pricing_breakdown_text' => $pricingBreakdown->type === 'booking_create' ? 'Booking Create' : $pricingBreakdown->type,
                'currency' => $rentalUnit->currency,
                'payment_method' => $booking->paid_with === 'card' ? 'Card' : 'Cash',
                'prepayment_amount' => $booking->prepayment_amount,
                'check_in_date' => Carbon::parse($booking->check_in_date)->format('M d, Y'),
                'check_out_date' => Carbon::parse($booking->check_out_date)->format('M d, Y'),
                'password' => $randomPassword ?? null,
                'email' => $guestDetail->email ?? null,
            ];

            Mail::to($guestDetail->email)->send(new GuestReceiptEmail($venue->name, $guestReceiptData));

//            Mail::to($guest['email'])->send(new GuestReceiptEmail($venue->name, $guestReceiptData));

            $venueLogo = $venue->logo ? Storage::disk('s3')->temporaryUrl($venue->logo, '+8000 minutes') : null;
            // send a new booking email to the venue
            // Send new booking email
            if (self::$activateMS && $venue->email) {
                // Send to venue email if activateMS is true
                Mail::to($venue->email)->send(new NewBookingEmail(
                    $venue->name,
                    $guestDetail->name,
                    $rentalUnit->name,
                    $booking->check_in_date,
                    $booking->check_out_date,
                    $venueLogo
                ));
            }

            // Always send to specific email regardless of activateMS
            Mail::to('griseld.gerveni@yahoo.com')->send(new NewBookingEmail(
                $venue->name,
                $guestDetail->name,
                $rentalUnit->name,
                $booking->check_in_date,
                $booking->check_out_date,
                $venueLogo
            ));

            // Send SMS notification
            if ($venue->phone_number) {
                $account_sid = env('TWILIO_ACCOUNT_SID');
                $auth_token = env('TWILIO_AUTH_TOKEN');
                $twilio_number = env('TWILIO_NUMBER');

                $client = new Client($account_sid, $auth_token);

                $smsBody = "New Booking Notification from VenueBoost\n\n" .
                    "Guest: {$guestDetail->name}\n" .
                    "Rental Unit: {$rentalUnit->name}\n" .
                    "Check-in: {$booking->check_in_date}\n" .
                    "Check-out: {$booking->check_out_date}";

                try {
                    // Send to specific number regardless of activateMS
                    $client->messages->create(
                        '+306908654153',
                        array(
                            'from' => $twilio_number,
                            'body' => $smsBody
                        )
                    );

                    // Send to venue phone if activateMS is true
                    if (self::$activateMS) {
                        $client->messages->create(
                            $venue->phone_number,
                            array(
                                'from' => $twilio_number,
                                'body' => $smsBody
                            )
                        );
                    }
                } catch (\Exception $e) {
                    \Sentry\captureException($e);
                }
            }



            // check tier of guest, get points and update wallet balance with points and update activate
            // get guest's tier attribute

            $tier = $guestDetail->getLoyaltyTierAttribute();
            // get the points earned based on the tier
            $pointsEarned = $tier?->points_per_booking ?? 0;

            // Update the guest's wallet balance with the points earned
            // Update guest's wallet balance with the earned points
            if (!$guestDetail->wallet) {
                // get first tier based on name
                $tier = LoyaltyTier::where('name', 'Bronze Tier')->first();

                Wallet::create([
                    'guest_id' => $guestDetail->id,
                    'venue_id' => $venue->id,
                    'loyalty_tier_id' => $tier->id,
                    'balance' => $pointsEarned,
                ]);
            } else {
                $guestDetail->wallet->increment('balance', $pointsEarned);
            }

            // Add a record to the earnPointsHistory table with guest_id, reservation_id, and points_earned
            $guestDetail->earnPointsHistory()->create([
                'booking_id' => $booking->id,
                'points_earned' => $pointsEarned,
                'venue_id' => $venue->id,
            ]);



            return response()->json(['message' => 'Booking confirmed!', 'booking_details' => $booking], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            $errorMessage = $e->getMessage();
            return response()->json(['error' => 'An error occurred: ' . $errorMessage], 400);
        }

    }


    // store third party booking from ics file
    public function storeThirdPartyBooking(Request $request, $id): \Illuminate\Http\JsonResponse
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

        $rentalUnit = RentalUnit::where('id', $id)->where('venue_id', $venue->id)->first();

        if (!$rentalUnit) {
            return response()->json(['message' => 'The requested rental unit does not exist'], 404);
        }

        try{
            $validator = Validator::make($request->all(), [
                'title' => 'required',
                'description' => 'required',
                'type' => 'required',
                'file' => 'file|mimetypes:text/calendar,application/calendar',
                'url' => 'string',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            // check if url

            if ($request->url) {
                $url = $request->url;
                $icsData = file_get_contents($url);

                $ical = new ICal();
                $ical->initString($icsData);
                $events = $ical->events();
                $data = [];
                $data = $request->except('file', 'SN-BOOST-CORE-ADMIN-API-KEY');
                foreach ($events as $event) {
                    $data['event_description'] = $event->description;
                    $data['summary'] = $event->summary;
                    $data['start_date'] = Carbon::createFromFormat('Ymd', $event->dtstart);
                    $data['end_date'] = Carbon::createFromFormat('Ymd', $event->dtend);

                    $newBookingStartDate = Carbon::createFromFormat('Ymd', $event->dtstart);
                    $newBookingEndDate = Carbon::createFromFormat('Ymd', $event->dtend);

                    // Check if there are any existing bookings that overlap with the new booking's date range
                    $existingBookings = ThirdPartyBooking::where(function ($query) use ($newBookingStartDate, $newBookingEndDate) {
                        $query->where(function ($subquery) use ($newBookingStartDate, $newBookingEndDate) {
                            $subquery->where('start_date', '<=', $newBookingStartDate)
                                ->where('end_date', '>=', $newBookingStartDate);
                        })->orWhere(function ($subquery) use ($newBookingStartDate, $newBookingEndDate) {
                            $subquery->where('start_date', '<=', $newBookingEndDate)
                                ->where('end_date', '>=', $newBookingEndDate);
                        });
                    });

                    // Check if venue_id and rental_unit_id are provided and if they are different from existing bookings
                    if ($venue->id && $rentalUnit->id) {
                        $existingBookings->where(function ($query) use ($data, $venue, $rentalUnit) {
                            $query->where('venue_id', $venue->id)
                                ->where('rental_unit_id', $rentalUnit->id);
                        });
                    }

                    $existingBookings = $existingBookings->get();
                    if ($existingBookings->count() > 0) {
                        return response()->json(['error' => 'Booking overlaps with existing bookings. The booking on these dates already exist provided in ics file.'], 400);
                    }
                    $data['venue_id']= $venue->id;
                    $data['rental_unit_id']= $rentalUnit->id;
                    $bookingConfirmed = ThirdPartyBooking::create($data);
                }
                return response()->json(['message' => 'Booking Done Successfully!', ], 200);
            }

            $icsFile = $request->file('file');
            if ($icsFile) {
                $icsData = file_get_contents($icsFile->getPathname());

                $ical = new ICal();
                $ical->initString($icsData);
                $events = $ical->events();
                $data = [];
                $data = $request->except('file', 'SN-BOOST-CORE-ADMIN-API-KEY');
                foreach ($events as $event) {
                    $data['event_description'] = $event->description;
                    $data['summary'] = $event->summary;
                    $data['start_date'] = Carbon::createFromFormat('Ymd', $event->dtstart);
                    $data['end_date'] = Carbon::createFromFormat('Ymd', $event->dtend);

                    $newBookingStartDate = Carbon::createFromFormat('Ymd', $event->dtstart);
                    $newBookingEndDate = Carbon::createFromFormat('Ymd', $event->dtend);

                    // Check if there are any existing bookings that overlap with the new booking's date range
                    $existingBookings = ThirdPartyBooking::where(function ($query) use ($newBookingStartDate, $newBookingEndDate) {
                        $query->where(function ($subquery) use ($newBookingStartDate, $newBookingEndDate) {
                            $subquery->where('start_date', '<=', $newBookingStartDate)
                                ->where('end_date', '>=', $newBookingStartDate);
                        })->orWhere(function ($subquery) use ($newBookingStartDate, $newBookingEndDate) {
                            $subquery->where('start_date', '<=', $newBookingEndDate)
                                ->where('end_date', '>=', $newBookingEndDate);
                        });
                    });

                    // Check if venue_id and rental_unit_id are provided and if they are different from existing bookings
                    if ($venue->id && $rentalUnit->id) {
                        $existingBookings->where(function ($query) use ($data, $venue, $rentalUnit) {
                            $query->where('venue_id', $venue->id)
                                ->where('rental_unit_id', $rentalUnit->id);
                        });
                    }

                    $existingBookings = $existingBookings->get();
                    if ($existingBookings->count() > 0) {
                        return response()->json(['error' => 'Booking overlaps with existing bookings. The booking on these dates already exist provided in ics file.'], 400);
                    }
                    $data['venue_id']= $venue->id;
                    $data['rental_unit_id']= $rentalUnit->id;
                    $bookingConfirmed = ThirdPartyBooking::create($data);
                }
                return response()->json(['message' => 'Booking Done Successfully!', ], 200);
            }

            return response()->json(['message' => 'No ICS file uploaded'], 400);

            } catch (\Exception $e)
            {
                \Sentry\captureException($e);
                $errorMessage = $e->getMessage();
                return response()->json(['error' =>  'An error occurred: ' . $errorMessage], 400);
            }
    }

    // show third party booking done with ics file
    public function showThirdPartyBooking(Request $request, $id): \Illuminate\Http\JsonResponse
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

        // rental units are allowed only for venue types vacation rental
        if (!($venue->venueType->short_name != 'vacation_rental' || $venue->venueType->short_name !== 'hotel')) {
            return response()->json(['error' => 'Venue type is not vacation rental'], 400);
        }

        $rentalUnit = RentalUnit::where('id', $id)->where('venue_id', $venue->id)->first();

        if (!$rentalUnit) {
            return response()->json(['message' => 'The requested rental unit does not exist'], 404);
        }

        $venueId = $venue->id;
        $rentalUnitId = $rentalUnit->id;
        $month = $request->input('month');
        $filterType = $request->input('type');
        $bookings = collect();
        $thirdPartyBookings = collect();

        if ($filterType == 'all') {
            $bookings = Booking::select('id', 'venue_id', 'rental_unit_id', 'guest_id', 'guest_nr', 'check_in_date as start_date', 'check_out_date as end_date', 'total_amount', 'discount_price', 'status', 'paid_with', 'prepayment_amount', 'created_at', 'stripe_payment_id', 'subtotal')
                ->where(function ($query) use ($venueId, $rentalUnitId, $month) {
                    if ($venueId !== null) {
                        $query->where('venue_id', $venueId);
                    }

                    if ($rentalUnitId !== null) {
                        $query->where('rental_unit_id', $rentalUnitId);
                    }

                    if ($month !== null) {
                        $query->whereMonth('check_in_date', $month);
                    }
                })
                ->get();

            $thirdPartyBookings = ThirdPartyBooking::where(function ($query) use ($venueId, $rentalUnitId, $month) {
                if ($venueId !== null) {
                    $query->where('venue_id', $venueId);
                }

                if ($rentalUnitId !== null) {
                    $query->where('rental_unit_id', $rentalUnitId);
                }

                if ($month !== null) {
                    $query->whereMonth('start_date', $month);
                }
            })->get();

            $message = 'Booking Found';
        } elseif ($filterType == 'import_ics_booking') {
            $thirdPartyBookings = ThirdPartyBooking::where(function ($query) use ($venueId, $rentalUnitId, $month) {
                if ($venueId !== null) {
                    $query->where('venue_id', $venueId);
                }

                if ($rentalUnitId !== null) {
                    $query->where('rental_unit_id', $rentalUnitId);
                }

                if ($month !== null) {
                    $query->whereMonth('start_date', $month);
                }
            })->get();

            $message = 'Booking Found';
        } elseif ($filterType == 'Venue_boost_booking') {
            $bookings = Booking::select('id', 'venue_id', 'rental_unit_id', 'guest_id', 'guest_nr', 'check_in_date as start_date', 'check_out_date as end_date', 'total_amount', 'discount_price', 'status', 'paid_with', 'prepayment_amount', 'created_at', 'stripe_payment_id', 'subtotal')
                ->where(function ($query) use ($venueId, $rentalUnitId, $month) {
                    if ($venueId !== null) {
                        $query->where('venue_id', $venueId);
                    }

                    if ($rentalUnitId !== null) {
                        $query->where('rental_unit_id', $rentalUnitId);
                    }

                    if ($month !== null) {
                        $query->whereMonth('check_in_date', $month);
                    }
                })
                ->get();
            $message = 'Booking Found';
        }

        if ($bookings->count() > 0 || $thirdPartyBookings->count() > 0) {
            return response()->json([
                'message' => $message,
                'bookings' => $bookings,
                'thirdPartyBookings' => $thirdPartyBookings,
            ], 200);
        } else {
            return response()->json([
                'message' => 'No Booking Found',
                'bookings' => $bookings,
                'thirdPartyBookings' => $thirdPartyBookings,
            ], 200);
        }
    }

    public function getRentalUnitPrice(Request $request): \Illuminate\Http\JsonResponse
    {

        $rentalUnitCode = request()->get('rental_unit_code');
        if (!$rentalUnitCode) {
            return response()->json(['error' => 'rental unit code is required'], 400);
        }

        $rentalUnit = RentalUnit::where('unit_code', $rentalUnitCode)->first();

        if (!$rentalUnit) {
            return response()->json(['error' => 'Rental Unit not found'], 404);
        }

        $rentalUnit = RentalUnit::with('price_per_nights')->where('id', $rentalUnit->id)->where('venue_id', $rentalUnit->venue_id)->first();
        if($rentalUnit){
            return response()->json(['price_per_nights' => $rentalUnit->price_per_nights], 200);
        } else {
            return response()->json(['message' => 'Not Found'], 200);
        }


    }

    public function checkAvailability(Request $request): JsonResponse
    {
        $rentalUnitName = $request->get('rental_unit');
        if (!$rentalUnitName) {
            return response()->json(['error' => 'Rental unit name is required'], 400);
        }

        $rentalUnit = RentalUnit::where('name', $rentalUnitName)->first();
        if (!$rentalUnit) {
            return response()->json(['error' => 'Rental unit not found'], 404);
        }

        $checkInDate = $request->get('check_in_date');
        $checkOutDate = $request->get('check_out_date');
        if (!$checkInDate || !$checkOutDate) {
            return response()->json(['error' => 'Both check-in and check-out dates are required'], 400);
        }

        try {
            $checkInDate = Carbon::parse($checkInDate);
            $checkOutDate = Carbon::parse($checkOutDate);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid date format'], 400);
        }

        // Check regular bookings
        $regularBookingConflict = Booking::where('rental_unit_id', $rentalUnit->id)
            ->where(function ($query) use ($checkInDate, $checkOutDate) {
                $query->whereBetween('check_in_date', [$checkInDate, $checkOutDate])
                    ->orWhereBetween('check_out_date', [$checkInDate, $checkOutDate])
                    ->orWhere(function ($q) use ($checkInDate, $checkOutDate) {
                        $q->where('check_in_date', '<=', $checkInDate)
                            ->where('check_out_date', '>=', $checkOutDate);
                    });
            })
            ->exists();

        // Check third-party bookings
        $thirdPartyBookingConflict = ThirdPartyBooking::where('rental_unit_id', $rentalUnit->id)
            ->where(function ($query) use ($checkInDate, $checkOutDate) {
                $query->whereBetween('start_date', [$checkInDate, $checkOutDate])
                    ->orWhereBetween('end_date', [$checkInDate, $checkOutDate])
                    ->orWhere(function ($q) use ($checkInDate, $checkOutDate) {
                        $q->where('start_date', '<=', $checkInDate)
                            ->where('end_date', '>=', $checkOutDate);
                    });
            })
            ->exists();

        if ($regularBookingConflict || $thirdPartyBookingConflict) {
            return response()->json(['message' => 'Rental unit is not available for the selected dates', 'code' => 0], 200);
        } else {
            return response()->json(['message' => 'Rental unit is available for the selected dates', 'code' => 1], 200);
        }
    }

    // Function to change the status of a booking
    public function changeStatus(Request $request): JsonResponse
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
            'id' => 'required|exists:bookings,id',
            //  ['Pending', 'Processing', 'Confirmed', 'Cancelled', 'Completed']
            'status' => 'required|in:Pending,Processing,Confirmed,Cancelled,Completed',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $booking = Booking::where('id', $request->input('id'))->where('venue_id', $venue->id)->first();
        if (!$booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        $booking->status = $request->input('status');
        $booking->save();

        $rentalUnit = RentalUnit::where('id', $booking->rental_unit_id)->first();
        $guest = Guest::where('id', $booking->guest_id)->first();

         $gallery = Gallery::where('rental_unit_id', $rentalUnit->id)->with('photo')->get();

        // if booking is confirmed, send email to customer
        if ($request->input('status') === 'Confirmed') {
            // prepare data for guest confirmation booking email
            $rentalUnitBookingConfirmationData = [
                // rental unit name
                'rental_unit_name' => $rentalUnit->name,
                'nr_of_nights' => Carbon::parse($booking->check_in_date)->diffInDays(Carbon::parse($booking->check_out_date)),
                // booking date in this format Sun, May 14, 2023
                'booking_date' => Carbon::parse($booking->created_at)->format('D, M d, Y'),
                // rental type
                'rental_type' => $rentalUnit->accommodation_type,
                // nr of guests
                'nr_of_guests' => $booking->guest_nr,
                // booking confirmation code
                'confirmation_code' => $booking->confirmation_code,
                // currency
                'currency' => $rentalUnit->currency,
                // payment method
                'payment_method' => $booking->paid_with === 'card' ? 'Card' : 'Cash',
                // prepayment amount
                'prepayment_amount' => $booking->prepayment_amount,
                // check in date
                'check_in_date' => Carbon::parse($booking->check_in_date)->format('M d, Y'),
                // check out date
                'check_out_date' => Carbon::parse($booking->check_out_date)->format('M d, Y'),
                // rental unit address
                'rental_unit_address' => $rentalUnit->address,
                // rental unit host
                'rental_unit_host' => $rentalUnit->accommodation_host_profile->host_name,
                // rental unit check in time, check out time
                'rental_unit_check_in_form' => $rentalUnit->accommodation_rules->check_in_from,
                'rental_unit_checkout_until' => $rentalUnit->accommodation_rules->checkout_until,

                // get first photo from gallery, if exists
                'rental_unit_photo' => $gallery->count() > 0 ? Storage::disk('s3')->temporaryUrl($gallery[0]->photo->image_path, '+5 minutes') : null,
            ];

            Mail::to($guest['email'])->send(new RentalUnitBookingConfirmationEmail($venue->name, $rentalUnitBookingConfirmationData));
        } else if ($request->input('status') === 'Cancelled') {
            // prepare data for guest confirmation booking email
            $rentalUnitBookingDeclinedData = [
                // rental unit name
                'rental_unit_name' => $rentalUnit->name,
                'rental_type' => $rentalUnit->accommodation_type,
                'rental_unit_host' => $rentalUnit->accommodation_host_profile->host_name,
                // rental unit host
               ];

            Mail::to($guest['email'])->send(new RentalUnitBookingDeclinedEmail($venue->name, $rentalUnitBookingDeclinedData));
        }


        return response()->json(['message' => 'Booking status updated successfully', 'data' => $booking]);
    }

    public function paid(Request $request): JsonResponse
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
            'id' => 'required|exists:bookings,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $booking = Booking::where('id', $request->input('id'))->where('venue_id', $venue->id)->first();
        if (!$booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        $receipt = Receipt::where('booking_id', $booking->id)->first();
        if (!$receipt) {
            return response()->json(['error' => 'Receipt not found'], 404);
        }

        $receipt->status = 'fully_paid';
        $receipt->save();

        // paid receipt response
        return response()->json(['message' => 'Booking receipt status updated successfully', 'data' => $receipt]);
    }

    public function getBookingDetails(Request $request, $id): JsonResponse
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

        $booking = Booking::with([
            'rentalUnit.accommodation_detail',
            'rentalUnit.accommodation_rules',
            'rentalUnit.accommodation_host_profile',
            'guest',
            'receipt',
            'priceBreakdowns'
        ])->where('venue_id', $venue->id)
            ->where('id', $id)
            ->first();

        if (!$booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        // Get rental unit gallery
        $gallery = Gallery::where('rental_unit_id', $booking->rental_unit_id)
            ->with('photo')
            ->get()
            ->map(function ($item) {
                return [
                    'photo_id' => $item->photo_id,
                    'photo_url' => Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes')
                ];
            });


        // Get or create chat for this booking
        $chat = Chat::firstOrCreate(
            [
                'booking_id' => $booking->id,
                'end_user_id' => $booking->guest->user?->id,
                'type' => Chat::TYPE_BOOKING
            ],
            [
                'venue_user_id' => $booking->rentalUnit->venue->user_id,
                'venue_id' => $booking->rentalUnit->venue_id,
                'status' => Chat::STATUS_ACTIVE
            ]
        );

        // Calculate nights and dates
        $checkIn = Carbon::parse($booking->check_in_date);
        $checkOut = Carbon::parse($booking->check_out_date);
        $nights = $checkIn->diffInDays($checkOut);


        // Initialize an array to count occurrences of each bed type
        $bedCount = [];
        $roomsCount = RentalUnit::with('rooms.beds')->find($booking->rentalUnit->id)->rooms->count();


        return response()->json([
            'booking' => [
                'id' => $booking->id,
                'confirmation_code' => $booking->confirmation_code,
                'status' => $booking->status,
                'created_at' => $booking->created_at,

                // Dates
                'check_in' => [
                    'date' => $checkIn->format('Y-m-d'),
                    'formatted' => $checkIn->format('M d, Y'),
                    'time' => $booking->rentalUnit->accommodation_rules->check_in_from ?? 'N/A'
                ],
                'check_out' => [
                    'date' => $checkOut->format('Y-m-d'),
                    'formatted' => $checkOut->format('M d, Y'),
                    'time' => $booking->rentalUnit->accommodation_rules->checkout_until ?? 'N/A'
                ],
                'nights' => $nights,
                'chat' => $chat,

                // Guest Info
                'guest' => [
                    'id' => $booking->guest->id,
                    'name' => $booking->guest->name,
                    'email' => $booking->guest->email,
                    'phone' => $booking->guest->phone,
                    'is_verified' => (bool)$booking->guest->user?->email_verified_at,
                    'number_of_guests' => $booking->guest_nr
                ],

                // Payment Info
                'payment' => [
                    'method' => $booking->paid_with,
                    'stripe_payment_id' => $booking->stripe_payment_id,
                    'subtotal' => $booking->subtotal,
                    'discount' => $booking->discount_price,
                    'total' => $booking->total_amount,
                    'prepayment' => $booking->prepayment_amount,
                    'currency' => $booking->rentalUnit->currency,
                    'receipt_id' => $booking->receipt?->receipt_id,
                    'receipt_status' => $booking->receipt?->status
                ],

                // Property Info
                'property' => [
                    'id' => $booking->rentalUnit->id,
                    'name' => $booking->rentalUnit->name,
                    'type' => $booking->rentalUnit->accommodation_type,
                    'address' => $booking->rentalUnit->address,
                    'host' => [
                        'name' => $booking->rentalUnit->accommodation_host_profile->host_name ?? 'N/A',
                        'phone' => $booking->rentalUnit->accommodation_host_profile->host_phone ?? 'N/A'
                    ],
                    'photos' => $gallery,
                    'details' => [
                        'size' => $booking->rentalUnit->accommodation_detail->square_metres ?? 'N/A',
                        'bedrooms' => $booking->rentalUnit->accommodation_detail->bedrooms ?? 0,
                        'rooms' => $roomsCount,
                        'bathrooms' => $booking->rentalUnit->accommodation_detail->bathroom_count ?? 0
                    ]
                ],

                // Price History
                'price_history' => $booking->priceBreakdowns->map(function ($breakdown) {
                    return [
                        'type' => $breakdown->type,
                        'price_difference' => $breakdown->price_difference,
                        'total_adjustment' => $breakdown->total_adjustment,
                        'previous_total' => $breakdown->previous_total,
                        'new_total' => $breakdown->new_total,
                        'created_at' => $breakdown->created_at
                    ];
                })
            ]
        ]);
    }

    private function generateConfirmationCode($length = 12): string
        {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $code;
    }

    private function generateReceiptId(): string
    {
        // Generate a random string of characters
        $randomPart = Str::random(10); // You can adjust the length as needed

        // Generate a timestamp part (for example, in a YmdHis format)
        $timestampPart = now()->format('YmdHis');

        // Combine both parts to create the unique receipt ID
        return $timestampPart . $randomPart;
    }

    /**
     * List bookings for OmniStack integration
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listBookingsForOmnistack(Request $request): \Illuminate\Http\JsonResponse
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

        // Get bookings for this venue
        $bookings = Booking::getBookingsForOmniStack($venue->id);

        return response()->json([
            'data' => $bookings,
            'message' => 'Bookings retrieved successfully'
        ]);
    }

    /**
     * Update booking with external ID from OmniStack
     *
     * @param Request $request
     * @param int $id Booking ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateBookingExternalId(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        // Validate incoming request
        $validator = Validator::make($request->all(), [
            'omnigateway_api_key' => 'required|string',
            'omnistack_id' => 'required|string',
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

        // Find the booking
        $booking = Booking::where('id', $id)
            ->where('venue_id', $venue->id)
            ->first();

        if (!$booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        // Get current external_ids or initialize as empty array
        $externalIds = $booking->external_ids ? json_decode($booking->external_ids, true) : [];

        // Add the OmniStack ID
        $externalIds['omniStackId'] = $request->input('omnistack_id');

        // Update the booking
        $booking->external_ids = json_encode($externalIds);
        $booking->save();

        return response()->json([
            'message' => 'Booking external ID updated successfully',
            'booking_id' => $booking->id
        ]);
    }


    /**
     * Delete a booking and associated records for OmniStack integration
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroyForOmniStack(Request $request, $id): JsonResponse
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

        $booking = Booking::where('id', $id)
            ->where('venue_id', $venue->id)
            ->first();

        if (!$booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        // Delete receipt if exists
        if ($booking->receipt) {
            $booking->receipt->delete();
        }

        // Delete price breakdowns
        $booking->priceBreakdowns()->delete();

        // Delete earn points history
        $booking->earnPointsHistories()->delete();

        // Delete chat if exists
        $chat = \App\Models\Chat::where('booking_id', $booking->id)->first();
        if ($chat) {
            $chat->delete();
        }

        // Finally delete the booking
        $booking->delete();

        return response()->json([
            'message' => 'Booking deleted successfully'
        ]);
    }
}
