<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Mail\EndUserCardCheckinEmail;
use App\Mail\EndUserCardCreateEmail;
use App\Models\EndUserCard;
use App\Models\Guest;
use App\Models\Promotion;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Milon\Barcode\DNS2D;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use stdClass;

/**
 * @OA\Info(
 *   title="End User Car API",
 *   version="1.0",
 *   description="This API allows use End User Car Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="End User Card API",
 *   description="Operations related to End User Car"
 * )
 */

class EndUserCardController extends Controller
{

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

        $endUserCards = EndUserCard::where('venue_id', $venue->id)
            ->with('guest') // Eager load the guest relationship
            ->get();


        $formattedCards = $endUserCards->map(function ($card) {
            return [
                'id' => $card->id,
                'user_full_name' => $card->guest->name,
                'notes' => $card->notes,
                'issued_at' => $card->issued_at ?  Carbon::parse($card->issued_at)->format('Y-m-d H:i:s') : null,
                'last_scanned_at' => $card->last_scanned_at ?  Carbon::parse($card->last_scanned_at)->format('Y-m-d H:i:s') : null,
                'user_email' => $card->guest->email, // Adjust this based on your guest model
                'user_phone' => $card->guest->phone, // Adjust this based on your guest model
                'status' => $card->status,
                'is_verified' => $card->is_verified,
                'expiration_date' => $card->expiration_date ?  Carbon::parse($card->expiration_date)->format('Y-m-d H:i:s') : null,
                'guest' => $card->guest,
            ];
        });

