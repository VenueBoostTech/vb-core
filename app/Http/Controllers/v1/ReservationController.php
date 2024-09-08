<?php

namespace App\Http\Controllers\v1;
use App\Enums\FeatureNaming;
use App\Http\Controllers\Controller;
use App\Mail\InPlaceNotificationEmail;
use App\Mail\PostReservationNotificationEmail;
use App\Mail\PreArrivalReminderEmail;
use App\Mail\NewReservationEmail;
use App\Models\AutomaticReply;
use App\Models\Feature;
use App\Models\FeatureUsageCredit;
use App\Models\FeatureUsageCreditHistory;
use App\Models\Guest;
use App\Models\HotelEventsHall;
use App\Models\HotelGym;
use App\Models\HotelRestaurant;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyProgramGuest;
use App\Models\OrderProduct;
use App\Models\PaymentMethod;
use App\Models\PlanFeature;
use App\Models\Promotion;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Subscription;
use App\Models\Table;
use App\Models\Order;
use App\Models\TableReservations;
use App\Models\Wallet;
use App\Services\ApiUsageLogger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use JetBrains\PhpStorm\NoReturn;
use stdClass;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;
use Exception;
use function env;
use function response;

/**
 * @OA\Info(
 *   title="Reservations API",
 *   version="1.0",
 *   description="This API allows use Reservations Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Reservations",
 *   description="Operations related to Reservations"
 * )
 */
class ReservationController extends Controller
{
    protected ApiUsageLogger $apiUsageLogger;

    public function __construct(ApiUsageLogger $apiUsageLogger)
    {
        $this->apiUsageLogger = $apiUsageLogger;
    }

