<?php
namespace App\Http\Controllers\v1;
use App\Enums\FeatureNaming;
use App\Http\Controllers\Controller;

use App\Models\AccommodationDetail;
use App\Models\AccommodationHostProfile;
use App\Models\AccommodationPaymentCapability;
use App\Models\AccommodationRule;
use App\Models\AdditionalFeeAndCharge;
use App\Models\AdditionalFeeAndChargesName;
use App\Models\Bed;
use App\Models\BreakfastDetail;
use App\Models\Booking;
use App\Models\CardPreference;
use App\Models\Feature;
use App\Models\FeatureUsageCredit;
use App\Models\FeatureUsageCreditHistory;
use App\Models\Guest;
use App\Models\Discount;
use App\Models\Facility;
use App\Models\Gallery;
use App\Models\IndustryBrandCustomizationElement;
use App\Models\Language;
use App\Models\OpeningHour;
use App\Models\ParkingDetail;
use App\Models\Photo;
use App\Models\PlanFeature;
use App\Models\PricePerNight;
use App\Models\PricingAndCalendar;
use App\Models\RentalCustomRule;
use App\Models\RentalUnit;
use App\Models\Restaurant;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\VenueType;
use App\Models\VenueWhitelabelCustomization;
use App\Services\ApiUsageLogger;
use Carbon\Carbon;
use Google\Cloud\Storage\Connection\Rest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use stdClass;
use function event;
use function response;

/**
 * @OA\Info(
 *   title="Accommodation API",
 *   version="1.0",
 *   description="This API allows use Accommodation Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Accommodation",
 *   description="Operations related to Accommodation"
 * )
 */


class AccommodationController extends Controller
{

    protected ApiUsageLogger $apiUsageLogger;

    public function __construct(ApiUsageLogger $apiUsageLogger)
    {
        $this->apiUsageLogger = $apiUsageLogger;
    }

    public function createRentalUnit(Request $request): \Illuminate\Http\JsonResponse
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

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Generate a unique rental unit code
        do {
            $code = mt_rand(1000000000, 9999999999);  // generate a random 10-digit number
        } while (RentalUnit::where('unit_code', $code)->exists());  // check if it's unique


        $data['name'] = $request->input('name');
        $data['about'] = $request->input('about');
        $data['about_space'] = $request->input('about_space');
        $data['about_guest_access'] = $request->input('about_guest_access');
        $data['unit_code'] = $code;
        $data['accommodation_type'] = 'Apartment';
        $data['venue_id'] = $venue->id;

        $venueType = VenueType::where('id', $venue->venue_type)->first();
        $data['accommodation_venue_type'] = $venueType->short_name;

        $rentalUnit = RentalUnit::create($data);