        return response()->json(['data' => $formattedCards], 200);

    }
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

        // Validate the request
        $validator = Validator::make($request->all(), [
            'guest_id' => 'nullable|exists:guests,id',
            'issued_at' => 'required',
            'expiration_date' => 'required',
            'notes' => 'nullable',
        ]);

        // validate if guest is part of the venue

        $guest = Guest::where('id', $request->input('guest_id'))->first();
        if (!$guest) {
            return response()->json(['error' => 'Guest not found'], 404);
        }

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // check if guest has wallet
        $wallet = Wallet::where('guest_id', $request->input('guest_id'))->first();

        // check if guest has EarnPointsHistory
        // $earnPointHistory = EarnPointsHistory::where('guest_id', $request->input('guest_id'))->first();

        $cardType = null;
        if ($venue->venueType->definition === 'accommodation') {
            $cardType = config('enduser-card-types.ACCOMMODATION_GUEST_CARD.type');
        }

        if ($venue->venueType->definition === 'retail') {
            $cardType = config('enduser-card-types.RETAIL_CUSTOMER_CARD.type');
        }

        if ($venue->venueType->definition === 'food') {
            $cardType = config('enduser-card-types.GUEST_CARD.type');
        }

        if ($venue->venueType->definition === 'sport_entertainment') {
            $cardType = config('enduser-card-types.SPORT_AND_ENTERTAINMENT_MEMBERSHIP_CARD.type');
        }

        if ($venue->venueType->definition === 'healthcare') {
            $cardType = config('enduser-card-types.ELECTRONIC_HEALTH_CARD.type');
        }

        // Create the END USER CARD
        $template = EndUserCard::create([
            'venue_id' => $venue->id,
            'guest_id' => $request->input('guest_id'),
            'wallet_id' => $wallet?->id ?? null,
            'earn_points_history_id' => null,
            'card_type' => $cardType ?? config('cardtypes.GUEST_CARD'),
            'uuid' => uuid_create(UUID_TYPE_RANDOM),
            'status' => 'active',
            'is_verified' => false,
            'issued_at' => $request->input('issued_at'),
            'expiration_date' => $request->input('expiration_date'),
            'last_scanned_at' => null,
            'notes' => $request->input('notes'),
        ]);

        return response()->json(['message' => 'End user card created successfully', 'data' => $template], 200);
    }

    public function destroy($id): JsonResponse
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

        $endUserCard = EndUserCard::where('id', $id)->where('venue_id', $venue->id)->first();

        if (!$endUserCard) {
            return response()->json(['message' => 'The requested end user card does not exist'], 404);
        }
        $endUserCard->delete();
        return response()->json(['message' => 'Successfully deleted the end user card'], 200);
    }


    // Update the end user card
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
            'id' => 'required|exists:end_user_cards,id',
            'issued_at' => 'required',
            'expiration_date' => 'required',
            'notes' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $card = EndUserCard::where('id', $request->input('id'))->where('venue_id', $venue->id)->first();
        if (!$card) {
            return response()->json(['error' => 'Card not found'], 404);
        }

        $card->issued_at = $request->input('issued_at') ?? $card->issued_at;
        $card->expiration_date = $request->input('expiration_date') ?? $card->expiration_date;
        $card->notes = $request->input('notes') ?? $card->notes;
        $card->save();

        return response()->json(['message' => 'Card status updated successfully', 'data' => $card]);
    }

    // Function to change the status of a card
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
            'id' => 'required|exists:end_user_cards,id',
            'status' => 'required|in:active,inactive,expired',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $card = EndUserCard::where('id', $request->input('id'))->where('venue_id', $venue->id)->first();
        if (!$card) {
            return response()->json(['error' => 'Card not found'], 404);
        }

        $card->status = $request->input('status');
        $card->save();

        return response()->json(['message' => 'Card status updated successfully', 'data' => $card]);
    }

    // Function to verify a card
    public function verify(Request $request): JsonResponse
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
            'id' => 'required|exists:end_user_cards,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $card = EndUserCard::with('guest')->where('id', $request->input('id'))->where('venue_id', $venue->id)->first();
        if (!$card) {
            return response()->json(['error' => 'Card not found'], 404);
        }

        $card->is_verified = true;
        $card->save();

        // send email to guest
        Mail::to($card->guest?->email)->send(new EndUserCardCreateEmail($venue->name));

        return response()->json(['message' => 'Card verified successfully', 'data' => $card]);
    }

    public function showByID($id): \Illuminate\Http\JsonResponse
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

        $card = EndUserCard::where('id', $id)->where('venue_id', $venue->id)->first();

        if (!$card) {
            return response()->json(['error' => 'Card not found'], 404);
        }

        // eager load relationships with the guest model, wallet model and earnPointsHistories relationship

        $card->load('guest', 'wallet', 'guest.earnPointsHistory');
        $card->s3_path = $card->s3_path ? Storage::disk('s3')->temporaryUrl($card->s3_path , '+5 minutes') : null;
        return response()->json(['data' => $card]);
    }


    public function showByUUID($uuid): \Illuminate\Http\JsonResponse
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

        // check with UUID
        $card = EndUserCard::where('uuid', $uuid)->where('venue_id', $venue->id)->first();

        if (!$card) {
            return response()->json(['error' => 'Card not found'], 404);
        }

        // check if card is not expired and should be verified before and also check issued date
        if (
            $card->status !== 'active' ||
            $card->is_verified === false ||
            Carbon::parse($card->issued_date) > Carbon::now() ||
            Carbon::parse($card->expiration_date) < Carbon::now()

        ) {
            return response()->json(['error' => 'Card can not be scanned'], 400);
        }

        if ($venue->venueType->definition === 'food') {
            $guest = Guest::where([
                ['is_main', true],
                ['restaurant_id', $venue->id],
            ])->with('reservations.promotion', 'loyaltyProgramGuests', 'wallet', 'earnPointsHistory')->find($card->guest_id);

            if (!$guest) {
                return response()->json(['error' => 'Guest not found'], 404);
            }
            $firstRegistration = new StdClass;
            $firstRegistration->name = 'Registration';
            $firstRegistration->date = Carbon::parse($guest->created_at)->format('Y-m-d H:i:s');

            $firstReservation = new StdClass;
            $firstReservation->name = 'First reservation completed';
            $firstReservation->date = Carbon::parse($guest->reservations->min('start_time'))->format('Y-m-d H:i:s');

            $enrolledToLoyalty = new StdClass;
            $enrolledToLoyalty->name = 'Enrolled to loyalty program';
            $enrolledToLoyalty->date = count($guest->loyaltyProgramGuests) > 0 ? Carbon::parse($guest->loyaltyProgramGuests->min('created_at'))->format('Y-m-d H:i:s') : null;

            $activities = [
                $firstRegistration,
                $firstReservation,
                $enrolledToLoyalty
            ];

            $promotions = [];
            $loyaltyProgramsUsed = [];

            foreach ($guest->reservations as $reservation) {
                if ($reservation->order && $reservation->order->promotion_id) {
                    $promotion = $reservation->order->promotion_id;
                    $promotionData = Promotion::where('id', $promotion)->first();
                    $promotions[] = [
                        'promotion_id' => $promotionData->id,
                        'title' => $promotionData->title,
                        'discount_value' => $reservation->order->discount_total,
                        'reservation_id' => $reservation->id,
                        'used_on' => Carbon::parse($reservation->order->created_at)->format('Y-m-d H:i:s')
                    ];
                }
            }

            foreach ($guest->loyaltyProgramGuests as $loyaltyGuest) {
                $loyaltyProgramsUsed[] = [
                    'name' => $loyaltyGuest->loyaltyProgram->title,
                    'enrolled_on_date' => Carbon::parse($loyaltyGuest->created_at)->format('Y-m-d H:i:s'),
                    'days_on_program' => Carbon::parse($loyaltyGuest->created_at)->diffInDays(Carbon::now()) + 1,
                ];
            }

            $guest->promotions = $promotions;
            $guest->activities = $activities;
            $guest->loyalty_programs_used = $loyaltyProgramsUsed;
        }

        if ($venue->venueType->definition === 'accommodation') {
            $guest = Guest::where([
                ['is_main', true],
                ['restaurant_id', $venue->id],
            ])->find($card->guest_id);
        }

        if ($venue->venueType->definition === 'retail') {
            $guest = Guest::where([
                ['is_main', true],
                ['restaurant_id', $venue->id],
            ])->find($card->guest_id);
        }

        if ($venue->venueType->definition === 'sport_entertainment') {
            $guest = Guest::where([
                ['is_main', true],
                ['restaurant_id', $venue->id],
            ])->find($card->guest_id);
        }

        if ($venue->venueType->definition === 'healthcare') {
            $guest = Guest::where([
                ['is_main', true],
                ['restaurant_id', $venue->id],
            ])->find($card->guest_id);
        }

        $endUserCard = new StdClass;
        $endUserCard->card_data = $card;
        $endUserCard->guest_data = $guest;

        // update wallet balance
        $pointsEarned =  10;

        // Update guest's wallet balance with the earned points
        if (!$guest->wallet) {
            Wallet::create([
                'guest_id' => $guest->id,
                'balance' => $pointsEarned,
                'venue_id' => $venue->id,
            ]);
        } else {
            $guest->wallet->increment('balance', $pointsEarned);
        }

        $guest->earnPointsHistory()->create([
            'points_earned' => $pointsEarned,
            'venue_id' => $venue->id,
            'end_user_card_id' => $card->id,
        ]);

        // update last_scanned_at
        $card->last_scanned_at = Carbon::now();
        $card->wallet_id = $guest->wallet->id;
        $card->save();

        // send email to guest
        Mail::to($guest->email)->send(new EndUserCardCheckinEmail($pointsEarned, $venue->name));

        return response()->json(['data' => $endUserCard]);
    }

    public function generateQrCode($uuid): \Illuminate\Http\Response|JsonResponse|\Illuminate\Contracts\Foundation\Application|ResponseFactory
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

        // check with UUID
        $card = EndUserCard::where('uuid', $uuid)->where('venue_id', $venue->id)->first();

        if (!$card) {
            return response()->json(['error' => 'Card not found'], 404);
        }


        // Create a link based on the card UUID
        $link = 'https://admin.venueboost.io/vb-cards/' . $card->uuid . '/detail';
        $qrcode = QrCode::size(300)->generate($link);

        // Define the file name for the QR code
        $fileName = 'qr_code_' . $card->uuid . '.png';

        // Save the QR code to a temporary file
        $temporaryFilePath = storage_path('app/public/') . $fileName;
        file_put_contents($temporaryFilePath, $qrcode);

        // TODO: confirm if the right qr code is uploaded to aws s3
        // Upload QR code to AWS S3
        $path = Storage::disk('s3')->putFileAs(
            'venue-end-user-c-qr-codes/' . $venue->venueType->short_name . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)),
            $temporaryFilePath,
            $fileName,
        );

        // update end user card with qr code s3 path and url
        $card->s3_path = $path;
        $card->url = $link;
        $card->save();
        // get card data
        $card = EndUserCard::where('uuid', $uuid)->where('venue_id', $venue->id)->first();
        $card->s3_path = Storage::disk('s3')->temporaryUrl($card->s3_path , '+5 minutes');

        return response(
            [
                'data' => $card
            ]
        );
    }

    public function guestsWithoutCards(): JsonResponse
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
        $guestsWithoutCards = Guest::where('restaurant_id', $venue->id)
            ->whereDoesntHave('endUserCard')
            ->get();



        return response()->json(['data' => $guestsWithoutCards], 200);

    }



}