    /**
     * @OA\Post(
     *     path="/reservations",
     *     summary="Create a new reservation",
     *     tags={"Reservations"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="table_id",
     *                 type="integer"
     *             ),
     *             @OA\Property(
     *                 property="start_time",
     *                 type="string",
     *                 format="datetime"
     *             ),
     *             @OA\Property(
     *                 property="end_time",
     *                 type="string",
     *                 format="datetime"
     *             ),
     *             @OA\Property(
     *                 property="seating_arrangement",
     *                 type="string",
     *                 example="round"
     *             ),
     *             @OA\Property(
     *                 property="guest_count",
     *                 type="integer"
     *             ),
     *             @OA\Property(
     *                 property="notes",
     *                 type="string"
     *             ),
     *           @OA\Property(
     *                 property="hasAdditionalGuests",
     *                 type="boolean"
     *             ),
     *             @OA\Property(
     *                 property="provideGuestInfo",
     *                 type="boolean"
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Reservation created successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="table_id",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="start_time",
     *                     type="string",
     *                     format="datetime"
     *                 ),
     *                 @OA\Property(
     *                     property="end_time",
     *                     type="string",
     *                     format="datetime"
     *                 ),
     *                 @OA\Property(
     *                     property="seating_arrangement",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="guest_count",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="notes",
     *                     type="string"
     *                 ),
     *              @OA\Property(
     *                 property="guests",
     *                 type="array",
     *                 @OA\Items(
     *                    type="object",
     *                    @OA\Property(property="id", type="integer"),
     *                    @OA\Property(property="name", type="string"),
     *                    @OA\Property(property="email", type="string"),
     *                    @OA\Property(property="address", type="string"),
     *                    @OA\Property(property="phone", type="string"),
     *                     @OA\Property(
     *                         property="is_main",
     *                         type="boolean",
     *                         description="Indicates whether this guest is the primary guest for the reservation."
     *                     )
     *                 )
     *              )
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
     * @throws ConfigurationException|TwilioException
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'table_id' => 'nullable|exists:tables,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after_or_equal:start_time',
            'seating_arrangement' => 'string',
            'guest_count' => 'required|integer',
            'notes' => 'nullable|string',
            'provideGuestInfo' => 'required|boolean',
            'hasAdditionalGuests' => 'required|boolean',
            'source' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

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

        // Check if the table is available during the requested time
        $table = Table::find($request->input('table_id'));

        if ($table) {
            if(!$venue->tables->contains($table)){
                return response()->json(['error' => 'Table does not belong to the restaurant'], 400);
            }

            if (!$table->is_available($request->input('start_time'), $request->input('end_time'))) {
                return response()->json(['error' => 'Table is not available during the requested time'], 400);
            }
        } else {
            $table = null;
        }


        // Create the reservation
        $reservation = Reservation::create([
            'table_id' => $request->input('table_id'),
            'start_time' => $request->input('start_time'),
            'end_time' => $request->input('end_time'),
            'seating_arrangement' => $request->input('seating_arrangement'),
            'guest_count' => $request->input('guest_count'),
            'notes' => $request->input('notes'),
            'source' => $request->input('source') ?? 'call',
            'restaurant_id' => $venue->id,
        ]);


        // Restaurant provide guest info
        if ($request->input('provideGuestInfo')) {

            // Create or select guests

            if ($request->input('guest_count') > 0 && !$request->has('guests')) {
                return response()->json(['error' => 'Guest list must be greater than 0'], 400);
            }

            if ($request->input('hasAdditionalGuests') && $request->input('guest_count') < 2) {
                return response()->json(['error' => 'Additional Guests list must be greater than 0'], 400);
            }

            $guests = [];

            for ($i = 0; $i < $request->input('guest_count'); $i++) {
                $guest_data = $request->input('guests')[$i];

                // check if guest_data has id or not
                if(isset($guest_data['id'])){
                    //if guest_data has id then update guest
                    $guest = Guest::find($guest_data['id']);
                    if ($guest) {
                        $guest->update($guest_data);
                    } else {
                        if (!Guest::isUniqueEmail($guest_data['email']) && isset($guest_data['is_main']) && $guest_data['is_main'] && !isset($guest_data['id'])) {
                            return response()->json(['error' => 'Email already exists'], 400);
                        }

                        $guestDataCheckEmail = $guest_data['email'] === '' || $guest_data['email'] === null ? generateRandomEmail() . '@venueboost.io' : $guest_data['email'];
                        $guest_data['email'] = $guestDataCheckEmail;
                        $guest_data['restaurant_id'] = $venue->id;
                        Guest::create($guest_data);
                    }
                } else {

                    $guestDataCheckEmail = $guest_data['email'] === '' || $guest_data['email'] === null ? generateRandomEmail() . '@venueboost.io' : $guest_data['email'];
                    $guest_data['email'] = $guestDataCheckEmail;
                    $guest_data['restaurant_id'] = $venue->id;
                    // if guest_data doesn't have id then create new guest
                    $guest = Guest::create($guest_data);
                }
                $guests[] = $guest;
            }

            // Check if the request contains guests information
            if ($request->has('guests')) {

                // Create or update guests
                $guests = [];
                foreach ($request->input('guests') as $guestData) {
                    if(isset($guestData['id'])) {
                        $guest = Guest::find($guestData['id']);
                        $guestData['restaurant_id'] = $venue->id;
                        $guest->fill($guestData);
                        $guest->save();
                        array_push($guests, $guest);
                    }
                    else {
                        $guestDataCheckEmail = $guestData['email'] === '' || $guestData['email'] === null ? generateRandomEmail() . '@venueboost.io' : $guestData['email'];
                        $guest = Guest::firstOrNew(['email' => $guestDataCheckEmail]);
                        $guestData['email'] = $guestDataCheckEmail;
                        $guestData['restaurant_id'] = $venue->id;
                        $guest->fill($guestData);
                        $guest->save();
                        array_push($guests, $guest);
                    }
                }

                $finalGuests = [];
                foreach ($guests as $guest) {
                    if ($guest->email === '' || $guest->email === null) {
                        $guest->email = generateRandomEmail() . '@venueboost.io';
                    }

                    array_push($finalGuests, $guest);
                }

                // Assign guests to the reservation
                $reservation->guests()->saveMany($finalGuests);
            }
        }


        if ($request->input('provideGuestInfo')) {
            // Twilio account information
            $account_sid = env('TWILIO_ACCOUNT_SID');
            $auth_token = env('TWILIO_AUTH_TOKEN');
            $twilio_number = env('TWILIO_NUMBER');
            $client = new Client($account_sid, $auth_token);

            $reservationRestaurant = $reservation->restaurant->name;

            // TODO: after v1 testing, we need to validate also phone number
            foreach ($guests as $guest) {

                if ($guest->is_main) {
                    try {
                        // Send SMS message
                        $client->messages->create(
                            $guest->phone,
                            array(
                                'from' => $twilio_number,
                                'body' => "Your reservation at {$reservationRestaurant} on {$reservation->start_time} has been placed. We look forward to seeing you!"
                            )
                        );
                    }
                    catch (Exception $e) {
                        continue;
                    }

                }
            }
        }

        if ($table) {
            TableReservations::create([
                'table_id' => $request->input('table_id'),
                'reservation_id' => $reservation->id,
                'start_time' => $request->input('start_time'),
                'end_time' => $request->input('end_time'),
            ]);
        }

        // check if venue has program loyalty, and if guests is enrolled, only then apply this logic
        // Calculate points earned based on reservation amount and percentage (e.g., 5%)
//        $percentage = 5;
//        $pointsEarned = $guest->calculatePointsEarned($reservationAmount, $percentage);
//
//        // Update guest's wallet balance with the earned points
//        if (!$guest->wallet) {
//            $guest->wallet()->create(['balance' => $pointsEarned]);
//        } else {
//            $guest->wallet->increment('balance', $pointsEarned);
//        }
//
//        // Add a record to the earnPointsHistory table with guest_id, reservation_id, and points_earned
//        $guest->earnPointsHistory()->create([
//            'reservation_id' => $reservationId,
//            'points_earned' => $pointsEarned,
//        ]);

        // log api usage inside a try so that it doesn't break the api call
        // also calculate deduction if venue is on discover
        try {


            $featureId = Feature::where('name', FeatureNaming::reservations)->where('feature_category', $venue->venueType->definition)->first()->id;
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
                // Check Count of the reservation used on FeatureUsageCreditHistory with feature_id
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
                    $featureUsageCreditHistory->used_at_feature = FeatureNaming::reservations;
                    $featureUsageCreditHistory->feature_usage_credit_id = $featureUsageCredit->id;
                    $featureUsageCreditHistory->transaction_type = 'decrease';
                    $featureUsageCreditHistory->amount = 1;
                    $featureUsageCreditHistory->save();
                }
            }


            $this->apiUsageLogger->log($featureId, $venue->id, 'Add Manual Reservation - POST', $subFeatureId);
        } catch (\Exception $e) {
            // do nothing
        }

        return response()->json(['message' => 'Reservation created successfully', 'data' => $reservation], 201);

    }

    /**
     * @OA\Get(
     *     path="/reservations",
     *     summary="List all reservations",
     *     tags={"Reservations"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(
     *                         property="id",
     *                         type="integer"
     *                     ),
     *                     @OA\Property(
     *                         property="table_id",
     *                         type="integer"
     *                     ),
     *                     @OA\Property(
     *                         property="table",
     *                         type="object",
     *                         @OA\Property(
     *                             property="id",
     *                             type="integer"
     *                         ),
     *                         @OA\Property(
     *                             property="name",
     *                             type="string"
     *                         ),
     *                         @OA\Property(
     *                             property="location",
     *                             type="string"
     *                         ),
     *                         @OA\Property(
     *                             property="seats",
     *                             type="integer"
     *                         ),
     *                         @OA\Property(
     *                             property="shape",
     *                             type="string"
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="start_time",
     *                         type="string",
     *                         format="datetime"
     *                     ),
     *                     @OA\Property(
     *                         property="end_time",
     *                         type="string",
     *                         format="datetime"
     *                     ),
     *                     @OA\Property(
     *                         property="seating_arrangement",
     *                         type="string"
     *                     ),
     *                     @OA\Property(
     *                         property="guest_count",
     *                         type="integer"
     *                     ),
     *                     @OA\Property(
     *                         property="notes",
     *                         type="string"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
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
        $reservations = Reservation::where('restaurant_id', $venue->id)->with('table')->get();

        return response()->json(['data' => $reservations], 200);
    }

    /**
     * @OA\Get(
     *     path="/reservations/{id}",
     *     summary="Get reservation details",
     *     tags={"Reservations"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the reservation to retrieve",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Reservation not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function show($id)
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

        try {
            $reservation = Reservation::with(['table', 'guests', 'order', 'order.orderProducts', 'order.paymentMethod', 'order.promotion', 'order.orderProducts.product'])
                ->where('restaurant_id', $venue->id)
                ->findOrFail($id);

            if ($reservation?->order?->orderProducts) {
                foreach ($reservation?->order?->orderProducts as $orderProduct) {
                    // Assuming $orderProduct->product is the related product model
                    // You can adjust this based on your actual relationship
                    if ($orderProduct->product) {
                        // Get the image path for each product of order products
                        $orderProduct->product->image_path = $orderProduct->product->image_path ?
                            Storage::disk('s3')->temporaryUrl($orderProduct->product->image_path, '+5 minutes') : null;
                    }
                }
            }
            $reservation->orders = Order::with(['customer', 'paymentMethod', 'promotion'])->where('reservation_id', $reservation->id)->orderBy('created_at', 'DESC')->get();
            return response()->json(['data' => $reservation], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Reservation not found'], 404);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['error' => 'Error fetching reservation details'], 500);
        }
    }

    /**
     * @throws ConfigurationException
     * @throws TwilioException
     */
    #[NoReturn] public static function sendReminderSMS($reservation){