        try {

            $featureName = FeatureNaming::rental_units;

            $featureId = Feature::where('name', $featureName)
                ->where('active', 1)
                ->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = null;

            $activeSubscription = Subscription::with(['subscriptionItems.pricingPlanPrice', 'pricingPlan'])
                ->where('venue_id', $venue->id)
                ->where(function ($query) {
                    $query->where('status', 'active')
                        ->orWhere('status', 'trialing');
                })
                ->orderBy('created_at', 'desc')
                ->first();
            $planName = $activeSubscription?->pricingPlan->name;
            $planId = $activeSubscription?->pricing_plan_id;
            if ($planName === 'Discover') {
                // Check Count of the rental unit used on FeatureUsageCreditHistory with feature_id
                $featureUsageCreditHistoryCount = FeatureUsageCreditHistory::where('feature_id', $featureId)->get();
                // get usage credit for this feature
                $featureUsageCredit = PlanFeature::where('feature_id', $featureId)->where('plan_id', $planId)->first()->usage_credit;
                // if count is less than usage credit then deduct from usage credit
                if ($featureUsageCreditHistoryCount->count() < $featureUsageCredit) {
                    // find feature usage credit for this venue
                    $featureUsageCredit = FeatureUsageCredit::where('venue_id', $venue->id)->first();
                    $featureUsageCredit->update([
                        'balance' => $featureUsageCredit->balance - 1
                    ]);
                    // create feature usage credit history
                    $featureUsageCreditHistory = new FeatureUsageCreditHistory();
                    $featureUsageCreditHistory->feature_id = $featureId;
                    $featureUsageCreditHistory->used_at_feature = $featureName;
                    $featureUsageCreditHistory->feature_usage_credit_id = $featureUsageCredit->id;
                    $featureUsageCreditHistory->transaction_type = 'decrease';
                    $featureUsageCreditHistory->amount = 1;
                    $featureUsageCreditHistory->save();
                }
            }


            $this->apiUsageLogger->log($featureId, $venue->id, 'Add Manual Rental Unit - POST', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json(['message' => 'Rental Unit created successfully', 'rentalUnit' => $rentalUnit]);
    }

    public function listRentalUnits(): \Illuminate\Http\JsonResponse
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

        $rentalUnits = RentalUnit::where('venue_id', $venue->id)
            ->whereNull('deleted_at')
            ->get();

        // for each rental units, return name, address and created at in day, month, year format

        $responseRentalUnits = [];

        foreach ($rentalUnits as $rentalUnit) {
            $responseRentalUnit = new stdClass();
            $responseRentalUnit->id = $rentalUnit->id;
            $responseRentalUnit->name = $rentalUnit->name;
            $responseRentalUnit->address = $rentalUnit->address;
            $responseRentalUnit->unit_code = $rentalUnit->unit_code;
            $responseRentalUnit->created_at = $rentalUnit->created_at->format('d M Y');
            $responseRentalUnit->can_delete = 0; // TODO: we should allow delete only if there are no fk constraints
            $url = $rentalUnit->unit_code ? 'https://venueboost.io/rental/'.$rentalUnit->unit_code : null;
            $responseRentalUnit->url = $url;
            $responseRentalUnits[] = $responseRentalUnit;
        }

        // determine if venues has used all credits for the feature for the month

        $hasUsedAllCredits = false;
        try {

            $featureName = FeatureNaming::rental_units;

            $featureId = Feature::where('name', $featureName)
                ->where('active', 1)
                ->where('feature_category', $venue->venueType->definition)->first()->id;
            $subFeatureId = null;

            $activeSubscription = Subscription::with(['subscriptionItems.pricingPlanPrice', 'pricingPlan'])
                ->where('venue_id', $venue->id)
                ->where(function ($query) {
                    $query->where('status', 'active')
                        ->orWhere('status', 'trialing');
                })
                ->orderBy('created_at', 'desc')
                ->first();
            $planName = $activeSubscription?->pricingPlan->name;
            $planId = $activeSubscription?->pricing_plan_id;
            if ($planName === 'Discover') {
                // Check Count of the rental unit used on FeatureUsageCreditHistory with feature_id
                $featureUsageCreditHistoryCount = FeatureUsageCreditHistory::where('feature_id', $featureId)->get();
                // get usage credit for this feature
                $featureUsageCredit = PlanFeature::where('feature_id', $featureId)->where('plan_id', $planId)->first()->usage_credit;
                // if count is same as usage credit
                if ($featureUsageCreditHistoryCount->count() >= $featureUsageCredit) {
                    $hasUsedAllCredits = true;
                }

            }

            $this->apiUsageLogger->log($featureId, $venue->id, 'List Rental Unit - GET', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json([
            'data' => $responseRentalUnits,
            'message' => 'Rental Units retrieved successfully',
            'hasUsedAllCredits' => $hasUsedAllCredits,
        ]);
    }

    public function showRentalUnit($id): \Illuminate\Http\JsonResponse
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
            return response()->json(['error' => 'Rental Unit not found'], 404);
        }

        $responseRentalUnit = new StdClass();
        $nameAndLocation = new StdClass();
        $accommodationSetup = new StdClass();
        $photosAndFacilities = new StdClass();
        $pricingAndCalendar = new StdClass();
        $policiesAndRules = new StdClass();


        $nameAndLocation->name = $rentalUnit->name;

        $accommodationAddress = new StdClass();
        $accommodationAddress->address = $rentalUnit->address;
        $accommodationAddress->latitude = $rentalUnit->latitude;
        $accommodationAddress->longitude = $rentalUnit->longitude;
        $accommodationAddress->country = $rentalUnit->country;

        $nameAndLocation->address = $accommodationAddress;
        $nameAndLocation->about = $rentalUnit->about;
        $nameAndLocation->about_space = $rentalUnit->about_space;
        $nameAndLocation->about_guest_access = $rentalUnit->about_guest_access;
        $nameAndLocation->currency = $rentalUnit->currency;

        $url = $rentalUnit->unit_code ? 'https://venueboost.io/rental/'.$rentalUnit->unit_code : null;


        $gallery = Gallery::where('rental_unit_id', $rentalUnit->id)->with('photo')->get();

        $modifiedGallery = $gallery->map(function ($item) {
            return [
                'photo_id' => $item->photo_id,
                'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
            ];
        });

        $photosAndFacilities->gallery = $modifiedGallery;
        $photosAndFacilities->vr_link = $rentalUnit->vr_link;

        // get facilities of rental from rental_unit_facility table
        $facilities = $rentalUnit->facilities()->get();
        $photosAndFacilities->facilities = $facilities;

        $nameAndLocation->breakfast_details = $rentalUnit->breakfast_detail;
        $nameAndLocation->parking_details = $rentalUnit->parking_detail;

        $languages = $rentalUnit->languages()->get();
        $nameAndLocation->languages = $languages;
        $nameAndLocation->accommodation_host_profile = $rentalUnit->accommodation_host_profile;
        $nameAndLocation->guest_interaction = $rentalUnit->guest_interaction;
        $nameAndLocation->unit_status = $rentalUnit->unit_status;

        $responseRentalUnit->name_and_location = $nameAndLocation;

        $accommodationSetup->accommodation_details = $rentalUnit->accommodation_detail;



        $rooms = RentalUnit::with('rooms.beds')->find($rentalUnit->id)->rooms;

        $rooms = $rooms->map(function ($room) {
            $room->name = typeToReadableName($room->type);

            $gallery = Gallery::where('room_id', $room->id)->with('photo')->get();

            $modifiedGallery = $gallery->map(function ($item) {
                return [
                    'photo_id' => $item->photo_id,
                    'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
                ];
            });

            $room->gallery = $modifiedGallery;
            return $room;
        });

        $accommodationSetup->rooms = $rooms;

        $accommodationSetup->accommodation_type = $rentalUnit->accommodation_type;
        $accommodationSetup->unit_floor = $rentalUnit->unit_floor;
        $accommodationSetup->year_built = $rentalUnit->year_built;

        $responseRentalUnit->accommodation_setup = $accommodationSetup;
        $responseRentalUnit->photos_and_facilities = $photosAndFacilities;

        // pricing and calendar tab
        // get price per nights
        $pricingAndCalendar->price_per_nights = $rentalUnit->price_per_nights;
        $pricingAndCalendar->booking_acceptance_date = $rentalUnit->pricing_and_calendar?->booking_acceptance_date;
        $pricingAndCalendar->booking_acceptance_type = $rentalUnit->pricing_and_calendar?->booking_acceptance_date ? 'specific_date' : 'asap';
        $pricingAndCalendar->card_capability = $rentalUnit->accommodation_payment_capability?->can_charge_credit_cards;
        $pricingAndCalendar->cash_capability = $rentalUnit->accommodation_payment_capability?->accept_later_cash_payment;
        $pricingAndCalendar->card_preferences = $rentalUnit->card_preferences ?? [];
        $responseRentalUnit->pricing_and_calendar = $pricingAndCalendar;


        // policies and rules tab
        $policiesAndRules->accommodation_rules = $rentalUnit->accommodation_rules;
        $policiesAndRules->custom_accommodations_rules = $rentalUnit->rental_custom_rules;
        $policiesAndRules->cancellation_days = $rentalUnit->pricing_and_calendar?->cancellation_days;
        $policiesAndRules->prepayment_amount = $rentalUnit->pricing_and_calendar?->prepayment_amount;

        $additionalFeesAndChargesArray = [];
        $additionalFeesAndCharges = $rentalUnit->additional_fee_and_charges()->with('feeName')->get();

        // check if for this venue if there are no fee_name_id = 1, or 2, or 3 then create those 3 with amount 0
        $feeNameIds = $additionalFeesAndCharges->pluck('fee_name_id')->toArray();
        $feeNameIdsToCreate = array_diff([1, 2, 3], $feeNameIds);
        if (count($feeNameIdsToCreate) > 0) {
            foreach ($feeNameIdsToCreate as $feeNameId) {
                $additionalFeeAndCharge = new AdditionalFeeAndCharge();
                $additionalFeeAndCharge->rental_unit_id = $rentalUnit->id;
                $additionalFeeAndCharge->venue_id = $venue->id;
                $additionalFeeAndCharge->fee_name_id = $feeNameId;
                $additionalFeeAndCharge->amount = 0;
                $additionalFeeAndCharge->save();
            }
            $additionalFeesAndCharges = $rentalUnit->additional_fee_and_charges()->with('feeName')->get();
        }
        foreach ($additionalFeesAndCharges as $fee) {
            $name = $fee->feeName->name;
            $description = $fee->feeName->description;
            $amount = $fee->amount;

            // Push the formatted data into the array
            $additionalFeesAndChargesArray[] = [
                'id' => $fee->id, // this is the primary key of the fee name table, not the id of the fee name in the pivot table
                'name' => $name,
                'description' => $description,
                'amount' => $amount,
            ];
        }
        $policiesAndRules->additional_fees_and_charges = $additionalFeesAndChargesArray;
        $responseRentalUnit->policies_and_rules = $policiesAndRules;

        // get facilities grouped by category

        $facilities = Facility::getFacilitiesGroupedByCategory();

        $icsUrl = $rentalUnit->getIcsUrl();
        return response()->json([
            'rental_unit' => $responseRentalUnit,
            'url' => $url,
            'ics_url' => $icsUrl,
            'message' => 'Rental Unit retrieved successfully',
            'facilities' => $facilities,
            'languages' => Language::all(),
            'bed_types' => Bed::all(),
        ]);
    }

    public function destroyRentalUnit($id): JsonResponse
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

        // TODO: we should allow delete only if there are no fk constraints
        // Add later

        $rentalUnit->delete();


        return response()->json(['message' => 'Successfully deleted the rental unit'], 200);
    }

    public function updateNameLocation(Request $request, $id): \Illuminate\Http\JsonResponse
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

        $input = $request->all();

        $rules = [
            'type' => 'required|in:name,location', // 'type' should either be 'name' or 'location'
        ];

        if (!isset($input['type'])) {
            return response()->json(['error' => 'Type is required'], 400);
        }

            // Applying the validation rules based on the 'type' input.
        switch ($input['type']) {
            case 'name':
                $rules = array_merge($rules, [
                    'name' => 'required|string',
                    'about' => 'required|string',
                    'about_space' => 'nullable|string',
                    'about_guest_access' => 'nullable|string',
                    'unit_status' => 'nullable|string',
                    'guest_interaction' => 'nullable|string',
                    'currency' => [
                        'nullable',
                        Rule::in(['ALL', 'USD', 'EUR'])
                    ],
                ]);
                break;

            case 'location':
                $rules = array_merge($rules, [
                    'country' => 'string',
                    'address' => 'required|string',
                    'latitude' => 'nullable|numeric',  // Assuming 'numeric' validation as I'm unaware of any in-built 'coordinates' validation.
                    'longitude' => 'nullable|numeric', // Assuming 'numeric' validation.
                ]);
                break;
        }

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $rentalUnit->name = $request->name ?? $rentalUnit->name;
        $rentalUnit->about = $request->about ?? $rentalUnit->about;
        $rentalUnit->about_space = $request->about_space ?? $rentalUnit->about_space;
        $rentalUnit->about_guest_access = $request->about_guest_access ?? $rentalUnit->about_guest_access;
        $rentalUnit->unit_status = $request->unit_status ?? $rentalUnit->unit_status;
        $rentalUnit->guest_interaction = $request->guest_interaction ?? $rentalUnit->guest_interaction;
        $rentalUnit->currency = $request->currency ?? $rentalUnit->currency;
        $rentalUnit->country = $request->country ?? $rentalUnit->country;
        $rentalUnit->address = $request->address ?? $rentalUnit->address;
        $rentalUnit->latitude = $request->latitude ?? $rentalUnit->latitude;
        $rentalUnit->longitude = $request->longitude ?? $rentalUnit->longitude;

        $rentalUnit->save();

        return response()->json(['message' => 'Rental unit updated successfully']);
    }

    public function updatePricingCalendar(Request $request, $id): \Illuminate\Http\JsonResponse
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

        $input = $request->all();

        $rules = [
            'type' => 'required|in:cash_card_capability,card_preferences,booking_acceptance_date,price_per_night', // 'type' should either be 'cash_card_capability' or 'card_preferences' or 'booking_acceptance_date
        ];

        if (!isset($input['type'])) {
            return response()->json(['error' => 'Type is required'], 400);
        }

        // Applying the validation rules based on the 'type' input.
        switch ($input['type']) {
            case 'cash_card_capability':
                $rules = array_merge($rules, [
                    'card_capability' => 'required|boolean',
                    'cash_capability' => 'required|boolean',
                ]);
                break;
            case 'price_per_night':
                $rules = array_merge($rules, [
                    'price' => 'required|numeric',
                    'nr_guests' => 'required|numeric',
                ]);
                break;
            case 'card_preferences':
                $rules = array_merge($rules, [
                    'card_preferences' => 'array',
                ]);
                break;
            case 'booking_acceptance_date':
                $rules = array_merge($rules, [
                    'booking_acceptance_date' => 'nullable|date',
                ]);
                break;
        }

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        if ($input['type'] === 'cash_card_capability') {
            // check if it has AccommodationPaymentCapability
            $accommodationPaymentCapability = $rentalUnit->accommodation_payment_capability;

            if (!$accommodationPaymentCapability) {
                $accommodationPaymentCapability = new AccommodationPaymentCapability();
                $accommodationPaymentCapability->can_charge_credit_cards = $request->card_capability;
                $accommodationPaymentCapability->accept_later_cash_payment = $request->cash_capability;
                $accommodationPaymentCapability->rental_unit_id = $rentalUnit->id;
                $accommodationPaymentCapability->venue_id = $venue->id;


            } else {
                $accommodationPaymentCapability->can_charge_credit_cards = $request->card_capability ?? $accommodationPaymentCapability->can_charge_credit_cards;
                $accommodationPaymentCapability->accept_later_cash_payment = $request->cash_capability ?? $accommodationPaymentCapability->accept_later_cash_payment;
                $accommodationPaymentCapability->updated_at = Carbon::now();
            }


            $accommodationPaymentCapability->save();
        }

        if ($input['type'] === 'card_preferences') {
            // check if it has CardPreference
            $accommodationCardPreference = $rentalUnit->card_preferences;

            // if this empty delete all card preferences of this rental unit and venue

            if (count($request->card_preferences) < 1) {
                foreach ($accommodationCardPreference as $cardPreference) {
                    $cardPreference->delete();
                }
            }

            if (count($accommodationCardPreference) < 1) {

                // now the array will of request will be something like this ['Visa', 'Maestro']
                // check if those type exists in card preferences, if not add
                // if those are not provided but exists in card preferences, remove

                foreach ($request->card_preferences as $card) {
                    $cardPreference = CardPreference::where('card_type', $card)->where('rental_unit_id', $rentalUnit->id)->where('venue_id', $venue->id)->first();
                    if (!$cardPreference) {
                        $cardPreference = new CardPreference();
                        $cardPreference->card_type = $card;
                        $cardPreference->rental_unit_id = $rentalUnit->id;
                        $cardPreference->venue_id = $venue->id;
                        $cardPreference->save();
                    }
                }



            } else {

                // Get all existing card preferences for the rental unit
                $existingCardPreferences = CardPreference::where('rental_unit_id', $rentalUnit->id)->pluck('card_type')->toArray();

                foreach ($request->card_preferences as $card) {
                    $cardPreference = CardPreference::where('card_type', $card)
                        ->where('rental_unit_id', $rentalUnit->id)
                        ->first();

                    if (!$cardPreference) {
                        $cardPreference = new CardPreference();
                        $cardPreference->card_type = $card;
                        $cardPreference->rental_unit_id = $rentalUnit->id;
                        $cardPreference->venue_id = $venue->id;

                        $cardPreference->save();
                    } else {
                        // If this card type is present in the database, remove it from the existingCardPreferences array
                        if (($key = array_search($card, $existingCardPreferences)) !== false) {
                            unset($existingCardPreferences[$key]);
                        }
                    }
                }

                // At this point, $existingCardPreferences will only contain card types that were not present in the request
                // So we'll delete these card preferences
                foreach ($existingCardPreferences as $cardTypeToRemove) {
                    CardPreference::where('card_type', $cardTypeToRemove)
                        ->where('rental_unit_id', $rentalUnit->id)
                        ->delete();
                }

            }
        }

        if ($input['type'] === 'price_per_night') {

            // check if the number of guests price per night already exist
            $pricePerNight = PricePerNight::where('venue_id', $venue->id)->where('rental_unit_id', $rentalUnit->id)->where('nr_guests', $request->nr_guests)->first();

            if($pricePerNight) {
                return response()->json(['error' => 'A pricing entry for the specified number of guests already exists.'], 400);
            }
            // create price per night
            $pricePerNight = new PricePerNight();
            $pricePerNight->rental_unit_id = $rentalUnit->id;
            $pricePerNight->venue_id = $venue->id;
            $pricePerNight->nr_guests = $request->nr_guests;
            $pricePerNight->price = $request->price;
            $pricePerNight->discount = $request->discount ?? 0;

            $pricePerNight->save();
        }

        if ($input['type'] === 'booking_acceptance_date') {
            // check if it has PricingAndCalendar
            $accommodationPricingAndCalendar = $rentalUnit->pricing_and_calendar;
            if (!$accommodationPricingAndCalendar) {
                $accommodationPricingAndCalendar = new PricingAndCalendar();
                $accommodationPricingAndCalendar->booking_acceptance_date = $request->booking_acceptance_date === null ? Carbon::now() : $request->booking_acceptance_date;
                $accommodationPricingAndCalendar->price_per_night = 0;
                $accommodationPricingAndCalendar->cancellation_days = 1;
                $accommodationPricingAndCalendar->rental_unit_id = $rentalUnit->id;
                $accommodationPricingAndCalendar->venue_id = $venue->id;
            } else {
                $accommodationPricingAndCalendar->booking_acceptance_date =  $request->booking_acceptance_date === null ? Carbon::now() : $request->booking_acceptance_date;
                $accommodationPricingAndCalendar->updated_at = Carbon::now();
            }

            $accommodationPricingAndCalendar->save();
        }

        return response()->json(['message' => 'Rental unit updated successfully']);
    }

    public function updatePoliciesAndRules(Request $request, $id): \Illuminate\Http\JsonResponse
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

        $input = $request->all();

        $rules = [
            'type' => 'required|in:cancellation_days,prepayment_amount', // 'type' should either be 'prepayment_amount' or 'cancellation_days'
        ];

        if (!isset($input['type'])) {
            return response()->json(['error' => 'Type is required'], 400);
        }

        // Applying the validation rules based on the 'type' input.
        switch ($input['type']) {
            case 'prepayment_amount':
                $rules = array_merge($rules, [
                    'prepayment_amount' => 'required|numeric',
                ]);
                break;
            case 'cancellation_days':
                $rules = array_merge($rules, [
                    'cancellation_days' => 'required|numeric',
                ]);
                break;
        }

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }


        if ($input['type'] === 'cancellation_days') {
            // check if it has PricingAndCalendar
            $accommodationPricingAndCalendar = $rentalUnit->pricing_and_calendar;
            if (!$accommodationPricingAndCalendar) {
                $accommodationPricingAndCalendar = new PricingAndCalendar();
                $accommodationPricingAndCalendar->booking_acceptance_date = Carbon::now();
                $accommodationPricingAndCalendar->price_per_night = 0;
                $accommodationPricingAndCalendar->cancellation_days = $request->cancellation_days;
                $accommodationPricingAndCalendar->rental_unit_id = $rentalUnit->id;
                $accommodationPricingAndCalendar->venue_id = $venue->id;
            } else {
                $accommodationPricingAndCalendar->cancellation_days =  $request->cancellation_days;
                $accommodationPricingAndCalendar->updated_at = Carbon::now();
            }

            $accommodationPricingAndCalendar->save();
        }

        if ($input['type'] === 'prepayment_amount') {
            // check if it has PricingAndCalendar
            $accommodationPricingAndCalendar = $rentalUnit->pricing_and_calendar;
            if (!$accommodationPricingAndCalendar) {
                $accommodationPricingAndCalendar = new PricingAndCalendar();
                $accommodationPricingAndCalendar->booking_acceptance_date = Carbon::now();
                $accommodationPricingAndCalendar->price_per_night = 0;
                $accommodationPricingAndCalendar->prepayment_amount = $request->prepayment_amount;
                $accommodationPricingAndCalendar->rental_unit_id = $rentalUnit->id;
                $accommodationPricingAndCalendar->venue_id = $venue->id;
            } else {
                $accommodationPricingAndCalendar->prepayment_amount =  $request->prepayment_amount;
                $accommodationPricingAndCalendar->updated_at = Carbon::now();
            }

            $accommodationPricingAndCalendar->save();
        }

        return response()->json(['message' => 'Rental unit updated successfully']);
    }

    public function updatePricePerNight(Request $request, $id): \Illuminate\Http\JsonResponse
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

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'price' => 'required|numeric',
            'discount' => 'nullable|numeric',
            'nr_guests' => 'required|integer', // Add validation for number of guests
        ]);


        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Find the PricePerNight entry by its ID
        $pricePerNight = PricePerNight::where('id', $request->id)
            ->where('rental_unit_id', $rentalUnit->id)
            ->where('venue_id', $venue->id)
            ->first();

        if (!$pricePerNight) {
            return response()->json(['message' => 'The requested price per night does not exist'], 404);
        }

        // Update the entry with the new values
        $pricePerNight->price = $request->price ?? $pricePerNight->price;
        $pricePerNight->discount = $request->discount ?? $pricePerNight->discount;
        $pricePerNight->nr_guests = $request->nr_guests ?? $pricePerNight->nr_guests;
        $pricePerNight->save();


        return response()->json(['message' => 'Price per night updated successfully']);
    }

    public function updateAccommodationSetup(Request $request, $id): \Illuminate\Http\JsonResponse
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

        $input = $request->all();

        $rules = [
            'type' => 'required|in:accommodation_details,accommodation_rules_extra,breakfast_details,parking_details,accommodation_rules,accommodation_host_profile,languages,accommodation_vr_link', // 'type' should either be 'accommodation_detail', 'breakfast_detail', 'parking_detail', 'accommodation_rules', 'accommodation_host_profile' or 'languages'
        ];

        if (!isset($input['type'])) {
            return response()->json(['error' => 'Type is required'], 400);
        }

        // Applying the validation rules based on the 'type' input.
        switch ($input['type']) {
            case 'accommodation_details':

                $rules = array_merge($rules, [
                   'guest_limit' => 'required|numeric',
                   'year_built' => 'numeric',
                   'unit_floor' => 'numeric',
                   'square_metres' => 'required|numeric',
                   'accommodation_type' => 'string',
                ]);
                break;

            case 'breakfast_details':
                $rules = array_merge($rules, [
                    'offers_breakfast' => 'required|boolean',
                ]);
                break;
            case 'parking_details':
                $rules = array_merge($rules, [
                    'availability' => 'required|in:free,paid,no',
                    'reservation' => 'required|in:needed,not_needed',
                    'location' => 'required|in:on_site,off_site',
                    'park_type' => 'required|in:private,public',
                ]);
                break;
            case 'accommodation_rules':
                $rules = array_merge($rules, [
                    'smoking_allowed' => 'required|boolean',
                    'pets_allowed' => 'required|boolean',
                    'parties_allowed' => 'required|boolean'
                ]);
                break;
            case 'accommodation_rules_extra':
                $rules = array_merge($rules, [
                    'check_in_from' => 'required|date_format:H:i',
                    'check_in_until' => 'required|date_format:H:i',
                    'checkout_from' => 'required|date_format:H:i',
                    'checkout_until' => 'required|date_format:H:i',
                ]);
                break;
            case 'accommodation_host_profile':
                $rules = array_merge($rules, [
                    'host_name' => 'required|string',
                    'about_host' => 'required|string',
                ]);
                break;
            case 'languages':
                $rules = array_merge($rules, [
                    'languages' => 'required|array',
                ]);
                break;
            case 'accommodation_vr_link':
                $rules = array_merge($rules, [
                    'vr_link' => 'required|string',
                ]);
                break;
        }

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        if ($input['type'] === 'accommodation_details') {
            // check if it has accommodation_detail
            $accommodationDetails = $rentalUnit->accommodation_detail;

            // update rental unit separately
            if ($request->accommodation_type) {
                $rentalUnit->accommodation_type = $request->accommodation_type;
                $rentalUnit->save();
            }

            if ($request->unit_floor) {
                $rentalUnit->unit_floor = $request->unit_floor;
                $rentalUnit->save();
            }

            if ($request->year_built) {
                $rentalUnit->year_built = $request->year_built;
                $rentalUnit->save();
            }


            if (!$accommodationDetails) {
                $accommodationDetails = new AccommodationDetail();
                $accommodationDetails->guest_limit = $request->guest_limit;
                $accommodationDetails->bathroom_count = 0;
                $accommodationDetails->allow_children = false;
                $accommodationDetails->offer_cots = false;
                $accommodationDetails->square_metres = $request->square_metres;
                $accommodationDetails->rental_unit_id = $rentalUnit->id;
                $accommodationDetails->venue_id = $venue->id;

            } else {
                $accommodationDetails->guest_limit = $request->guest_limit ?? $accommodationDetails->guest_limit;
                $accommodationDetails->square_metres = $request->square_metres ?? $accommodationDetails->square_metres;
                $accommodationDetails->updated_at = Carbon::now();
            }

            $accommodationDetails->save();
        }

        if ($input['type'] === 'accommodation_vr_link') {

            // update rental unit separately
            if ($request->vr_link) {
                $rentalUnit->vr_link = $request->vr_link;
                $rentalUnit->save();
            }
        }

        if ($input['type'] === 'breakfast_details') {
            // check if it has breakfast_detail
            $accommodationBreakfastDetails = $rentalUnit->breakfast_detail;

            if (!$accommodationBreakfastDetails) {
                $accommodationBreakfastDetails = new BreakfastDetail();
                $accommodationBreakfastDetails->offers_breakfast = $request->offers_breakfast;
                $accommodationBreakfastDetails->rental_unit_id = $rentalUnit->id;
                $accommodationBreakfastDetails->venue_id = $venue->id;

            } else {

                $accommodationBreakfastDetails->offers_breakfast = $request->offers_breakfast ?? $accommodationBreakfastDetails->offers_breakfast;
                $accommodationBreakfastDetails->updated_at = Carbon::now();
            }

            $accommodationBreakfastDetails->save();
        }

        if ($input['type'] === 'parking_details') {
            // check if it has parking_details
            $accommodationParkingDetails = $rentalUnit->parking_detail;

            if (!$accommodationParkingDetails) {
                $accommodationParkingDetails = new ParkingDetail();

                $accommodationParkingDetails->availability = $request->availability;
                $accommodationParkingDetails->reservation = $request->reservation;
                $accommodationParkingDetails->location = $request->location;
                $accommodationParkingDetails->type = $request->park_type;
                $accommodationParkingDetails->rental_unit_id = $rentalUnit->id;
                $accommodationParkingDetails->venue_id = $venue->id;

            } else {
                $accommodationParkingDetails->availability = $request->availability ?? $accommodationParkingDetails->availability;
                $accommodationParkingDetails->reservation = $request->reservation ?? $accommodationParkingDetails->reservation;
                $accommodationParkingDetails->location = $request->location ?? $accommodationParkingDetails->location;
                $accommodationParkingDetails->type = $request->park_type ?? $accommodationParkingDetails->type;
                $accommodationParkingDetails->updated_at = Carbon::now();
            }

            $accommodationParkingDetails->save();
        }

        if ($input['type'] === 'accommodation_rules') {
            // check if it has accommodation_rules
            $accommodationRules = $rentalUnit->accommodation_rules;

            if (!$accommodationRules) {
                $accommodationRules = new AccommodationRule();

                $accommodationRules->smoking_allowed = $request->smoking_allowed;
                $accommodationRules->pets_allowed = $request->pets_allowed;
                $accommodationRules->parties_allowed = $request->parties_allowed;
                $accommodationRules->rental_unit_id = $rentalUnit->id;
                $accommodationRules->venue_id = $venue->id;

            } else {
                $accommodationRules->smoking_allowed = $request->smoking_allowed ?? $accommodationRules->smoking_allowed;
                $accommodationRules->pets_allowed = $request->pets_allowed ?? $accommodationRules->pets_allowed;
                $accommodationRules->parties_allowed = $request->parties_allowed ?? $accommodationRules->parties_allowed;
                $accommodationRules->updated_at = Carbon::now();
            }

            $accommodationRules->save();
        }

        if ($input['type'] === 'accommodation_rules_extra') {
            // check if it has accommodation_rules
            $accommodationRules = $rentalUnit->accommodation_rules;

            if (!$accommodationRules) {
                $accommodationRules = new AccommodationRule();

                $accommodationRules->check_in_from = $request->check_in_from;
                $accommodationRules->check_in_until = $request->check_in_until;
                $accommodationRules->checkout_from = $request->checkout_from;
                $accommodationRules->checkout_until = $request->checkout_until;
                $accommodationRules->guest_requirements = $request->guest_phone || $request->guest_identification ? true : false;
                $accommodationRules->guest_phone = $request->guest_phone === '1';
                $accommodationRules->guest_identification = $request->guest_identification === '1';
                $accommodationRules->wifi_detail = $request->wifi_detail ?? ' ';
                $accommodationRules->check_out_method = $request->check_out_method ?? ' ';
                $accommodationRules->check_in_method = $request->check_in_method ?? ' ';
                $accommodationRules->key_pick_up = $request->key_pick_up ?? ' ';
                $accommodationRules->smoking_allowed = false;
                $accommodationRules->pets_allowed =  false;
                $accommodationRules->parties_allowed = false;
                $accommodationRules->rental_unit_id = $rentalUnit->id;
                $accommodationRules->venue_id = $venue->id;

            } else {

                $accommodationRules->guest_requirements = $request->guest_phone || $request->guest_identification ? true : false ?? $accommodationRules->guest_requirements;
                $accommodationRules->guest_phone = intval($request->guest_phone) === 1;
                $accommodationRules->guest_identification = intval($request->guest_identification) === 1;
                $accommodationRules->wifi_detail = $request->wifi_detail ?? $accommodationRules->wifi_detail;
                $accommodationRules->check_out_method = $request->check_out_method ?? $accommodationRules->check_out_method;
                $accommodationRules->check_in_method = $request->check_in_method ?? $accommodationRules->check_in_method;
                $accommodationRules->key_pick_up = $request->key_pick_up ?? $accommodationRules->key_pick_up;
                $accommodationRules->check_in_from = $request->check_in_from ?? $accommodationRules->check_in_from;
                $accommodationRules->check_in_until = $request->check_in_until ?? $accommodationRules->check_in_until;
                $accommodationRules->checkout_from = $request->checkout_from ?? $accommodationRules->checkout_from;
                $accommodationRules->checkout_until = $request->checkout_until ?? $accommodationRules->checkout_until;
                $accommodationRules->updated_at = Carbon::now();
            }

            $accommodationRules->save();
        }

        if ($input['type'] === 'accommodation_host_profile') {
            // check if it has AccommodationHostProfile
            $accommodationHostProfile = $rentalUnit->accommodation_host_profile;
            if (!$accommodationHostProfile) {
                $accommodationHostProfile = new AccommodationHostProfile();
                $accommodationHostProfile->host_name = $request->host_name;
                $accommodationHostProfile->about_host = $request->about_host;
                $accommodationHostProfile->rental_unit_id = $rentalUnit->id;
                $accommodationHostProfile->venue_id = $venue->id;
            } else {
                $accommodationHostProfile->host_name = $request->host_name ?? $accommodationHostProfile->host_name;
                $accommodationHostProfile->about_host = $request->about_host ?? $accommodationHostProfile->about_host;
                $accommodationHostProfile->updated_at = Carbon::now();
            }

            $accommodationHostProfile->save();
        }

        if ($input['type'] === 'languages') {
            $languages = $request->input('languages') ?? [];
            $existingLanguages = $rentalUnit->languages->pluck('id')->toArray();

            $newLanguages = array_diff($languages, $existingLanguages);
            $removeLanguages = array_diff($existingLanguages, $languages);

            if (count($newLanguages)) {
                foreach ($newLanguages as $languageId) {
                    // check if language id exists only then add
                    if (!Language::where('id', $languageId)->exists()) {
                        continue;
                    }
                    $rentalUnit->languages()->attach($languageId);
                }
            }

            if (count($removeLanguages)) {
                foreach ($removeLanguages as $languageId) {
                    $rentalUnit->languages()->detach($languageId);
                }
            }
        }

        return response()->json(['message' => 'Rental unit updated successfully']);
    }

    public function updateFacilities(Request $request, $id): \Illuminate\Http\JsonResponse
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

        $validator = Validator::make($request->all(), [
            'type' =>  'required|in:facilities',
            'facilities' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // facilities are sent by frontend in this format [1, 2, 3]
        // first we need to check if those exist and then add to rental_unit_facility table or remove if those are not sent

        $facilities = $request->facilities ?? [];
        $existingFacilities = $rentalUnit->facilities->pluck('id')->toArray();

        $newFacilities = array_diff($facilities, $existingFacilities);
        $removeFacilities = array_diff($existingFacilities, $facilities);

        if (count($newFacilities)) {
            foreach ($newFacilities as $facilityId) {
                // check if facility id exists only then add

                if (!Facility::where('id', $facilityId)->exists()) {
                    continue;
                }
                $rentalUnit->facilities()->attach($facilityId);
            }
        }

        if (count($removeFacilities)) {
            foreach ($removeFacilities as $facilityId) {
                $rentalUnit->facilities()->detach($facilityId);
            }
        }


        return response()->json(['message' => 'Rental unit updated successfully']);
    }

    public function rentalUploadPhoto(Request $request, $id): JsonResponse
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

        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|max:15360', // Maximum file size of 15MB
            'type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        };


        if ($request->hasFile('photo')) {
            $photoFile = $request->file('photo');

            $venue = Restaurant::with('venueType')->findOrFail($venue->id);

            $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();

            // Upload photo to AWS S3
            $path = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $venue->venueType->short_name . '/' . $request->type . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)), $photoFile, $filename);

            // Save photo record in the database
            $photo = new Photo();
            $photo->venue_id = $venue->id;
            $photo->image_path = $path;
            $photo->type = $request->type;
            $photo->save();

            $gallery = new Gallery();
            $photo_id = $photo->id;

            $gallery->photo_id = $photo_id;
            $gallery->rental_unit_id = $rentalUnit->id;
            $gallery->save();


            return response()->json(['message' => 'Photo uploaded successfully']);
        }

        return response()->json(['error' => 'No photo uploaded'], 400);
    }

    public function rentalRoomUploadPhoto(Request $request, $id, $roomId): JsonResponse
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

        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|max:15360', // Maximum file size of 15MB
            'type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        };


        if ($request->hasFile('photo')) {
            $photoFile = $request->file('photo');

            $venue = Restaurant::with('venueType')->findOrFail($venue->id);

            $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();

            // Upload photo to AWS S3
            $path = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $venue->venueType->short_name . '/' . $request->type . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)), $photoFile, $filename);

            // Save photo record in the database
            $photo = new Photo();
            $photo->venue_id = $venue->id;
            $photo->image_path = $path;
            $photo->type = $request->type;
            $photo->save();

            $gallery = new Gallery();
            $photo_id = $photo->id;

            $gallery->photo_id = $photo_id;
            $gallery->rental_unit_id = $rentalUnit->id;
            $gallery->room_id = $roomId;
            $gallery->save();


            return response()->json(['message' => 'Photo uploaded successfully']);
        }

        return response()->json(['error' => 'No photo uploaded'], 400);
    }

    public function addRoom(Request $request, $id): \Illuminate\Http\JsonResponse
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

        // Begin transaction to ensure atomicity
        DB::beginTransaction();

        try {
            // Check if a room with the given ID exists
            if ($request->has('id') && $request->id) {
                $room = Room::find($request->id);

                // If room exists and belongs to the rental unit and venue, update it
                if ($room && $room->rental_unit_id == $rentalUnit->id && $room->venue_id == $venue->id) {
                    $room->update([
                        'type' => $request->type,
                        'has_private_bathroom' => $request->input('has_private_bathroom', false),
                        'shared_space' => $request->input('shared_space', false),
                        'shared_space_with' => $request->input('shared_space_with', []),
                    ]);

                    // Clear existing bed associations
                    $room->beds()->detach();
                }
            }

            // Create the room if it doesn't exist
            if (!isset($room)) {
                $room = Room::create([
                    'rental_unit_id' => $rentalUnit->id,
                    'venue_id' => $venue->id,
                    'type' => $request->type,
                    'has_private_bathroom' => $request->input('has_private_bathroom', false),
                    'shared_space' => $request->input('shared_space', false),
                    'shared_space_with' => $request->input('shared_space_with', []),
                ]);
            }

            // Attach the beds to the room
            foreach ($request->beds as $bed) {
                $room->beds()->attach($bed['id'], ['quantity' => $bed['quantity']]);
            }

            DB::commit();

            return response()->json(['message' => 'Room updated/created successfully!'], 201);

        } catch (\Exception $e) {
            DB::rollback();
            \Sentry\captureException($e);
            return response()->json(['message' => 'Error updating/creating room!'], 500);
        }
    }


    public function updateAdditionalFeeAndCharge(Request $request, $id): \Illuminate\Http\JsonResponse
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

        $venueId = $venue->id;

        // Retrieve the JSON array of fee data from the request
        $feeData = $request->json()->all();



        foreach ($feeData as $feeItem) {

            $feeNameId = $feeItem['id'];
            $amount = $feeItem['amount'] ?? 0;

            // Find the corresponding fee record and update its amount
            $existingFee = AdditionalFeeAndCharge::where('venue_id', $venueId)
                ->where('id', $feeNameId)
                ->first();

            if ($existingFee) {
                $existingFee->amount = $amount;
                $existingFee->save();
            }
        }

        return response()->json(['message' => 'Additional fees and charges updated successfully!'], 200);
    }

    public function addAdditionalFeeCharge(Request $request, $id): JsonResponse
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

        // validator
        $validator = Validator::make($request->all(), [
            'custom_name' => 'required|string',
            'custom_description' => 'required|string',
            'custom_amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }


        // Create a custom fee name
        $customFeeName = AdditionalFeeAndChargesName::create([
            'name' => $request->custom_name,
            'description' => $request->custom_description,
        ]);

        $additionalFeeAndCharge = new AdditionalFeeAndCharge();
        $additionalFeeAndCharge->venue_id = $venue->id;
        $additionalFeeAndCharge->rental_unit_id = $rentalUnit->id;
        $additionalFeeAndCharge->fee_name_id = $customFeeName->id;
        $additionalFeeAndCharge->amount = $request->custom_amount;
        $additionalFeeAndCharge->save();

        return response()->json(['message' => 'Custom fee name created successfully!'], 201);
    }

    public function customHouseRule(Request $request, $id): JsonResponse
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

        // validator
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }


        // Create a custom fee name
        $customRule = RentalCustomRule::create([
            'name' => $request->name,
            'venue_id' => $venue->id,
            'rental_unit_id' => $rentalUnit->id,
        ]);

        $customRule->save();

        return response()->json(['message' => 'Custom house rule created successfully!'], 201);
    }

    public function deleteCustomHouseRule($id, $ruleId): \Illuminate\Http\JsonResponse
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

        $customRule = RentalCustomRule::where('id', $ruleId)->where('rental_unit_id', $rentalUnit->id)->first();

        if (!$customRule) {
            return response()->json(['message' => 'The requested custom rule does not exist'], 404);
        }

        if ($customRule->venue_id != $venue->id) {
            return response()->json(['error' => 'Unauthorized operation. The room does not belong to the specified venue.'], 403);
        }

        try {
            $customRule->delete();

            return response()->json(['message' => 'House rule deleted successfully!'], 200);

        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => 'Error deleting room!'], 500);
        }
    }

    public function deletePricePerNight($id, $pricingPlanId): \Illuminate\Http\JsonResponse
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

        $pricePerNight = PricePerNight::where('id', $pricingPlanId)->where('rental_unit_id', $rentalUnit->id)->first();

        if (!$pricePerNight) {
            return response()->json(['message' => 'The requested price per night does not exist'], 404);
        }

        if ($pricePerNight->venue_id != $venue->id) {
            return response()->json(['error' => 'Unauthorized operation. The room does not belong to the specified venue.'], 403);
        }

        try {
            $pricePerNight->delete();

            return response()->json(['message' => 'Price per night deleted successfully!'], 200);

        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => 'Error deleting price per night!'], 500);
        }
    }

    public function deleteRoom($id, $roomId): \Illuminate\Http\JsonResponse
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

        $room = Room::where('id', $roomId)->where('rental_unit_id', $rentalUnit->id)->first();

        if (!$room) {
            return response()->json(['message' => 'The requested room does not exist'], 404);
        }

        if ($room->venue_id != $venue->id) {
            return response()->json(['error' => 'Unauthorized operation. The room does not belong to the specified venue.'], 403);
        }

        try {
            $room->delete();

            return response()->json(['message' => 'Room deleted successfully!'], 200);

        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => 'Error deleting room!'], 500);
        }
    }

    public function rentalUnitWhiteLabel(): \Illuminate\Http\JsonResponse
    {
        $rentalUnitCode = request()->get('rental_unit_code');
        if (!$rentalUnitCode) {
            return response()->json(['error' => 'rental unit code is required'], 400);
        }

        $rentalUnit = RentalUnit::where('unit_code', $rentalUnitCode)->first();

        if (!$rentalUnit) {
            return response()->json(['error' => 'Rental Unit not found'], 404);
        }

        $responseRentalUnit = new StdClass();
        $headerSection = new StdClass();
        $headerSection->name = $rentalUnit->name;
        // add app key
        $headerSection->venue_app_key = $rentalUnit->venue->app_key;
        $headerSection->address = $rentalUnit->address;
        $headerSection->square_metters = $rentalUnit?->accommodation_detail?->square_metters ?? null;
        $headerSection->guest_limit = $rentalUnit?->accommodation_detail?->guest_limit ?? null;

        $rooms = RentalUnit::with('rooms.beds')->find($rentalUnit->id)->rooms;

        // Initialize an array to count occurrences of each room type
        $roomCount = [];

        $names = [
            'living_room' => 'Living Room',
            'bedroom' => 'Bedroom',
            'child_room' => 'Child Room',
            'other_spaces' => 'Other Spaces',
            'bathroom' => 'Bathroom',
            'balcony' => 'Balcony',
            'full_bathroom' => 'Full Bathroom',
            'kitchen' => 'Kitchen',
            'full_kitchen' => 'Full Kitchen',
            'gym' => 'Gym',
            'exterior' => 'Exterior',
            'patio' => 'Patio',
            'utility_room' => 'Utility Room',
        ];

        foreach ($rooms as $room) {
            $type = $room['type'];
            if (!isset($roomCount[$type])) {
                $roomCount[$type] = 0;
            }
            $roomCount[$type]++;
        }

        // Generate the desired format using the names mapping
        $readableRoomResult = [];
        foreach ($roomCount as $type => $count) {
            if (isset($names[$type])) {
                $readableRoomResult[] = "{$count} {$names[$type]}" . ($count > 1 ? 's' : ''); // add 's' for plural if count > 1
            }
        }

        // Initialize an array to count occurrences of each bed type
        $bedCount = [];

        foreach ($rooms as $room) {
            foreach ($room['beds'] as $bed) {
                $bedName = strtolower($bed['name']); // Convert to lowercase to ensure consistency
                $quantity = $bed['pivot']['quantity']; // Get the quantity from the pivot data

                if (!isset($bedCount[$bedName])) {
                    $bedCount[$bedName] = 0;
                }
                $bedCount[$bedName] += $quantity;
            }
        }

        // Generate the desired format
        $readableBedResult = [];
        foreach ($bedCount as $bedName => $count) {
            $readableBedResult[] = "{$count} {$bedName}" . ($count > 1 ? 's' : ''); // add 's' for plural if count > 1
        }

        $headerSection->rooms = $readableRoomResult;
        $headerSection->beds = $readableBedResult;
        $headerSection->bathrooms = $rentalUnit?->accommodation_detail?->bathroom_count ?? null;

        $gallery = Gallery::where('rental_unit_id', $rentalUnit->id)->with('photo')->get();

        $modifiedGallery = $gallery->map(function ($item) {
            return [
                'photo_id' => $item->photo_id,
                'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
            ];
        });

        $headerSection->gallery = $modifiedGallery;
        $headerSection->hosted_by = $rentalUnit?->accommodation_host_profile?->host_name ?? null;
        $headerSection->about = $rentalUnit?->about ?? null;
        $headerSection->about_space = $rentalUnit?->about_space ?? null;
        $headerSection->about_guest_access = $rentalUnit?->about_guest_access ?? null;
        $headerSection->price_per_night = $rentalUnit->pricing_and_calendar?->price_per_night ?? null;
        $headerSection->currency = $rentalUnit?->currency ?? null;

        $guestLimit = $rentalUnit?->accommodation_detail?->guest_limit;
        $formattedGuestsList = [];

        for ($i = 1; $i <= $guestLimit; $i++) {
            $formattedGuestsList[] = [
                'value' => $i,
                'title' => $i . ' ' . ($i === 1 ? 'guest' : 'guests')
            ];
        }
        $headerSection->guests = $formattedGuestsList;
        // get only non expired and active one
        $rentalUnitDiscount = Discount::where('rental_unit_id', $rentalUnit->id)->where('status', true)
            ->where('end_time', '>=', Carbon::now())
            ->select('type', 'value')
            ->first();
        $headerSection->discount = $rentalUnitDiscount ?? null;


        $whatsIncludedSection = new StdClass();
        $whatsIncludedSection->facilities =  Facility::getFacilitiesGroupedByCategoryByRentalUnitId($rentalUnit->id);
        $whatsIncludedSection->beds = $readableBedResult;
        $whatsIncludedSection->rooms = $readableRoomResult;
        $whatsIncludedSection->bathrooms = $rentalUnit?->accommodation_detail?->bathroom_count ?? null;

        $rules = [
            'availability' => 'required|in:free,paid,no',
            'reservation' => 'required|in:needed,not_needed',
            'location' => 'required|in:on_site,off_site',
            'type' => 'required|in:private,public',
        ];

        $parkingTranslations = [
            'availability' => [
                'free' => 'Free parking',
                'paid' => 'Paid parking',
                'no' => 'No parking'
            ],
            'reservation' => [
                'needed' => 'Reservation needed',
                'not_needed' => 'Reservation not needed '
            ],
            'location' => [
                'on_site' => 'On site',
                'off_site' => 'Off site'
            ],
            'type' => [
                'private' => 'Private',
                'public' => 'Public'
            ]
        ];

        $formattedParkingOptions = [];

        foreach ($rules as $key => $rule) {
            if (isset($rentalUnit->parking_detail->$key)) {
                $value = $rentalUnit->parking_detail->$key;
                if (isset($parkingTranslations[$key][$value])) {
                    $formattedParkingOptions[] = $parkingTranslations[$key][$value];
                }
            }
        }

        $whatsIncludedSection->parking = $formattedParkingOptions;


        $whereLocationSection = new StdClass();
        $whereLocationSection->latitude = $rentalUnit->latitude ?? null;
        $whereLocationSection->longitude = $rentalUnit->longitude ?? null;
        $whereLocationSection->vr_link = $rentalUnit->vr_link ?? null;

        $thingsToKnowSection = new StdClass();
        $thingsToKnowSectionAccommodationDetails = new StdClass();

        $languages = $rentalUnit->languages()->select('name')->get();
        $thingsToKnowSection->staff_languages = $languages;

        $accommodationRules = $rentalUnit->accommodation_rules;

        $accommodationRulesMessages = [];

        if (isset($accommodationRules['smoking_allowed'])) {
            $accommodationRulesMessages[] = $accommodationRules['smoking_allowed'] ? 'Smoking is allowed.' : 'Smoking is not allowed.';
        }

        if (isset($accommodationRules['pets_allowed'])) {
            $accommodationRulesMessages[] = $accommodationRules['pets_allowed'] ? 'Pets are allowed.' : 'Pets are not allowed.';
        }

        if (isset($accommodationRules['parties_allowed'])) {
            $accommodationRulesMessages[] = $accommodationRules['parties_allowed'] ? 'Parties are allowed.' : 'Parties are not allowed.';
        }

        if (isset($accommodationRules['check_in_from']) && isset($accommodationRules['check_in_until'])) {
            $accommodationRulesMessages[] = "Check-in is from {$accommodationRules['check_in_from']} to {$accommodationRules['check_in_until']}.";
        }

        if (isset($accommodationRules['checkout_from']) && isset($accommodationRules['checkout_until'])) {
            $accommodationRulesMessages[] = "Checkout is from {$accommodationRules['checkout_from']} to {$accommodationRules['checkout_until']}.";
        }


        $thingsToKnowSection->rules = $accommodationRulesMessages;

        $cancellationDays = $rentalUnit->pricing_and_calendar?->cancellation_days;

        if ($cancellationDays) {
            $cancellationPolicyMessage = "You can cancel your reservation up to {$cancellationDays} days before the check-in date without any penalties.";
        } else {
            $cancellationPolicyMessage = "No specific cancellation policy provided.";
        }
        $thingsToKnowSection->cancellation_policy = $cancellationPolicyMessage;

        $accDetailsMessages = [];

        $accDetailsMessages[] = $rentalUnit?->breakfast_detail?->offers_breakfast ? 'Breakfast included' : 'Breakfast not included';

        $accDetailsMessages[] = $rentalUnit?->accommodation_payment_capability?->can_charge_credit_cards ? 'Accepts card payments' : 'Does not accept card payments';

        $cardTypes = $rentalUnit?->card_preferences->pluck('card_type')->toArray();
        if(!empty($cardTypes)) {
            $accDetailsMessages[] = 'Cards allowed: ' . implode(', ', $cardTypes);
        } else {
            $accDetailsMessages[] = 'No specific card preferences';
        }

        // $accDetailsMessages[] = $rentalUnit?->accommodation_detauks?->allow_children ? 'Children allowed' : 'Children not allowed';

        $accDetailsMessages[] = $rentalUnit?->accommodation_detauks?->offer_cots ? 'Offers cots' : 'Does not offer cots';


        $thingsToKnowSection->details = $accDetailsMessages;

        $responseRentalUnit->header_section = $headerSection;
        $responseRentalUnit->what_is_included = $whatsIncludedSection;
        $responseRentalUnit->where_located = $whereLocationSection;
        $responseRentalUnit->things_to_know = $thingsToKnowSection;
        $responseRentalUnit->booking_dates = Booking::getBookingDatesForRentalUnit($rentalUnit->id);
        $responseRentalUnit->price_per_nights = $rentalUnit->price_per_nights;

        $venue = Restaurant::where('id', $rentalUnit->venue_id)->first();

        // check for value of allow_reservation_from
        $retrievedWhiteLabelOverview = new StdClass();
        $retrievedWhiteLabelOverview->venue_name = $venue->name;
        $retrievedWhiteLabelOverview->address = null;
        $retrievedWhiteLabelOverview->allow_reservation_from = null;
        $retrievedWhiteLabelOverview->cover = $venue->cover ? Storage::disk('s3')->temporaryUrl($venue->cover, '+5 minutes') : null;
        $retrievedWhiteLabelOverview->logo = $venue->logo ? Storage::disk('s3')->temporaryUrl($venue->logo, '+5 minutes') : null;



        $customizationBrand = VenueWhitelabelCustomization::where('venue_id', $venue->id)->first();
        $customizationBrandInformation = new StdClass;

        if($customizationBrand) {
            $customizationBrandInformation->show_newsletter = $customizationBrand->show_newsletter;
            $customizationBrandInformation->support_phone = $customizationBrand->support_phone;
            $customizationBrandInformation->pinterest_link = $customizationBrand->pinterest_link;
            $customizationBrandInformation->tiktok_link = $customizationBrand->tiktok_link;
            $customizationBrandInformation->social_media_label_text = $customizationBrand->social_media_label_text;
            $customizationBrandInformation->subscribe_label_text = $customizationBrand->subscribe_label_text;
            $customizationBrandInformation->call_us_text = $customizationBrand->call_us_text;
            $customizationBrandInformation->linkedin_link = $customizationBrand->linkedin_link;
            $customizationBrandInformation->header_links = $customizationBrand->header_links;
            $customizationBrandInformation->show_logo_header = $customizationBrand->show_logo_header;
            $customizationBrandInformation->show_logo_footer = $customizationBrand->show_logo_footer;
            $customizationBrandInformation->booking_sites = json_decode($customizationBrand?->booking_sites) ?? [];
            $customizationBrandInformation->instagram_link = $customizationBrand->instagram_link;
            $customizationBrandInformation->twitter_link = $customizationBrand->twitter_link;
            $customizationBrandInformation->facebook_link = $customizationBrand->facebook_link;
            $customizationBrandInformation->contact_page_main_title_string = $customizationBrand->contact_page_main_title_string;
            $customizationBrandInformation->contact_page_toplabel_string = $customizationBrand->contact_page_toplabel_string;
            $customizationBrandInformation->contact_page_address_string = $customizationBrand->contact_page_address_string;
            $customizationBrandInformation->contact_page_phone_string = $customizationBrand->contact_page_phone_string;
            $customizationBrandInformation->contact_page_email_string = $customizationBrand->contact_page_email_string;
            $customizationBrandInformation->contact_page_open_hours_string = $customizationBrand->contact_page_open_hours_string;
            $customizationBrandInformation->contact_page_form_subtitle_string = $customizationBrand->contact_page_form_subtitle_string;
            $customizationBrandInformation->contact_page_form_submit_btn_txt = $customizationBrand->contact_page_form_submit_btn_txt;
            $customizationBrandInformation->contact_page_fullname_label_string = $customizationBrand->contact_page_fullname_label_string;
            $customizationBrandInformation->contact_page_phone_number_label_string = $customizationBrand->contact_page_phone_number_label_string;
            $customizationBrandInformation->contact_page_phone_number_show = $customizationBrand->contact_page_phone_number_show;
            $customizationBrandInformation->contact_page_email_label_string = $customizationBrand->contact_page_email_label_string;
            $customizationBrandInformation->contact_page_subject_label_string = $customizationBrand->contact_page_subject_label_string;
            $customizationBrandInformation->contact_page_subject_show = $customizationBrand->contact_page_subject_show;
            $customizationBrandInformation->contact_page_content_label_string = $customizationBrand->contact_page_content_label_string;
            $customizationBrandInformation->contact_page_content_show = $customizationBrand->contact_page_content_show;
            $customizationBrandInformation->contact_page_enable = $customizationBrand->contact_page_enable;
            $customizationBrandInformation->contact_page_opening_hours_enable = $customizationBrand->contact_page_opening_hours_enable;
            $customizationBrandInformation->contact_page_address_value = $customizationBrand->contact_page_address_value;
            $customizationBrandInformation->contact_page_email_value = $customizationBrand->contact_page_email_value;
            $customizationBrandInformation->contact_page_phone_value = $customizationBrand->contact_page_phone_value;
            $customizationBrandInformation->vt_link = $customizationBrand->vt_link;

            $venue = Restaurant::find($venue->id);

            $openingHour =  OpeningHour::where('restaurant_id', $venue->id)->where('used_in_white_label', true)->first();
            $customizationBrandInformation->contact_page_opening_hours_value = $openingHour?->formattedOpeningHours();
//             $customizationBrandInformation->contact_page_opening_hours_value = $customizationBrand->contact_page_opening_hours_value;
        }

        $venueWhiteLabelInformation = new StdClass;
        $retrievedWhiteLabelOverview->header_links = json_decode($customizationBrand?->header_links) ?? [];

        // get brand profile
        $brandProfile =  IndustryBrandCustomizationElement::with(['venueBrandProfileCustomizations' => function ($query) use ($venue) {
            $query->where('venue_id', $venue->id);
        }])
            ->where('industry_id', $venue->venue_industry)
            ->get();

        // if industry is accommodation return only specific items from brand profile
        // only those with element_name =
        // 1. AllButtons
        // 2. Footer
        // 3. ContactFormLeftBlock
        // 4. ContactFormBtn
        // 5. ContactFormTopLabel
        if ($venue->venueType->definition === 'accommodation') {
            $brandProfile = $brandProfile->filter(function ($item) {
                // Filter directly on collection
                // Return only items that have element_name in the array
                // directly on the collection not in venueBrandProfileCustomizations
                return in_array($item->element_name, [
                    'AllButtons',
                    'Footer',
                    'ContactFormLeftBlock',
                    'ContactFormBtn',
                    'ContactFormTopLabel',
                ]);
            })->values(); // Use values() to reset the keys and get a re-indexed array
        }


        $venueWhiteLabelInformation->additional_information = null;
        $venueWhiteLabelInformation->overview = $retrievedWhiteLabelOverview;
        $venueWhiteLabelInformation->photos = $modifiedGallery;
        $venueWhiteLabelInformation->menu = [];
        $venueWhiteLabelInformation->brand_profile = $brandProfile;
        $venueWhiteLabelInformation->other_customations = $customizationBrandInformation;
        $venueWhiteLabelInformation->full_whitelabel = $venue->full_whitelabel == 1;


        return response()->json([
            'rental_unit' => $responseRentalUnit,
            'venue' => $venueWhiteLabelInformation,
            'message' => 'Rental Unit retrieved successfully',
        ]);
    }

}