        // Twilio account information
        $account_sid = env('TWILIO_ACCOUNT_SID');
        $auth_token = env('TWILIO_AUTH_TOKEN');
        $twilio_number = env('TWILIO_NUMBER');

        $client = new Client($account_sid, $auth_token);

        foreach ($reservation->guests as $guest) {
            $reservationGuestName = $guest ? $guest->name : 'Guest';
            // Send SMS message
            $client->messages->create(
                $guest->phone,
                array(
                    'from' => $twilio_number,
                    'body' => "Hello {$reservationGuestName}, this is a reminder for your reservation at {$reservation->table->name} at {$reservation->start_time}."
                )
            );
        }

    }

    /**
     * @throws ConfigurationException
     * @throws TwilioException
     */
    #[NoReturn] public static function sendPostReservationNotification($reservation){

        // Twilio account information
        $account_sid = env('TWILIO_ACCOUNT_SID');
        $auth_token = env('TWILIO_AUTH_TOKEN');
        $twilio_number = env('TWILIO_NUMBER');

        $client = new Client($account_sid, $auth_token);

        foreach ($reservation->guests as $guest) {
            $reservationGuestName = $guest ? $guest->name : 'Guest';

            // Get the restaurant name from the reservation
            $restaurantName = $reservation->restaurant ? $reservation->restaurant->name : 'Restaurant';

            // Check if the restaurant has a post-reservation automatic reply
            $preArrivalTemplate = AutomaticReply::where('venue_id', $reservation->restaurant_id)
                ->where('reply_type', 'post-reservation')
                ->first();

            if ($preArrivalTemplate) {
                $content = $preArrivalTemplate->template->description;

                // Replace template tags with actual values
                $content = str_replace('[guest_name]', $reservationGuestName, $content);
                $content = str_replace('[restaurant_name]', $restaurantName, $content);

                if ($preArrivalTemplate->template->type == 'Email') {
                    Mail::to($guest->email)->send(new PostReservationNotificationEmail($restaurantName, $content));
                } else {
                    if($guest->phone) {
                        // Send SMS message
                        $client->messages->create(
                            $guest->phone,
                            array(
                                'from' => $twilio_number,
                                'body' => $content
                            )
                        );
                    }
                }


            }
        }

    }

    /**
     * @throws ConfigurationException
     * @throws TwilioException
     */
    #[NoReturn] public static function sendInPlaceNotification($reservation){

        // Twilio account information
        $account_sid = env('TWILIO_ACCOUNT_SID');
        $auth_token = env('TWILIO_AUTH_TOKEN');
        $twilio_number = env('TWILIO_NUMBER');

        $client = new Client($account_sid, $auth_token);

        foreach ($reservation->guests as $guest) {
            $reservationGuestName = $guest ? $guest->name : 'Guest';

            // Get the restaurant name from the reservation
            $restaurantName = $reservation->restaurant ? $reservation->restaurant->name : 'Restaurant';

            // Check if the restaurant has a in-place automatic reply
            $preArrivalTemplate = AutomaticReply::where('venue_id', $reservation->restaurant_id)
                ->where('reply_type', 'in-place')
                ->first();

            if ($preArrivalTemplate) {
                $content = $preArrivalTemplate->template->description;

                // Replace template tags with actual values
                $content = str_replace('[guest_name]', $reservationGuestName, $content);
                $content = str_replace('[restaurant_name]', $restaurantName, $content);

                if ($preArrivalTemplate->template->type == 'Email') {
                    Mail::to($guest->email)->send(new InPlaceNotificationEmail($restaurantName, $content));
                } else {
                    if($guest->phone) {
                        // Send SMS message
                        $client->messages->create(
                            $guest->phone,
                            array(
                                'from' => $twilio_number,
                                'body' => $content
                            )
                        );
                    }
                }


            }
        }

    }

    /**
     * @throws ConfigurationException
     * @throws TwilioException
     */
    #[NoReturn] public static function sendPreArrivalReminder($reservation){

        // Twilio account information
        $account_sid = env('TWILIO_ACCOUNT_SID');
        $auth_token = env('TWILIO_AUTH_TOKEN');
        $twilio_number = env('TWILIO_NUMBER');

        $client = new Client($account_sid, $auth_token);

        foreach ($reservation->guests as $guest) {
            $reservationGuestName = $guest ? $guest->name : 'Guest';

            // Get the restaurant name from the reservation
            $restaurantName = $reservation->restaurant ? $reservation->restaurant->name : 'Restaurant';

            // Check if the restaurant has a pre-arrival automatic reply
            $preArrivalTemplate = AutomaticReply::where('venue_id', $reservation->restaurant_id)
                ->where('reply_type', 'pre-arrival')
                ->first();

            if ($preArrivalTemplate) {
                $content = $preArrivalTemplate->template->description;


                // todo fix this with venue name for non accommodation and accommodation_name
                // Replace template tags with actual values
                $content = str_replace('[guest_name]', $reservationGuestName, $content);
                $content = str_replace('[restaurant_name]', $restaurantName, $content);

                if ($preArrivalTemplate->template->type == 'Email') {
                    Mail::to($guest->email)->send(new PreArrivalReminderEmail($restaurantName, $content));
                } else {
                    if($guest->phone) {
                        // Send SMS message
                        $client->messages->create(
                            $guest->phone,
                            array(
                                'from' => $twilio_number,
                                'body' => $content
                            )
                        );
                    }
                }


            }
        }

    }

    /**
     * @throws ConfigurationException
     * @throws TwilioException
     */
    #[NoReturn] public static function sendReminderConfirmCancelSMS($reservation){

        // Twilio account information
        $account_sid = env('TWILIO_ACCOUNT_SID');
        $auth_token = env('TWILIO_AUTH_TOKEN');
        $twilio_number = env('TWILIO_NUMBER');

        $client = new Client($account_sid, $auth_token);

        $reservationRestaurant = $reservation->restaurant ? $reservation->restaurant->name : 'Restaurant';

        foreach ($reservation->guests as $guest) {
            $reservationGuestName = $guest ? $guest->name : 'Guest';
            // Send SMS message
            $client->messages->create(
                $guest->phone,
                array(
                    'from' => $twilio_number,
                    'body' => "Hello $reservationGuestName Don't forget your reservation at {$reservationRestaurant} on {$reservation->start_time}. Reply CONFIRM to confirm, or CANCEL to cancel."
                )
            );
        }

    }

    /**
     * @OA\Patch(
     *     path="/reservations/{id}/confirm/",
     *     tags={"Reservations"},
     *     summary="Confirm or cancel a reservation",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the reservation to be confirmed or canceled",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"confirmed"},
     *             @OA\Property(
     *                 property="confirmed",
     *                 description="The status of the reservation (confirmed or canceled)",
     *                 type="integer",
     *                 enum={1, 2}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The reservation has been successfully confirmed or canceled"
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="The reservation could not be found"
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="The confirmed value provided is not valid"
     *     )
     * )
     */
    public function confirm(Request $request, $id): \Illuminate\Http\JsonResponse
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

        $reservation = Reservation::where('restaurant_id', $venue->id)->where('id', $id)->first();
        if (!$reservation) {
            return response()->json(['error' => 'The reservation could not be found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'confirmed' => 'required|in:1,2'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $messageStatus = $request->input('confirmed') === 1 ? 'confirmed' : 'canceled';

        $reservation->confirmed = strval($request->input('confirmed'));
        $reservation->save();

        return response()->json(['message' => "The reservation has been successfully {$messageStatus}"], 200);
    }


    /**
     * @OA\Patch(
     *     path="/reservations/{id}/choose-table",
     *     tags={"Reservations"},
     *     summary="Choose table for reservation",
     *     @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="ID of the reservation that we want to choose a table for",
     *     required=true,
     *     @OA\Schema(
     *     type="integer"
     *    )
     * ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="table_id",
     *                 type="integer",
     *                 description="ID of the table"
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Reservation or table not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function chooseTable(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'table_id' => 'required|exists:tables,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $reservation = Reservation::find($id);
        if (!$reservation) {
            return response()->json(['error' => 'Reservation not found'], 404);
        }

        $table = Table::find($request->input('table_id'));
        $start_time = $reservation->start_time;
        $end_time = $reservation->end_time;

        if (!$table->is_available($start_time, $end_time)) {
            $reservation->table_id = $table->id;
            $reservation->save();
            return response()->json(['message' => 'Table successfully chosen']);
        } else {
            return response()->json(['error' => 'Table not available']);
        }
    }

    public function assignOrder(Request $request, $id): \Illuminate\Http\JsonResponse
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

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'products' => 'array|required',
            'products.*.id' => 'required|exists:products,id',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.quantity' => 'required|integer|min:0',
            'payment_method' => 'required|string',
            'enroll_guest' => 'required|boolean',
            'promotion_id' => 'nullable|exists:promotions,id',
            'discount_value' => 'numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Find the reservation
        $reservation = Reservation::findOrFail($id);

        $paymentMethod = PaymentMethod::where('name', $request->input('payment_method'))->first();
        if (!$paymentMethod) {
            return response()->json(['error' => 'Payment method not found'], 404);
        }

        // Calculate total price of products
        $productsTotal = collect($request->input('products'))->sum(function ($product) {
            return $product['price'] * $product['quantity'];
        });

        // Validate that the products total matches the provided subtotal
        if (floatval($request->input('subtotal')) !== floatval($productsTotal)) {
            return response()->json(['error' => 'Products total does not match the provided subtotal'], 400);
        }

        // Check if an order exists, if not create a new one
        if (!$reservation->order) {
            $order = new Order();
            $order->reservation_id = $reservation->id;
            $order->restaurant_id = $venue->id;
            $order->payment_status = 'paid'; // You may need to set this according to your logic
        } else {
            $order = $reservation->order;
        }

        // Update order with payment method, subtotal, and total
        $order->payment_method_id = PaymentMethod::where('name', $request->input('payment_method'))->value('id');
        $order->subtotal = $request->input('subtotal');
        $order->total_amount = $request->input('total');

        // Check if there's a promotion
        if ($request->has('promotion_id') && $request->has('discount_value')) {
            $promotion = Promotion::with('discounts')->findOrFail($request->input('promotion_id'));


            // Update order with promotion
            $order->promotion_id = $promotion->id;

            // Calculate discount
            $discountType = $promotion->discounts[0]->type ?? '-';


            if ($discountType === 'fixed') {
                $discountValue = $promotion->discounts[0]->value;

                if (floatval($request->input('discount_value')) !== floatval($discountValue)) {
                    return response()->json(['error' => 'Discount values dont match'], 400);
                }
                $order->total_amount = max(0, $request->input('subtotal') - $discountValue);

            } elseif ($discountType === 'percentage') {
                $discountValue = ($promotion->discounts[0]->value / 100) * $request->input('subtotal');
                $discountValueCents =  intval($discountValue * 100);
                $inputValueCents = intval(floatval($request->input('discount_value')) * 100);

                if ($discountValueCents !== $inputValueCents) {
                    return response()->json(['error' => 'Discount values dont match'], 400);
                }
                $order->total_amount = $request->input('subtotal') - $discountValue;
            }

        }

        // Save order
        $order->payment_method_id = $paymentMethod->id;
        $order->payment_status = 'paid';
        $order->discount_total = $request->input('discount_value') ?? 0;
        $order->save();

        if ($request->input('products')) {
            foreach ($request->input('products') as $productData) {
                OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $productData['id'],
                    'product_instructions' => $productData['instructions'] ?? null,
                    'product_quantity' => $productData['quantity'],
                    'product_total_price' => $productData['price'],
                    'product_discount_price' => $productData['discount_price'] ?? null
                ]);
            }
        }

        if($request->input('enroll_guest') == true){
            $loyalty = LoyaltyProgram::where('venue_id', $venue->id)
                ->first();

            if ($loyalty) {
                $enrolledGuests = $loyalty->guests()->select('name', 'email', 'phone')
                    ->withPivot('created_at')->get();

                $mainGuest = $reservation->guests()->where('is_main', true)->first();
                $isMainGuestEnrolled = $enrolledGuests->where('email', $mainGuest->email)->first();

                if (!$isMainGuestEnrolled) {
                    $guest = Guest::where('id', $mainGuest->id)->first();

                    LoyaltyProgramGuest::create([
                        'loyalty_program_id' => $loyalty->id,
                        'guest_id' => $guest->id,
                    ]);

                    Wallet::create([
                        'guest_id' => $guest->id,
                        'balance' => 0,
                        'venue_id' => $venue->id,
                    ]);
                }
            }

        }

        $loyaltyUpdated = LoyaltyProgram::where('venue_id', $venue->id)
            ->first();

        if ($loyaltyUpdated) {

            $enrolledGuests = $loyaltyUpdated->guests()->select('name', 'email', 'phone')
                ->withPivot('created_at')->get();

            $mainGuest = $reservation->guests()->where('is_main', true)->first();
            $isMainGuestEnrolled = $enrolledGuests->where('email', $mainGuest->email)->first();

            if ($isMainGuestEnrolled) {
                $percentage = $loyaltyUpdated->reward_value;
                $pointsEarned = $mainGuest->calculatePointsEarned($order->total_amount, $percentage);

                // Update guest's wallet balance with the earned points
                if (!$mainGuest->wallet) {
                    Wallet::create([
                        'guest_id' => $mainGuest->id,
                        'balance' => $pointsEarned,
                        'venue_id' => $venue->id,
                    ]);
                } else {
                    $mainGuest->wallet->increment('balance', $pointsEarned);
                }

                // Add a record to the earnPointsHistory table with guest_id, reservation_id, and points_earned
                $mainGuest->earnPointsHistory()->create([
                    'reservation_id' => $reservation->id,
                    'points_earned' => $pointsEarned,
                    'venue_id' => $venue->id,
                ]);
            }
        }

        return response()->json([
            'message' => 'Order assigned to reservation successfully'
        ]);
    }

    public function providePaymentMethod(Request $request, $id): \Illuminate\Http\JsonResponse
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
            'payment_method' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $reservation = Reservation::where('restaurant_id', $venue->id)->where('id', $id)->first();
        if (!$reservation) {
            return response()->json(['error' => 'The reservation could not be found'], 404);
        }

        $paymentMethod = PaymentMethod::where('name', $request->input('payment_method'))->first();

        if (!$paymentMethod) {
            return response()->json(['error' => 'Payment method not found'], 404);
        }

        // Create a new order
        $order = new Order();
        $order->total_amount = $request->input('subtotal');
        $order->subtotal = $request->input('subtotal');
        $order->reservation_id = $reservation->id;
        $order->restaurant_id = $venue->id;
        $order->payment_status = 'paid';
        $order->payment_method_id = $paymentMethod->id;
        $order->save();


        $loyalty = LoyaltyProgram::where('venue_id', $venue->id)
            ->first();

        if ($loyalty) {

            $enrolledGuests = $loyalty->guests()->select('name', 'email', 'phone')
                ->withPivot('created_at')->get();

            $mainGuest = $reservation->guests()->where('is_main', true)->first();
            $isMainGuestEnrolled = $enrolledGuests->where('email', $mainGuest->email)->first();

            if ($isMainGuestEnrolled) {
                $percentage = $loyalty->reward_value;
                $pointsEarned = $mainGuest->calculatePointsEarned($order->total_amount, $percentage);

                // Update guest's wallet balance with the earned points
                if (!$mainGuest->wallet) {
                    Wallet::create([
                        'guest_id' => $mainGuest->id,
                        'balance' => $pointsEarned,
                        'venue_id' => $venue->id,
                    ]);
                } else {
                    $mainGuest->wallet->increment('balance', $pointsEarned);
                }

                // Add a record to the earnPointsHistory table with guest_id, reservation_id, and points_earned
                $mainGuest->earnPointsHistory()->create([
                    'reservation_id' => $reservation->id,
                    'points_earned' => $pointsEarned,
                    'venue_id' => $venue->id,
                ]);
            }
        }

        return response()->json([
            'message' => 'Payment information provided successfully'
        ]);

    }

    public function applyPromo(Request $request, $id): \Illuminate\Http\JsonResponse
    {

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'promotion_id' => 'required|exists:promotions,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

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

        $reservation = Reservation::where('restaurant_id', $venue->id)->where('id', $id)->first();
        if (!$reservation) {
            return response()->json(['error' => 'The reservation could not be found'], 404);
        }

        $promotion = Promotion::with('discounts')->findOrFail($request->input('promotion_id'));

        // Check if an order exists, if not don't apply the promotion
        if (!$reservation->order || $reservation->order->promotion_id) {
            // you cannot apply promo to an order that doesn't exist
            return response()->json(['error' => 'Not eligible for applying promotion'], 400);
        } else {
            $order = $reservation->order;
        }

        // Update the order with the promotion
        $order->promotion_id = $promotion->id;
        $order->save();

        // Update subtotal and total based on the discount logic
        $subtotal = $reservation->order->subtotal;

        if ($reservation->order->subtotal && $promotion->discounts->first()) {
            $discountType = $promotion->discounts->first()->type;
            $discountValue = $promotion->discounts->first()->value;

            if ($discountType === 'fixed') {
                // Calculate total amount as subtotal - fixed discount value
                $order->total_amount = max(0, $subtotal - $discountValue);;
            } elseif ($discountType === 'percentage') {
                // Calculate total amount as subtotal * (1 - discount percentage)
                $order->total_amount = max(0, $subtotal * (1 - ($discountValue / 100)));
            }
            $order->discount_total = $subtotal - $order->total_amount;

        } else {
            $order->total_amount = $subtotal;
        }

        $order->save();

        return response()->json([
            'message' => 'Promotion assigned to reservation successfully'
        ]);

    }

    /**
     * @OA\Get(
     *     path="/filter",
     *     summary="Filter reservations by various criteria",
     *     description="Filter reservations by start date, guest ID, table ID, guest count, confirmed status, insertion type, and source",
     *     tags={"Reservations"},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date in the format of Y-m-d",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             format="date",
     *             example="2023-04-21"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="guest_id",
     *         in="query",
     *         description="Guest ID to filter reservations by",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             example=1234
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="table_id",
     *         in="query",
     *         description="Table ID to filter reservations by",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="guest_count_min",
     *         in="query",
     *         description="Minimum guest count to filter reservations by",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             example=2
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="guest_count_max",
     *         in="query",
     *         description="Maximum guest count to filter reservations by",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             example=4
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="confirmed",
     *         in="query",
     *         description="Confirmation status of the reservation (1 for confirmed, 2 for cancelled)",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="insertion_type",
     *         in="query",
     *         description="Insertion type to filter reservations by",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             example="app"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="source",
     *         in="query",
     *         description="Source to filter reservations by",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             example="web"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             items={
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="table_id", type="integer"),
     *                 @OA\Property(property="start_time", type="string", format="date-time"),
     *                 @OA\Property(property="end_time", type="string", format="date-time"),
     *                 @OA\Property(property="seating_arrangement", type="string"),
     *                 @OA\Property(property="guest_count", type="integer"),
     *                 @OA\Property(property="notes", type="string"),
     *                 @OA\Property(property="confirmed", type="integer"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="errors", type="object", example={"start_date": {"The start date field is required."}})
     *         )
     *     )
     * )
     */
    public function filter(Request $request)
    {
        $startDate = $request->input('start_date');
        $guestId = $request->input('guest_id');
        $tableId = $request->input('table_id');
        $guestCountMin = $request->input('guest_count_min');
        $guestCountMax = $request->input('guest_count_max');
        $confirmed = $request->input('confirmed');
        $insertionType = $request->input('insertion_type');
        $source = $request->input('source');

        // Validate the start date format
        if ($startDate !== null) {
            $validator = Validator::make(['start_date' => $startDate], [
                'start_date' => 'required|date_format:Y-m-d'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        }

        // Validate the guest ID format
        if ($guestId !== null) {
            $validator = Validator::make(['guest_id' => $guestId], [
                'guest_id' => 'required|integer|exists:guests,id'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        }

        // Validate the table ID format
        if ($tableId !== null) {
            $validator = Validator::make(['table_id' => $tableId], [
                'table_id' => 'required|integer|exists:tables,id'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        }

        // Validate the guest count format
        if ($guestCountMin !== null || $guestCountMax !== null) {
            $validator = Validator::make([
                'guest_count_min' => $guestCountMin,
                'guest_count_max' => $guestCountMax
            ], [
                'guest_count_min' => 'nullable|integer',
                'guest_count_max' => 'nullable|integer'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            if ($guestCountMin !== null && $guestCountMax !== null && $guestCountMin > $guestCountMax) {
                return response()->json(['error' => 'guest_count_min cannot be greater than guest_count_max'], 422);
            }
        }

        // Validate the confirmed status format
        if ($confirmed !== null) {
            $validator = Validator::make(['confirmed' => $confirmed], [
                'confirmed' => 'required|in:0,1,2'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        }

        // Validate the insertion type format
        if ($insertionType !== null) {
            $validator = Validator::make(['insertion_type' => $insertionType], [
                'insertion_type' => 'required|in:manually_entered,snapfood_app'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        }

        // Validate the source format
        if ($source !== null) {
            $validator = Validator::make(['source' => $source], [
                'source' => 'required|in:snapfood,facebook,call,instagram,google,website,other'

            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        }

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

        // Build the query
        $query = Reservation::where('restaurant_id', $venue->id)->with(['table', 'guests']);

        if ($startDate !== null) {
            $query->where('start_time', '>=', $startDate);
        }

        if ($guestId !== null) {
            $query->whereHas('guests', function ($q) use ($guestId) {
                $q->where('guest_id', $guestId);
            });
        }

        if ($tableId !== null) {
            $query->where('table_id', $tableId);
        }

        if ($guestCountMin !== null) {
            $query->where('guest_count', '>=', $guestCountMin);
        }

        if ($guestCountMax !== null) {
            $query->where('guest_count', '<=', $guestCountMax);
        }

        if ($confirmed !== null) {
            $query->where('confirmed', $confirmed);
        }

        if ($insertionType !== null) {
            $query->where('insertion_type', $insertionType);
        }

        if ($source !== null) {
            $query->where('source', $source);
        }

        // Execute the query and return the results
        $reservations = $query->get();

        return response()->json(['data' => $reservations], 200);
    }


    /**
     * @OA\Get(
     *     path="/availability",
     *     summary="List available timeslots for a table",
     *     tags={"Table Management"},
     *     @OA\Parameter(
     *         name="table_id",
     *         in="query",
     *         description="ID of the table",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Date in the format YYYY-MM-DD",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of available timeslots",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="available_timeslots",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(
     *                         property="start_time",
     *                         type="string",
     *                         description="Start time of the available timeslot in the format YYYY-MM-DD HH:MM:SS"
     *                     ),
     *                     @OA\Property(
     *                         property="end_time",
     *                         type="string",
     *                         description="End time of the available timeslot in the format YYYY-MM-DD HH:MM:SS"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="error",
     *                 type="object",
     *                 additionalProperties={
     *                     "type": "string"
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Table not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     )
     * )
     */
    public function getAvailabilityByTable(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'table_id' => 'required|numeric',
            'date' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $table_id = $request->input('table_id');
        $date = $request->input('date');

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

        $table = Table::where('restaurant_id', $venue->id)->find($table_id);

        if (!$table) {
            return response()->json(['message' => 'Table not found'], 404);
        }

        $reservations = $table->reservations()->whereDate('start_time', $date)->get();

        $reservation_start_time = strtotime($venue->reservation_start_time);
        $reservation_end_time = strtotime($venue->reservation_end_time);

        $available_timeslots = [];

        $current_time = strtotime($date . ' ' . date('H:i:s', $reservation_start_time));

        while ($current_time <= strtotime($date . ' ' . date('H:i:s', $reservation_end_time))) {
            $start_time = date('Y-m-d H:i:s', $current_time);
            $end_time = date('Y-m-d H:i:s', $current_time + 60 * 30);

            $is_available = true;

            foreach ($reservations as $reservation) {
                if (($start_time >= $reservation->start_time && $start_time < $reservation->end_time)
                    || ($end_time > $reservation->start_time && $end_time <= $reservation->end_time)
                    || ($start_time <= $reservation->start_time && $end_time >= $reservation->end_time)) {
                    $is_available = false;
                    break;
                }
            }

            if ($is_available) {
                $available_timeslots[] = [
                    'start_time' => $start_time,
                    'end_time' => $end_time
                ];
            }

            $current_time += 60 * 30; // increment by 30 minutes
        }

        return response()->json([
            'available_timeslots' => $available_timeslots
        ]);
    }

    public function enrollGuest(Request $request): \Illuminate\Http\JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $loyalty = LoyaltyProgram::where('venue_id', $venue->id)
            ->first();

        if (!$loyalty) {
            return response()->json(['error' => 'Loyalty not found'], 404);
        }

        $enrolledGuests = $loyalty->guests()->select('name', 'email', 'phone')
            ->withPivot('created_at')->get();

        $isMainGuestEnrolled = $enrolledGuests->where('email', $request->input('email'))->first();

        if ($isMainGuestEnrolled) {
            return response()->json(['error' => 'This guest is already enrolled'], 400);
        }

        $guest = Guest::where('email', $request->input('email'))->first();

        LoyaltyProgramGuest::create([
            'loyalty_program_id' => $loyalty->id,
            'guest_id' => $guest->id,
        ]);

        Wallet::create([
            'guest_id' => $guest->id,
            'venue_id' => $venue->id,
            'balance' => 0,
        ]);

        return response()->json([
            'message' => 'Guest enrolled successfully'
        ]);
    }

    public function webReservationCreate(Request $request): \Illuminate\Http\JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // is for main will be used for golf, restaurant
        $validator = Validator::make($request->all(), [
            'table_id' => 'required|integer',
            'start_time' => 'required|date',
            'guest_count' => 'required|integer',
            'notes' => 'nullable|string',
            'occation' => 'nullable|string',
            'main_guest' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Check if the table is available during the requested time
        $table = Table::find($request->input('table_id'));

        if ($table) {
            if(!$venue->tables->contains($table)){
                return response()->json(['error' => 'Table does not belong to the restaurant'], 400);
            }

            $start_time = $request->input('start_time');
            $end_time = Carbon::parse($start_time)->addHours(2)->toDateString();

            if (!$table->is_available($start_time, $end_time)) {
                return response()->json(['error' => 'Table is not available during the requested time'], 400);
            }
        } else {
            return response()->json(['error' => 'Table does not exist'], 400);
        }

        $hotelGymId = null;
        $hotelEventsHallId = null;
        $hotelRestaurantId = null;
        if(!$request->input('is_for_main')) {
            if($request->facility_type === 'hotel_restaurant') {
                $hotelRestaurant = HotelRestaurant::where('venue_id', $venue->id)->first();
                if (!$hotelRestaurant) {
                    return response()->json(['error' => 'Hotel Restaurant not found'], 404);
                }

                $hotelRestaurantId = $hotelRestaurant->id;
            }

            if($request->facility_type === 'hotel_gym') {
                $hotelGym = HotelGym::where('venue_id', $venue->id)->first();
                if (!$hotelGym) {
                    return response()->json(['error' => 'Hotel Gym not found'], 404);
                }

                $hotelGymId = $hotelGym->id;

            }
            if($request->facility_type === 'hotel_events_hall') {
                $hotelEventsHall = HotelEventsHall::where('venue_id', $venue->id)->first();
                if (!$hotelEventsHall) {
                    return response()->json(['error' => 'Hotel Events Hall not found'], 404);
                }

                $hotelEventsHallId = $hotelEventsHall->id;
            }
        }

        // Create the reservation
        $reservation = Reservation::create([
            'table_id' => $request->input('table_id'),
            'start_time' => $request->input('start_time'),
            'end_time' => Carbon::parse($request->input('start_time'))->addHours(2),
            'guest_count' => $request->input('guest_count'),
            'notes' => $request->input('notes'),
            'occation' => $request->input('occation'),
            'source' => 'vb-web',
            'insertion_type' => 'from_integration',
            'restaurant_id' => $venue->id,
            'hotel_restaurant_id' => $request->facility_type === 'hotel_restaurant' ? $hotelRestaurantId : null,
            'hotel_events_hall_id' => $request->facility_type === 'hotel_events_hall' ? $hotelEventsHallId : null,
            'hotel_gym_id' =>  $request->facility_type === 'hotel_gym' ? $hotelGymId : null,
        ]);

        if ($table) {
            TableReservations::create([
                'table_id' => $request->input('table_id'),
                'reservation_id' => $reservation->id,
                'start_time' => $request->input('start_time'),
                'end_time' => Carbon::parse($request->input('start_time'))->addHours(2),
            ]);
        }

        // Check if the 'data' field exists in the request and is not empty
        if ($request->has('main_guest') && !empty($request->input('main_guest'))) {
            $guestData = $request->input('main_guest');

            $checkGuest = null;
            if ($guestData['is_phone']) {
                $checkGuest = Guest::where('phone', $guestData['phone'])->first();
            }
            else {
                $checkGuest = Guest::where('email', $guestData['email'])->first();
            }
            if (!$checkGuest) {
                $guest = Guest::create([
                    'email' => $guestData['email'],
                    'phone' => $guestData['phone'],
                    'restaurant_id' => $venue->id,
                    'is_main' => true,
                    'allow_restaurant_msg' => $guestData['resto_email'],
                    'allow_venueboost_msg' => $guestData['venueboost_email'],
                    'allow_remind_msg' => $guestData['reminder'],
                ]);
            } else {
                $guest = $checkGuest;
                $guest->allow_restaurant_msg = $guestData['resto_email'];
                $guest->allow_venueboost_msg = $guestData['venueboost_email'];
                $guest->allow_remind_msg = $guestData['reminder'];
                $guest->save();
            }
        }

        // Assign guests to the reservation
        $reservation->guests()->saveMany([$guest]);

        $loyaltyProgram = LoyaltyProgram::where('venue_id', $venue->id)->first();

        if (!$loyaltyProgram) {
            $canEnroll = false;
        } else {
            $enrolledGuests = $loyaltyProgram->guests()->select('name', 'email', 'phone')
                ->withPivot('created_at')->get();

            $isMainGuestEnrolled = $enrolledGuests->where('email', $guest->email)->first();

            if ($isMainGuestEnrolled) {
                $canEnroll = false;
            } else {
                $canEnroll = true;
            }
        }

        $reservationSuccess = new StdClass();
        $reservationSuccess->reservation_id = '#' . $apiCallVenueAppKey . '#' .$reservation->id;
        $reservationSuccess->can_enroll = $canEnroll;

        // Calculate the points earned
        $loyalty = LoyaltyProgram::where('venue_id', $venue->id)
            ->first();

        $reservationAmount = 0;
        // TODO: after v1 testing, we need to validate also phone number
        // TODO: after v1 testing, improve this for orders, payments links etc where the amount is present
        if ($loyalty && $reservationAmount > 0) {
            $percentage = $loyalty->reward_value;
            $pointsEarned = $guest->calculatePointsEarned($reservationAmount, $percentage);

            $enrolledGuests = $loyalty->guests()->select('name', 'email', 'phone')
                ->withPivot('created_at')->get();

            $isMainGuestEnrolled = $enrolledGuests->where('email', $guest->email)->first();

            if($isMainGuestEnrolled) {
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
            }


            // Add a record to the earnPointsHistory table with guest_id, reservation_id, and points_earned
            $guest->earnPointsHistory()->create([
                'reservation_id' => $reservation->id,
                'points_earned' => $pointsEarned,
                'venue_id' => $venue->id,
            ]);
        }

        if ($guest->is_main) {
            try {
                if ($guest->phone != null && $guest->phone != "") {
                    // Twilio account information
                    $account_sid = env('TWILIO_ACCOUNT_SID');
                    $auth_token = env('TWILIO_AUTH_TOKEN');
                    $twilio_number = env('TWILIO_NUMBER');

                    $client = new Client($account_sid, $auth_token);
                    // Send SMS message
                    $client->messages->create(
                        $guest->phone,
                        array(
                            'from' => $twilio_number,
                            'body' => "Your reservation at {$venue->name} on {$reservation->start_time} has been placed. We look forward to seeing you!"
                        )
                    );
                }

                if ($guest->email != null && $guest->email != "") {
                    Mail::to($guest->email)->send(new NewReservationEmail($venue->name));
                }
            }
            catch (Exception $e) {
                // do nothing
            }
        }
        return response()->json(['message' => 'Reservation created successfully', 'data' => $reservationSuccess], 201);
    }


    public function webGetBookTimes(Request $request): \Illuminate\Http\JsonResponse
    {
        $apiCallVenueAppKey = request()->get('venue_app_key');
        if (!$apiCallVenueAppKey) {
            return response()->json(['error' => 'Venue app key is required'], 400);
        }

        $venue = Restaurant::where('app_key', $apiCallVenueAppKey)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // $validator = Validator::make($request->all(), [
        //     'start_time' => 'required|date',
        // ]);

        // if ($validator->fails()) {
        //     return response()->json(['error' => $validator->errors()], 400);
        // }

        $date = Carbon::now(); 
        $startOfDay = $date->copy()->startOfDay(); 
        $endOfDay = $date->copy()->endOfDay(); 

        
        $book_times = Reservation::where('restaurant_id', $venue->id)
            ->where('start_time', '>=',$startOfDay)
            ->where('end_time', '<=',$endOfDay)
            ->count();
        
    
        return response()->json(['data' => $book_times], 200);
    }

     // TODO: after v1 testing, add here the logic for using points as discount and updating the wallet

//    public function usePointsAsDiscount(Request $request)
//    {
//        // Logic to get guest and reservation details
//
//        // Convert points to a dollar discount based on the conversion rate (e.g., 100 points = -$1 discount)
//        $conversionRate = 100;
//        $pointsToUse = $guest->wallet ? $guest->wallet->balance : 0;
//        $discountAmount = $guest->convertPointsToDiscount($pointsToUse, $conversionRate);
//
//        // Apply the discount to the reservation amount
//        $reservationAmount -= $discountAmount;
//
//        // Update guest's wallet balance with the used points
//        if ($guest->wallet) {
//            $guest->wallet->decrement('balance', $pointsToUse);
//        }
//
//        // Add a record to the usePointsHistory table with guest_id, reservation_id, and points_used
//        $guest->usePointsHistory()->create([
//            'reservation_id' => $reservationId,
//            'points_used' => $pointsToUse,
//        ]);
//
//        // Return the updated reservation details and discount amount to the frontend
//        // ...
//    }

    public function getValidPromotionsForGuest($guestId): \Illuminate\Http\JsonResponse
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

        $guest = Guest::find($guestId);

        if(!$guest || $guest->restaurant_id != $venue->id) {
            return response()->json(['error' => 'Guest not found'], 404);
        }

        // Get the guest's confirmed reservation count
        $confirmedReservationCount = $guest->reservations->where('confirmed', '1')->count();

        // TODO: after v1 testing, check this logic for new guests
        // Get promotions with related discounts that meet the reservation count condition
        $promotions = Promotion::where('venue_id', $venue->id)->with(['discounts' => function ($query) use ($confirmedReservationCount) {
            $query->where(function ($query) use ($confirmedReservationCount) {
                $query->where('reservation_count', '<=', $confirmedReservationCount + 1);
            })->orWhere('reservation_count', 0); // Allow for 0 reservation_count
        }])->get();

        return response()->json([
            'promotions' => $promotions,
            'message' => 'Valid promotions retrieved successfully'
        ]);
    }

}


function generateRandomEmail() {
    $randomNumbers = sprintf('%04d', mt_rand(0, 9999));
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomChar = $alphabet[rand(0, strlen($alphabet) - 1)];

    return$randomNumbers . $randomChar;
}