function typeToReadableName($type): string
{

    $names = [
        'living_room' => 'Living Room',
        'bedroom' => 'Bedroom',
        'child_room' => 'Child Room',
        'other_spaces' => 'Other Spaces',
        'bathroom' => 'Bathroom',
        'balcony' => 'Balcony',
        'full_bathroom' => 'Full Bathroom',
        'kitchen' => 'Kitchen',
        'full_kitchen' => 'Full Kitchen',
        'gym' => 'Gym',
        'exterior' => 'Exterior',
        'patio' => 'Patio',
        'utility_room' => 'Utility Room',
    ];

    return $names[$type] ?? 'Unknown';  // Return 'Unknown' if the type is not found in the array
}

function getDescriptionForName($name): string
{
    // Define descriptions for default names (customize as needed)
    $descriptions = [
        'Linen fees' => 'For linens and towels',
        'Management fees' => 'For general admin and business expenses',
        'Community fees' => 'For building, community, and related fees',
    ];

    return $descriptions[$name] ?? '';
}

function getAmountForName($name, $linenFeeValue, $managementFeeValue, $communityFeeValue) {
    // Define default values for the default names (customize as needed)
    $defaultValues = [
        'Linen fees' => $linenFeeValue,
        'Management fees' => $managementFeeValue,
        'Community fees' => $communityFeeValue,
    ];

    return $defaultValues[$name] ?? 0;
}
