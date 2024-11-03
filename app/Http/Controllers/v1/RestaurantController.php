<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Mail\VenueDemoSuccessEmail;
use App\Models\BowlingAvailability;
use App\Models\City;
use App\Models\Country;
use App\Models\Discount;
use App\Models\FeatureUsageCredit;
use App\Models\FeatureUsageCreditHistory;
use App\Models\Gallery;
use App\Models\GolfAvailability;
use App\Models\GymAvailability;
use App\Models\HotelEventsHall;
use App\Models\HotelEventsHallAvailability;
use App\Models\HotelGym;
use App\Models\HotelGymAvailability;
use App\Models\HotelRestaurant;
use App\Models\HotelRestaurantAvailability;
use App\Models\OpeningHour;
use App\Models\Photo;
use App\Models\Product;
use App\Models\RentalUnit;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantConfiguration;
use App\Models\RestaurantReferral;
use App\Models\State;
use App\Models\StoreSetting;
use App\Models\User;
use App\Models\Address;
use App\Models\VenueIndustry;
use App\Models\VenuePauseHistory;
use App\Models\VenueType;
use App\Models\VenueWallet;
use App\Models\VenueWhitelabelCustomization;
use App\Models\VenueWhiteLabelInformation;
use App\Models\IndustryBrandCustomizationElement;
use App\Models\WalletHistory;
use App\Rules\ValidPauseReason;
use App\Services\VenueService;
use Dompdf\Dompdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\VendorVerifyEmail;
use Carbon\Carbon;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use stdClass;

/**
 * @OA\Info(
 *   title="Restaurant Configuration API",
 *   version="1.0",
 *   description="This API allows use Restaurant Configuration Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="RestaurantConfiguration",
 *   description="Operations related to Restaurant Configuration"
 * )
 */

class RestaurantController extends Controller
{

    protected $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    const BASE_DIRECTORY = 'images/restaurant';

    public function get(Request $request): \Illuminate\Http\JsonResponse
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
            'category' => 'integer',
            'search' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $category_id = $request->input('category');
            $search = $request->input('search');

            $products = DB::table('products')->where('restaurant_id', $venue->id);
            if ($category_id) {
                $products = $products->whereRaw("
                    (
                        id IN
                        (
                            SELECT product_id
                            FROM product_category
                            WHERE category_id = ?
                        )
                    )
                    ", [$category_id]);
            }

            error_log("category_id $category_id");

            if ($search) {
                $products = $products->whereRaw('LOWER(title) LIKE ?', ["%" . strtolower($search) . "%"]);
            }

            $products = $products->orderBy('created_at', 'DESC')->get();
            return response()->json(['message' => 'Products retrieved successfully', 'products' => $products], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getOne($id)
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
            $product = Product::where('restaurant_id', $venue->id)->find($id);
            if (!$product) {
                return response()->json(['message' => 'Not found product'], 404);
            }
            $options = DB::table('product_options')->where('product_id', $product->id)->where('type', 'option')->get();
            $additions = DB::table('product_options')->where('product_id', $product->id)->where('type', 'addition')->get();

            return response()->json(['message' => 'Product retrieved successfully',
                'product' => $product, 'options' => $options, 'additions' => $additions], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function create(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|string|email',
            'password' => 'required|string',
            'restaurant_name' => 'required|string',
            'restaurant_email' => 'required|string|email',
            'phone' => 'required|string',
//            'cuisine_types' => 'required|array',
//            'cuisine_types' => 'required|array',
//            'pricing' => 'required|string',
            'logo_image' => 'nullable|string',
            'cover_image' => 'nullable|string',
            'venue_type' => 'required|string',
            'venue_industry' => 'required|string',
//            'amenities' => 'required|array',
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
            'state' => 'required|string',
            'city' => 'required|string',
            'postcode' => 'required|string',
            'contact_id' => 'required|integer'
        ]);


        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $restaurantOwnerData = $request->only('first_name', 'last_name', 'email', 'password');

            $contact_id = $request->input('contact_id');
            $existingContactAffiliate = DB::table('contact_sales')->where('id', $contact_id)->first();
            $existingContactId = DB::table('contact_sales')->where('id', $contact_id)->first();
            if(!$existingContactId) {
                return response()->json(['error' => 'Not found contact id'], 404);
            }

            $restaurant_name = $request->input('restaurant_name');
            $restaurant_email = $request->input('restaurant_email');
            $phone = $request->input('phone');
            $website = $request->input('website');
            $venue_type = $request->input('venue_type');
//            $pricing = $request->input('pricing');
//            $capacity = $request->input('capacity');
//            $cuisine_types = $request->input('cuisine_types');
//            $amenities = $request->input('amenities');

            $restaurantAddressData = $request->only('address_line1', 'address_line2', 'state', 'city', 'postcode');

            $owner_user = User::where('email', )->first();
            if ($owner_user) {
                return response()->json(['message' => 'Email is already exist'], 400);
            }



            $hashedPassword = Hash::make($restaurantOwnerData['password']);
            $newUserID = DB::table('users')->insertGetId([
                'name' =>  $restaurantOwnerData['first_name']. " " . $restaurantOwnerData['last_name'],
                'country_code' => 'US',
                'email' => $restaurantOwnerData['email'],
                'password' => $hashedPassword,
            ]);

            $owner_user = User::where('id', $newUserID)->first();
            $venueType = VenueType::where('short_name', $venue_type)->first();
            $venueIndustry = VenueIndustry::where('short_name', $request->input('venue_industry'))->first();


            $address = Address::create($restaurantAddressData);

            $logo_image = $request->input('logo_image');
            $logo_image_path = null;
            // check if logo_image is uploaded
            if (@$logo_image) {
                $path = self::BASE_DIRECTORY . '/' . uniqid();
                Storage::disk('public')->put($path, base64_decode($logo_image));
                $logo_image_path = $path;
            }

            $cover_image = $request->input('cover_image');
            $cover_image_path = null;
            // check if cover_image is uploaded
            if (@$cover_image) {
                $path = self::BASE_DIRECTORY . '/' . uniqid();
                Storage::disk('public')->put($path, base64_decode($cover_image));
                $cover_image_path = $path;
            }

            $restaurant = new Restaurant();
            if ($logo_image_path) {
                $restaurant->logo = $logo_image_path;
            }
            if ($cover_image_path) {
                $restaurant->cover = $cover_image_path;
            }
            else{
                $restaurant->cover = '';
            }

            // TODO: after v1 testing fix this for image upload
            // TODO: after v1 testing fix this for italy
            // TODO: after v1 testing fix this for pricing range and maybe capacity, cusine types etc
            $restaurant->name = $restaurant_name;
            $restaurant->short_code = generateStringShortCode($restaurant_name);
            $restaurant->app_key = generateStringAppKey($restaurant_name);;
            $restaurant->venue_type = $venueType->id;
            $restaurant->venue_industry = $venueIndustry->id;
            $restaurant->is_main_venue = 1;
            $restaurant->phone_number = $phone;
            $restaurant->email = $restaurant_email;
            $restaurant->website = $website ?? "";
            $restaurant->pricing = '$';
            $restaurant->capacity = $capacity ?? 0;
            $restaurant->user_id = $owner_user->id;
            $restaurant->contact_id = $contact_id;
            $restaurant->status = 'completed';
            $restaurant->save();


            $affiliate_id = $existingContactAffiliate->affiliate_id;
            // Check if an affiliate is associated with this contact
            if (!is_null($affiliate_id)) {
                // Update the affiliate status to 'registered'
                DB::table('contact_sales')->where('id', $contact_id)->update(['affiliate_status' => 'registered']);

                // Create a record in the venue_affiliate table
                DB::table('venue_affiliate')->insert([
                    'venue_id' => $restaurant->id, // Assuming $restaurant is the created venue
                    'affiliate_id' => $affiliate_id,
                    'affiliate_code' => $existingContactAffiliate->affiliate_code,
                    'contact_id' => $contact_id
                ]);
            }

            // Check if a referral is associated with this contact
            $existingContactReferral = DB::table('contact_sales')->where('id', $contact_id)->first();
            $referer_id = $existingContactAffiliate->referer_id;

            // Check if a referral is associated with this contact
            if (!is_null($referer_id)) {
                // Update the referral status to 'registered'
                DB::table('contact_sales')->where('id', $contact_id)->update(['referral_status' => 'registered']);

                $referral_id = DB::table('restaurant_referrals')
                    ->insertGetId([
                        'restaurant_id' => $referer_id,
                        'referral_code' => $existingContactReferral->referral_code,
                        'register_id' => $restaurant->id,
                        'contact_id' => $contact_id,
                        'used_time' => Carbon::now()
                    ]);

                $restaurant->used_referral_id = $referral_id;
                $restaurant->save();
            }



            DB::table('employees')->insert([
                'name' => $owner_user->name,
                'email' => $owner_user->email,
                'role_id' => $venue_type === 'Hotel' ? 5 : ($venue_type === 'Golf Venue' ? 13 : 2),
                'salary' => 0,
                'salary_frequency' => 'monthly',
                'restaurant_id' => $restaurant->id,
                'user_id' => $owner_user->id
            ]);


            if ($venueType->definition === 'accommodation') {
                $this->venueService->manageAccommodationVenueAddonsAndPlan($restaurant->id);
            } elseif ($venueType->definition === 'sport_entertainment') {
                $this->venueService->manageSportsAndEntertainmentVenueAddonsAndPlan($restaurant->id);
            } else {
                $this->venueService->manageGeneralVenueAddonsAndPlan($restaurant->id);
            }

//            if ($cuisine_types) {
//                foreach ($cuisine_types as $key => $cuisine) {
//                    DB::table('restaurant_cuisine_types')->insert([
//                        'cuisine_types_id' => $cuisine,
//                        'restaurants_id' => $restaurant->id
//                    ]);
//                }
//            }
//            if ($amenities) {
//                foreach ($amenities as $key => $amenity) {
//                    DB::table('restaurant_amenities')->insert([
//                        'amenities_id' => $amenity,
//                        'restaurants_id' => $restaurant->id
//                    ]);
//                }
//            }
            if ($address) {
                DB::table('restaurant_addresses')->insert([
                    'address_id' => $address->id,
                    'restaurants_id' => $restaurant->id
                ]);
            }


            Mail::to($owner_user->email)->send(new VenueDemoSuccessEmail($restaurant->name, $restaurantOwnerData['email'], $restaurantOwnerData['password']));

            return response()->json(['message' => 'Venue is created successfully', 'restaurant' => $restaurant ], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function createAnotherDemo(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'restaurant_name' => 'required|string',
            'restaurant_email' => 'required|string|email',
            'phone' => 'required|string',
            'logo_image' => 'nullable|string',
            'venue_type' => 'required|string',
            'venue_industry' => 'required|string',
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
            'state' => 'required|string',
            'city' => 'required|string',
            'postcode' => 'required|string',
            'contact_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {

            $contact_id = $request->input('contact_id');

            $existingContactId = DB::table('contact_sales')->where('id', $contact_id)->first();
            if(!$existingContactId) {
                return response()->json(['error' => 'Not found contact id'], 404);
            }

            $previousVenue = DB::table('restaurants')->where('contact_id', $contact_id)->first();

            if(!$previousVenue) {
                return response()->json(['error' => 'Not eligible for another dem account'], 404);
            }

            $restaurant_name = $request->input('restaurant_name');
            $restaurant_email = $request->input('restaurant_email');
            $phone = $request->input('phone');;
            $venue_type = $request->input('venue_type');

            $restaurantAddressData = $request->only('address_line1', 'address_line2', 'state', 'city', 'postcode');

            $owner_user = User::where('id', $previousVenue->user_id)->first();
            $venueType = VenueType::where('short_name', $venue_type)->first();
            $venueIndustry = VenueIndustry::where('short_name', $request->input('venue_industry'))->first();


            $address = Address::create($restaurantAddressData);


            $restaurant = new Restaurant();
            $restaurant->logo = 'logo';
            $restaurant->cover = '';
            $restaurant->name = $restaurant_name;
            $restaurant->short_code = generateStringShortCode($restaurant_name);
            $restaurant->app_key = generateStringAppKey($restaurant_name);;
            $restaurant->venue_type = $venueType->id;
            $restaurant->venue_industry = $venueIndustry->id;
            $restaurant->is_main_venue = 1;
            $restaurant->phone_number = $phone;
            $restaurant->email = $restaurant_email;
            $restaurant->website = "";
            $restaurant->pricing = "";
            $restaurant->capacity = $capacity ?? 0;
            $restaurant->user_id = $owner_user->id;
            $restaurant->contact_id = $contact_id;
            $restaurant->status = 'completed';
            $restaurant->save();

            // Check if the email already has a '+' symbol followed by a number
            // If no existing number is found, append a random number between 1 and 100
            $randomNumber = rand(1, 1000);
            $modifiedEmail = substr($owner_user->email, 0, strpos($owner_user->email, '@')) . '+'. $randomNumber . substr($owner_user->email, strpos($owner_user->email, '@'));

            DB::table('employees')->insert([
                'name' => $owner_user->name,
                'email' => $modifiedEmail,
                'role_id' => $venue_type === 'Hotel' ? 5 : ($venue_type === 'Golf Venue' ? 13 : 2),
                'salary' => 0,
                'salary_frequency' => 'monthly',
                'restaurant_id' => $restaurant->id,
                'user_id' => $owner_user->id
            ]);


            if ($venueType->definition === 'accommodation') {
                $this->venueService->manageAccommodationVenueAddonsAndPlan($restaurant->id);
            } elseif ($venueType->definition === 'sport_entertainment') {
                $this->venueService->manageSportsAndEntertainmentVenueAddonsAndPlan($restaurant->id);
            } else {
                $this->venueService->manageGeneralVenueAddonsAndPlan($restaurant->id);
            }

            if ($address) {
                DB::table('restaurant_addresses')->insert([
                    'address_id' => $address->id,
                    'restaurants_id' => $restaurant->id
                ]);
            }

            return response()->json(['message' => 'Venue is created successfully', 'restaurant' => $restaurant ], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/accounts/update-restaurant",
     *     summary="Update restaurant profile",
     *     tags={"RestaurantConfiguration"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="restaurant_name",
     *                 type="string",
     *                 nullable=true,
     *                 description="Updated name of the restaurant",
     *             ),
     *             @OA\Property(
     *                 property="restaurant_email",
     *                 type="string",
     *                 format="email",
     *                 nullable=true,
     *                 description="Updated email of the restaurant",
     *             ),
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 nullable=true,
     *                 description="Updated phone number of the restaurant",
     *             ),
     *             @OA\Property(
     *                 property="cuisine_types",
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                 ),
     *                 nullable=true,
     *                 description="Updated array of cuisine type IDs",
     *             ),
     *             @OA\Property(
     *                 property="pricing",
     *                 type="string",
     *                 nullable=true,
     *                 description="Updated pricing information",
     *             ),
     *             @OA\Property(
     *                 property="logo_image",
     *                 type="string",
     *                 nullable=true,
     *                 description="Updated base64-encoded logo image",
     *             ),
     *             @OA\Property(
     *                 property="cover_image",
     *                 type="string",
     *                 nullable=true,
     *                 description="Updated base64-encoded cover image",
     *             ),
     *             @OA\Property(
     *                 property="amenities",
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                 ),
     *                 nullable=true,
     *                 description="Updated array of amenity IDs",
     *             ),
     *             @OA\Property(
     *                 property="address_line1",
     *                 type="string",
     *                 nullable=true,
     *                 description="Updated address line 1",
     *             ),
     *             @OA\Property(
     *                 property="address_line2",
     *                 type="string",
     *                 nullable=true,
     *                 description="Updated address line 2",
     *             ),
     *             @OA\Property(
     *                 property="state",
     *                 type="string",
     *                 nullable=true,
     *                 description="Updated state",
     *             ),
     *             @OA\Property(
     *                 property="city",
     *                 type="string",
     *                 nullable=true,
     *                 description="Updated city",
     *             ),
     *             @OA\Property(
     *                 property="postcode",
     *                 type="string",
     *                 nullable=true,
     *                 description="Updated postcode",
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Restaurant profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Success message",
     *             ),
     *             @OA\Property(
     *                 property="restaurant",
     *                 type="object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     description="Restaurant ID",
     *                 ),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Validation error message",
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message",
     *             ),
     *         ),
     *     ),
     * )
     */

    public function update(Request $request): \Illuminate\Http\JsonResponse
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
            'restaurant_name' => 'required|string',
            'restaurant_email' => 'required|string|email',
            'phone' => 'required|string',
            'cuisine_types' => 'required',
            'pricing' => 'nullable|string',
            //'logo_image' => 'required',
            //'cover_image' => 'required',
            // 'amenities' => 'nullable|array',
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'country' => 'required|integer',
            'state' => 'required|integer',
            'city' => 'required|integer',
            'postcode' => 'required|string',
            'venue_type' => 'required|string',
            'venue_industry' => 'required|string',
            'currency' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {

            $restaurant = $venue;

            $restaurant_name = $request->input('restaurant_name');
            $restaurant_email = $request->input('restaurant_email');
            $phone = $request->input('phone');
            $website = $request->input('website');
            $pricing = $request->input('pricing');
            $capacity = $request->input('capacity');
            $cuisine_types =  json_decode($request->input('cuisine_types'), true);

            $amenities = $request->input('amenities');
            $venueType = VenueType::where('short_name',  $request->input('venue_type'))->first();
            $venueIndustry = VenueIndustry::where('short_name', $request->input('venue_industry'))->first();

            // find state name based on state id
            $state = State::where('id', $request->input('state'))->first();

            // find country name based on country id
            $country = Country::where('id', $request->input('country'))->first();

            // find city name based on city id
            $city = City::where('id', $request->input('city'))->first();

            $restaurantAddressData = [
                'address_line1' => $request->input('address_line1'),
                'address_line2' => $request->input('address_line2'),
                'state' => $state->name,
                'city' => $city->name,
                'country' => $country->name,
                'postcode' => $request->input('postcode'),
                'state_id' => $request->input('state'),
                'city_id' => $request->input('city'),
                'country_id' => $request->input('country'),
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
            ];

            $rest_addr = DB::table('restaurant_addresses')->where('restaurants_id', $restaurant->id)->first();
            if ($rest_addr) {
                Address::where('id', $rest_addr->address_id)->update($restaurantAddressData);
            } else {
                $address = Address::create($restaurantAddressData);
                DB::table('restaurant_addresses')->insert([
                    'restaurants_id' => $restaurant->id,
                    'address_id' => $address->id,
                ]);
            }

            $pathLogo = null;
            $pathCover = null;

            // check if logo image is uploaded
            if ($request->file('logo_image')) {

                $venueLogo = $request->file('logo_image');
                $requestType = 'gallery';

                // Decode base64 image data
                $photoFile = $venueLogo;
                $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();

                // Upload photo to AWS S3
                $pathLogo = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $venue->venueType->short_name . '/' . $requestType . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)), $photoFile, $filename);

                // Save photo record in the database
                $photo = new Photo();
                $photo->venue_id = $venue->id;
                $photo->image_path = $pathLogo;
                $photo->type = $requestType;
                $photo->save();

            }

            // check if cover image is uploaded
            if ($request->file('cover_image')) {

                $venueLogo = $request->file('cover_image');
                $requestType = 'gallery';

                // Decode base64 image data
                $photoFile = $venueLogo;
                $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();

                // Upload photo to AWS S3
                $pathCover = Storage::disk('s3')->putFileAs('venue_gallery_photos/' . $venue->venueType->short_name . '/' . $requestType . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)), $photoFile, $filename);

                // Save photo record in the database
                $photo = new Photo();
                $photo->venue_id = $venue->id;
                $photo->image_path = $pathCover;
                $photo->type = $requestType;
                $photo->save();

            }


            $restaurant = Restaurant::where('id', $restaurant->id)->first();
            $restaurant->logo = $pathLogo ?? $restaurant->logo;
            $restaurant->cover = $pathCover ?? $restaurant->cover;

            $restaurant->name = $restaurant_name;
            $restaurant->phone_number = $phone;
            $restaurant->email = $restaurant_email;
            $restaurant->website = $website ?? "";
            $restaurant->pricing = $pricing ?? "$";
            $restaurant->capacity = $capacity ?? 0;
            $restaurant->venue_type = $venueType->id;
            $restaurant->venue_industry = $venueIndustry->id;
            $restaurant->currency = $request->input('currency');
            $restaurant->save();

            if ($cuisine_types) {
                DB::table('restaurant_cuisine_types')->where('restaurants_id', $restaurant->id)->delete();
                foreach ($cuisine_types as $key => $cuisine) {
                    DB::table('restaurant_cuisine_types')->insert([
                        'cuisine_types_id' => $cuisine,
                        'restaurants_id' => $restaurant->id,
                    ]);
                }
            }
            if ($amenities) {
                DB::table('restaurant_amenities')->where('restaurants_id', $restaurant->id)->delete();
                foreach ($amenities as $key => $amenity) {
                    DB::table('restaurant_amenities')->insert([
                        'amenities_id' => $amenity,
                        'restaurants_id' => $restaurant->id,
                    ]);
                }
            }

            return response()->json(['message' => 'Restaurant has been updated successfully', 'restaurant' => $restaurant], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/accounts/resend-verify-email",
     *     summary="Resend verification email",
     *     tags={"RestaurantConfiguration"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Restaurant ID",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="restaurant_id",
     *                 type="integer",
     *                 description="Restaurant ID"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Verification email sent",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Success message"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     )
     * )
     */
    public function resendVerifyEmail(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $restaurant_id = $request->input('restaurant_id');
            $restaurant = Restaurant::where('id', $restaurant_id)->first();
            if (!$restaurant) {
                return response()->json(['message' => 'Invalid token'], 400);
            }
            if ($restaurant->status != 'not_verified') {
                return response()->json(['message' => 'Invalid token'], 400);
            }

            $created_at = Carbon::now();
            $expired_at = $created_at->addMinutes(5); // Add 5mins
            $serverName = env('APP_NAME');

            $data = [
                // 'iat' => $created_at->timestamp, // Issued at: time when the token was generated
                // 'nbf' => $created_at->timestamp, // Not before
                'iss' => $serverName, // Issuer
                'exp' => $expired_at->timestamp, // Expire,
                'id' => $restaurant->id,
            ];

            $jwt_token = JWT::encode($data, env('JWT_SECRET'), 'HS256');
            $email_verify_link = env('APP_URL') . "/restaurants/verify-email/$jwt_token";
            Mail::to($restaurant->owner->email)->send(new VendorVerifyEmail($restaurant->name, $email_verify_link));

            return response()->json(['message' => 'We sent a verification email again'], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/accounts/verify-email",
     *     summary="Verify email",
     *     tags={"RestaurantConfiguration"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Verification token",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="token",
     *                 type="string",
     *                 description="Verification token"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Success message"
     *             ),
     *             @OA\Property(
     *                 property="contact_token",
     *                 type="string",
     *                 description="Contact token"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Restaurant not found",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     )
     * )
     */
    public function verifyEmail(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $token = $request->input('token');

            $id = null;
            try {
                $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
                $id = $decoded->id;
            } catch (ExpiredException $expiredException) {
                return response()->json(['message' => 'Expired link'], 400);
            } catch (\Exception $e) {
                \Sentry\captureException($e);
                return response()->json(['message' => 'Invalid link'], 400);
            }

            $restaurant = Restaurant::where('id', $id)->first();
            if (!$restaurant) {
                return response()->json(['message' => 'Invalid link'], 404);
            }

            if ($restaurant->status != 'not_verified') {
                return response()->json(['message' => 'Invalid link'], 400);
            }
            $restaurant->status = 'not_payment_setup';
            $restaurant->save();

            $owner = User::where('id', $restaurant->user_id)->first();
            if ($owner) {
                $owner->restaurant_id = $restaurant->id;
                $owner->email_verified_at = date('Y-m-d H:i:s');
                $owner->save();
            }

            $created_at = Carbon::now();
            $expired_at = $created_at->addWeeks(1); // Add 1 week
            $serverName = env('APP_NAME');
            $data = [
                // 'iat' => $created_at->timestamp, // Issued at: time when the token was generated
                // 'nbf' => $created_at->timestamp, // Not before
                'iss' => $serverName, // Issuer
                'exp' => $expired_at->timestamp, // Expire,
                'id' => $restaurant->contact_id,
            ];
            $contact_token = JWT::encode($data, env('JWT_SECRET'), 'HS256');

            return response()->json(['message' => 'Your email has been verified successfully', 'contact_token' => $contact_token], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/accounts/payment-methods",
     *     summary="Get payment methods",
     *     tags={"RestaurantConfiguration"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Restaurant ID",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="restaurant_id",
     *                 type="integer",
     *                 description="Restaurant ID"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment methods retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="payment_methods",
     *                 type="array",
     *                 description="List of payment methods",
     *                 @OA\Items(
     *                     type="object",
     *                     description="Payment method object",
     *                     @OA\Property(
     *                         property="id",
     *                         type="string",
     *                         description="Payment method ID"
     *                     ),
     *                     @OA\Property(
     *                         property="type",
     *                         type="string",
     *                         description="Payment method type"
     *                     ),
     *                     @OA\Property(
     *                         property="card",
     *                         type="object",
     *                         description="Card details",
     *                         @OA\Property(
     *                             property="brand",
     *                             type="string",
     *                             description="Card brand"
     *                         ),
     *                         @OA\Property(
     *                             property="last4",
     *                             type="string",
     *                             description="Last 4 digits of the card"
     *                         ),
     *                         @OA\Property(
     *                             property="exp_month",
     *                             type="integer",
     *                             description="Expiration month of the card"
     *                         ),
     *                         @OA\Property(
     *                             property="exp_year",
     *                             type="integer",
     *                             description="Expiration year of the card"
     *                         ),
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     )
     * )
     */
    public function getPaymentMethods(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $restaurant_id = $request->input('restaurant_id');
            $restaurant = Restaurant::where('id', $restaurant_id)->first();
            if (!$restaurant) {
                return response()->json(['message' => 'Invalid restaurant'], 400);
            }
            if (!$restaurant->stripe_customer_id) {
                return response()->json(['payment_methods' => []], 200);
            }

            $stripe = new \Stripe\StripeClient (
                config('services.stripe.key')
            );
            $payment_methods = $stripe->paymentMethods->all([
                'customer' => $restaurant->stripe_customer_id,
                'type' => 'card',
            ]);

            return response()->json(['payment_methods' => $payment_methods], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/accounts/add-card",
     *     summary="Add card as payment method",
     *     tags={"RestaurantConfiguration"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Card details and restaurant ID",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="restaurant_id",
     *                 type="integer",
     *                 description="Restaurant ID"
     *             ),
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 description="Cardholder name"
     *             ),
     *             @OA\Property(
     *                 property="number",
     *                 type="string",
     *                 description="Card number"
     *             ),
     *             @OA\Property(
     *                 property="exp_month",
     *                 type="string",
     *                 description="Expiration month"
     *             ),
     *             @OA\Property(
     *                 property="exp_year",
     *                 type="string",
     *                 description="Expiration year"
     *             ),
     *             @OA\Property(
     *                 property="cvc",
     *                 type="string",
     *                 description="Card verification code (CVC)"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Card added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="payment_method",
     *                 type="object",
     *                 description="Payment method object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="string",
     *                     description="Payment method ID"
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     description="Payment method type"
     *                 ),
     *                 @OA\Property(
     *                     property="card",
     *                     type="object",
     *                     description="Card details",
     *                     @OA\Property(
     *                         property="brand",
     *                         type="string",
     *                         description="Card brand"
     *                     ),
     *                     @OA\Property(
     *                         property="last4",
     *                         type="string",
     *                         description="Last 4 digits of the card"
     *                     ),
     *                     @OA\Property(
     *                         property="exp_month",
     *                         type="integer",
     *                         description="Expiration month of the card"
     *                     ),
     *                     @OA\Property(
     *                         property="exp_year",
     *                         type="integer",
     *                         description="Expiration year of the card"
     *                     ),
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     )
     * )
     */

    public function addCard(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|integer',
            'name' => 'required|string',
            'number' => 'required|string',
            'exp_month' => 'required|string',
            'exp_year' => 'required|string',
            'cvc' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $stripe = new \Stripe\StripeClient (config('services.stripe.key'));

            $restaurant_id = $request->input('restaurant_id');
            $restaurant = Restaurant::where('id', $restaurant_id)->first();
            if (!$restaurant) {
                return response()->json(['message' => 'Invalid restaurant'], 400);
            }
            if (!$restaurant->stripe_customer_id) {
                $customer = $stripe->customers->create();
                $restaurant->stripe_customer_id = $customer->id;
                $restaurant->save();
            }

            $name = $request->input('name');
            $number = $request->input('number');
            $exp_month = $request->input('exp_month');
            $exp_year = $request->input('exp_year');
            $cvc = $request->input('cvc');

            $paymentMethod = $stripe->paymentMethods->create([
                'type' => 'card',
                'card' => [
                    'number' => $number,
                    'exp_month' => $exp_month,
                    'exp_year' => $exp_year,
                    'cvc' => $cvc,
                ],
                'metadata' => [
                    'name' => $name,
                    'cvc' => $cvc,
                ],
            ]);

            $stripe->paymentMethods->attach(
                $paymentMethod->id,
                ['customer' => $restaurant->stripe_customer_id]
            );

            return response()->json(['payment_method' => $paymentMethod], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/accounts/pay-with-card",
     *     summary="Pay with card",
     *     tags={"RestaurantConfiguration"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Payment details and restaurant ID",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="restaurant_id",
     *                 type="integer",
     *                 description="Restaurant ID"
     *             ),
     *             @OA\Property(
     *                 property="payment_method_id",
     *                 type="string",
     *                 description="Payment method ID"
     *             ),
     *             @OA\Property(
     *                 property="plan_id",
     *                 type="integer",
     *                 description="Plan ID"
     *             ),
     *             @OA\Property(
     *                 property="mode",
     *                 type="string",
     *                 description="Payment mode ('monthly' or 'yearly')"
     *             ),
     *             @OA\Property(
     *                 property="addons",
     *                 type="array",
     *                 description="List of addon IDs",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment successful",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="messgae",
     *                 type="string",
     *                 description="Success message"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     )
     * )
     */

    public function payWithCard(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|integer',
            'payment_method_id' => 'required|string',
            'plan_id' => 'required|integer',
            'mode' => 'required|in:monthly,yearly',
            'addons' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $stripe = new \Stripe\StripeClient (config('services.stripe.key'));

            $restaurant_id = $request->input('restaurant_id');
            $restaurant = Restaurant::where('id', $restaurant_id)->first();
            if (!$restaurant) {
                return response()->json(['message' => 'Invalid restaurant'], 400);
            }

            $payment_method_id = $request->input('payment_method_id');
            $plan_id = $request->input('plan_id');
            $mode = $request->input('mode');
            $addons = $request->input('addons');

            $plan = DB::table('pricing_plans')->where('id', $plan_id)->first();
            if (!$plan) {
                return response()->json(['message' => 'Invalid plan'], 400);
            }

            $total_amount = 0;
            if ($mode == 'monthly') {
                $total_amount = $plan->monthly_cost;
            } else {
                $total_amount = $plan->yearly_cost;
            }

            if ($addons && count($addons) > 0) {
                $addons = DB::table('addons')->whereIn('id', $addons)->get();
                foreach ($addons as $key => $item) {
                    $total_amount = $total_amount + $item->price;
                }
            }

            $data = [
                'payment_method' => $payment_method_id,
                'amount' => $total_amount * 100,
                'currency' => 'USD',
                'confirmation_method' => 'manual',
                'confirm' => true,
                'customer' => $restaurant->stripe_customer_id,
                'description' => 'Boost Subscription payment ' . $plan->name . ' ' . $mode,
            ];

            $payment_intent = $stripe->paymentIntents->create($data);

            $restaurant->default_payment_method = $payment_method_id;
            $restaurant->plan_id = $plan_id;
            $restaurant->plan_type = $mode;
            $restaurant->status = 'completed';
            $restaurant->active_plan = 1;
            $restaurant->last_payment_date = date('Y-m-d');
            $restaurant->save();

            if ($addons) {
                foreach ($addons as $key => $item) {
                    DB::table('restaurant_addons')->insert([
                        'addons_id' => $item->id,
                        'restaurants_id' => $restaurant->id,
                    ]);
                }
            }

            DB::table('restaurant_transactions')->insert([
                'restaurants_id' => $restaurant->id,
                'amount' => $total_amount,
                'type' => 'paid',
                'category' => 'boost_subscribe',
                'source' => 'stripe_card_' . $payment_method_id,
                'note' => 'Boost Subscription payment ' . $plan->name . ' ' . $mode,
            ]);



            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/accounts/change-subscribe",
     *     summary="Change subscription",
     *     tags={"RestaurantConfiguration"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Subscription details and restaurant ID",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 description="Change type ('cancel', 'downgrade', 'upgrade', 'reinstate')"
     *             ),
     *             @OA\Property(
     *                 property="plan_id",
     *                 type="integer",
     *                 description="New plan ID"
     *             ),
     *             @OA\Property(
     *                 property="mode",
     *                 type="string",
     *                 description="Payment mode ('monthly' or 'yearly')"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription updated",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="messgae",
     *                 type="string",
     *                 description="Success message"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="Error message"
     *             )
     *         )
     *     )
     * )
     */
    public function changeSubscription(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:cancel,downgrade,upgrade,reinstante',
            'plan_id' => 'required|integer',
            'mode' => 'nullable|in:monthly,yearly',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $stripe = new \Stripe\StripeClient (config('services.stripe.key'));

            $user = auth()->user();
            $restaurant = $user->restaurant;
            if (!$restaurant) {
                return response()->json(['message' => 'Invalid restaurant'], 400);
            }
            $restaurant = Restaurant::where('id', $restaurant->id)->first();

            $type = $request->input('type');
            $plan_id = $request->input('plan_id');
            $mode = $request->input('mode');

            $cur_plan = DB::table('pricing_plans')->where('id', $restaurant->plan_id)->first();
            if (!$cur_plan) {
                return response()->json(['message' => 'Invalid current plan'], 400);
            }

            $new_plan = DB::table('pricing_plans')->where('id', $plan_id)->first();
            if (!$new_plan) {
                return response()->json(['message' => 'Invalid plan'], 400);
            }

            $total_amount = 0;
            if ($type == 'downgrade') {
                if ($new_plan->monthly_cost > $cur_plan->monthly_cost) {
                    return response()->json(['message' => 'Invalid downgrade option'], 400);
                }

                // TODO: after v1 testing calculate money for the remaining days, then refund it in stripe.
                $total_amount = 0; // no need more money for downgrade

                $restaurant->active_plan = 1;
            } else if ($type == 'upgrade') {
                if ($new_plan->monthly_cost < $cur_plan->monthly_cost) {
                    return response()->json(['message' => 'Invalid upgrade option'], 400);
                }

                if ($restaurant->plan_type == 'monthly') {
                    $total_amount = $new_plan->monthly_cost - $cur_plan->monthly_cost;
                }
                else {
                    $total_amount = $new_plan->yearly_cost - $cur_plan->yearly_cost;
                }

                $restaurant->active_plan = 1;
            } else if ($type == 'cancel') {
                $total_amount = 0;
                $restaurant->active_plan = 0;
            } else { // reinstantite
                $now = Carbon::now();
                if ($restaurant->plan_type == 'monthly') {
                    if ($now->diffInDays(Carbon::parse($restaurant->last_payment_date)) >= 30) {
                        $total_amount = $cur_plan->monthly_cost;
                        $restaurant->last_payment_date = date('Y-m-d');
                    } else {
                        $total_amount = 0;
                    }
                } else {
                    if ($now->diffInDays(Carbon::parse($restaurant->last_payment_date)) >= 365) {
                        $total_amount = $cur_plan->yearly_cost;
                        $restaurant->last_payment_date = date('Y-m-d');
                    } else {
                        $total_amount = 0;
                    }
                }

                $restaurant->active_plan = 1;
            }

            if ($total_amount > 0.5) {
                $data = [
                    'payment_method' => $restaurant->default_payment_method,
                    'amount' => $total_amount * 100,
                    'currency' => 'USD',
                    'confirmation_method' => 'manual',
                    'confirm' => true,
                    'customer' => $restaurant->stripe_customer_id,
                    'description' => 'Boost Subscription update payment ' . $new_plan->name,
                ];
                $payment_intent = $stripe->paymentIntents->create($data);

                DB::table('restaurant_transactions')->insert([
                    'restaurants_id' => $restaurant->id,
                    'amount' => $total_amount,
                    'type' => 'paid',
                    'category' => 'boost_subscribe_update',
                    'source' => 'stripe_card_' . $restaurant->default_payment_method,
                    'note' => 'Boost Subscription update payment ' . $new_plan->name,
                ]);
            }

            if ($type != 'cancel') {
                $restaurant->plan_id = $new_plan->id;
            }
            $restaurant->save();

            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/accounts/register-config",
     *     summary="Get vendor registration configuration",
     *     tags={"RestaurantConfiguration"},
     *     @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *     type="object",
     *     @OA\Property(
     *     property="data",
     *     type="object",
     *     @OA\Property(
     *     property="cuisine_types",
     *     type="array",
     *     @OA\Items(
     *     type="object",
     *     @OA\Property(
     *     property="id",
     *     type="integer",
     *     example=1
     *     ),
     *     @OA\Property(
     *     property="name",
     *     type="string",
     *     example="American"
     *    )
     *  )
     * ),
     *     @OA\Property(
     *     property="amenities",
     *     type="array",
     *     @OA\Items(
     *     type="object",
     *     @OA\Property(
     *     property="id",
     *     type="integer",
     *     example=1
     *     ),
     *     @OA\Property(
     *     property="name",
     *     type="string",
     *     example="Wifi"
     *   )
     * )
     * ),
     *     @OA\Property(
     *     property="states",
     *     type="array",
     *     @OA\Items(
     *     type="object",
     *     @OA\Property(
     *     property="id",
     *     type="integer",
     *     example=1
     *     ),
     *     @OA\Property(
     *     property="name",
     *     type="string",
     *     example="Alabama"
     *  )
     * )
     * ),
     *     @OA\Property(
     *     property="cities",
     *     type="array",
     *     @OA\Items(
     *     type="object",
     *     @OA\Property(
     *     property="id",
     *     type="integer",
     *     example=1
     *     ),
     *     @OA\Property(
     *     property="name",
     *     type="string",
     *     example="Abbeville"
     *  )
     * )
     * )
     * )
     * )
     * )
     * )
     * )
     * )
     * )
     * )
     * )
     */
    public function getRegisterConfig(): JsonResponse|array
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
            $cuisine_types = DB::table('cuisine_types')->orderBy('name')->get();
            $amenities = DB::table('amenities')->orderBy('name')->get();
            $states = DB::table('states')->orderBy('name')->get();
            $cities = DB::table('cities')->orderBy('name')->get();
            $countries = DB::table('countries')->orderBy('name')->get();

            // Group states by country_id
            $groupedStates = $states->groupBy('country_id');
            // Group cities by state_id
            $groupedCities = $cities->groupBy('states_id');

            // Nest states within countries
            $countries->transform(function ($country) use ($groupedStates, $groupedCities) {
                $countryStates = $groupedStates->get($country->id) ?? collect([]);
                // Nest cities within states
                $countryStates->transform(function ($state) use ($groupedCities) {
                    $state->cities = $groupedCities->get($state->id) ?? collect([]);
                    return $state;
                });

                $country->states = $countryStates;
                return $country;
            });

            return [
                'cuisine_types' => $cuisine_types,
                'amenities' => $amenities,
                'states' => $states,
                'cities' => $cities,
                'countries' => $countries,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/accounts/payment-config",
     *     tags={"RestaurantConfiguration"},
     *     summary="Get payment configuration",
     *     description="Get the payment configuration including pricing plans and addons",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="pricing_plans", type="array", @OA\Items()),
     *             @OA\Property(property="addons", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function getPaymentConfig(): JsonResponse|array
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
            $pricing_plans = DB::table('pricing_plans')->orderBy('monthly_cost')->get();
            $addons = DB::table('addons')->orderBy('name')->get();

            return [
                'pricing_plans' => $pricing_plans,
                'addons' => $addons,
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            \error_log($e->getMessage());
            return new JsonResponse([], 500);
        }
    }

    public function getVenueWhiteLabelProfile() {

        $apiCallVenueAppCode = request()->get('venue_app_code');
        if (!$apiCallVenueAppCode) {
            return response()->json(['error' => 'Venue app code is required'], 400);
        }

        $venue = Restaurant::with('venueType')->where('app_key', $apiCallVenueAppCode)->first();

        $existingAddonId = DB::table('restaurant_addons')->where('restaurants_id', $venue->id)->whereIn('addons_id', [12,13,14])->get();

        if(!$existingAddonId) {
            return response()->json(['error' => 'URL cannot be opened'], 404);
        }

        if(!($venue->venue_type === 1 || $venue->venue_type === 31 || $venue->venue_type === 14 ||  $venue->venue_type === 6 || $venue->venue_type === 7 || $venue->venueType->definition === 'retail')) {
            return response()->json(['error' => 'URL cannot be opened'], 404);
        }
        $venueWithVenueType = Restaurant::with('venueType')->findOrFail($venue->id);

        $venueDetails = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();
        $venueAddress = DB::table('restaurant_addresses')->where('restaurants_id', $venue->id)->first();
        $finalVenueAddress = null;

        if ($venueAddress) {
            $finalVenueAddress = Address::where('id', $venueAddress->address_id)->first();
        };

        $mainCuisine = null;

        $cuisineTypes = DB::table('restaurant_cuisine_types')->where('restaurants_id', $venue->id)->get();
        $cuisineNames = DB::table('restaurant_cuisine_types')
            ->join('cuisine_types', 'cuisine_types.id', '=', 'restaurant_cuisine_types.cuisine_types_id')
            ->where('restaurant_cuisine_types.restaurants_id', $venue->id)
            ->pluck('cuisine_types.name')
            ->toArray();
        if (count($cuisineTypes) > 0) {
            $mainCuisine = DB::table('cuisine_types')->where('id', $cuisineTypes[0]->cuisine_types_id)->first();

        }


        // check if restaurant has configuration
        $venueConfiguration = RestaurantConfiguration::where('venue_id', $venue->id)->first();

        $allow_reservation_from = false;
        if ($venueConfiguration) {
            $allow_reservation_from = $venueConfiguration->allow_reservation_from;
        }
        // check for value of allow_reservation_from
        $retrievedWhiteLabelOverview = new StdClass();
        $retrievedWhiteLabelOverview->venue_name = $venue->name;
        $retrievedWhiteLabelOverview->reservation_start_time = $venue->reservation_start_time;
        $retrievedWhiteLabelOverview->reservation_end_time = $venue->reservation_end_time;
        $retrievedWhiteLabelOverview->address = $finalVenueAddress;
        $retrievedWhiteLabelOverview->allow_reservation_from = $allow_reservation_from;
        $retrievedWhiteLabelOverview->cover = $venue->cover ? Storage::disk('s3')->temporaryUrl($venue->cover, '+5 minutes') : null;
        $retrievedWhiteLabelOverview->logo = $venue->logo ? Storage::disk('s3')->temporaryUrl($venue->logo, '+5 minutes') : null;
        $retrievedWhiteLabelInformation = new StdClass();

        $productsByCategory = DB::table('products')
            ->join('product_category', 'products.id', '=', 'product_category.product_id')
            ->join('categories', 'product_category.category_id', '=', 'categories.id')
            ->where('products.restaurant_id', $venue->id)
            ->whereNull('categories.parent_id')
            ->orderBy('products.created_at', 'DESC')
            ->select('products.*', 'categories.title as category_name')
            ->get();

        $updatedProductsByCategory = [];

        foreach ($productsByCategory as $product) {
            $categoryName = $product->category_name;

            if (!isset($updatedProductsByCategory[$categoryName])) {
                $updatedProductsByCategory[$categoryName] = [];
            }

            if ($product->image_path !== null) {
                // Generate the new path and update the image_path attribute
                $newPath = Storage::disk('s3')->temporaryUrl($product->image_path, '+5 minutes');
                $product->image_path = $newPath;
            }

            $stockQuantity = DB::table('inventory_retail')->where('product_id', $product->id)->first();
            $product->stock_quantity = $stockQuantity->stock_quantity ?? 0;

            $updatedProductsByCategory[$categoryName][] = $product;
        }

        $gallery = Gallery::where('venue_id', $venue->id)->with('photo')->get();

        $modifiedGallery = $gallery->map(function ($item) {
            return [
                'photo_id' => $item->photo_id,
                'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
            ];
        });

        if ($venueDetails) {
            if ($venueWithVenueType->venueType->definition === 'food') {
                $retrievedWhiteLabelInformation->main_cuisine = $mainCuisine?->name;
                $retrievedWhiteLabelInformation->dining_style = $venueDetails->dining_style;
                $retrievedWhiteLabelInformation->dress_code = $venueDetails->dress_code;
                $retrievedWhiteLabelInformation->parking_details = $venueDetails->parking_details;
                $retrievedWhiteLabelInformation->neighborhood = $venueDetails->neighborhood;
                $retrievedWhiteLabelInformation->description = $venueDetails->description;
                $retrievedWhiteLabelInformation->additional = $venueDetails->additional;
                $retrievedWhiteLabelInformation->cuisine_types = $cuisineNames;
                $retrievedWhiteLabelInformation->payment_options = json_decode($venueDetails->payment_options);
                $retrievedWhiteLabelInformation->tags = json_decode($venueDetails->tags);
                $retrievedWhiteLabelInformation->has_free_wifi = $venueDetails->has_free_wifi;
                $retrievedWhiteLabelInformation->has_free_breakfast = $venueDetails->has_free_breakfast;
                $retrievedWhiteLabelInformation->benefit_title = $venueDetails->benefit_title;
                $retrievedWhiteLabelInformation->benefits = json_decode($venueDetails->benefits);
                $retrievedWhiteLabelInformation->min_money_value = $venueDetails->min_money_value;
                $retrievedWhiteLabelInformation->max_money_value = $venueDetails->max_money_value;
            }
            if ($venueWithVenueType->venueType->definition === 'accommodation') {
                $retrievedWhiteLabelInformation->has_free_wifi = $venueDetails->has_free_wifi;
                $retrievedWhiteLabelInformation->has_free_breakfast = $venueDetails->has_free_breakfast;
                $retrievedWhiteLabelInformation->benefit_title = $venueDetails->benefit_title;
                $retrievedWhiteLabelInformation->benefits = json_decode($venueDetails->benefits);
                $retrievedWhiteLabelInformation->min_money_value = $venueDetails->min_money_value;
                $retrievedWhiteLabelInformation->max_money_value = $venueDetails->max_money_value;
                $retrievedWhiteLabelInformation->has_spa = $venueDetails->has_spa;
                $retrievedWhiteLabelInformation->has_events_hall = $venueDetails->has_events_hall;
                $retrievedWhiteLabelInformation->has_restaurant = $venueDetails->has_restaurant;
                $retrievedWhiteLabelInformation->hotel_type = $venueDetails->hotel_type;
                $retrievedWhiteLabelInformation->wifi = $venueDetails->wifi;
                $retrievedWhiteLabelInformation->stars = $venueDetails->stars;
                $retrievedWhiteLabelInformation->room_service_starts_at = $venueDetails->room_service_starts_at;
                $retrievedWhiteLabelInformation->parking_details = $venueDetails->parking_details;
                $retrievedWhiteLabelInformation->description = $venueDetails->description;
                $retrievedWhiteLabelInformation->additional = $venueDetails->additional;
                $retrievedWhiteLabelInformation->restaurant_type = $venueDetails->restaurant_type;
                $retrievedWhiteLabelInformation->neighborhood = $venueDetails->neighborhood;

                $rentalUnits = RentalUnit::where('venue_id', $venue->id)
                    ->whereNull('deleted_at')
                    ->get();

                // for each rental units, return name, address and created at in day, month, year format

                $responseRentalUnits = [];

                foreach ($rentalUnits as $rentalUnit) {
                    $responseRentalUnit = new stdClass();
                    $responseRentalUnit->name = $rentalUnit->name;
                    $url = $rentalUnit->unit_code ? 'https://venueboost.io/rental/'.$rentalUnit->unit_code : null;
                    $responseRentalUnit->url = $url;

                    $accommodationDetails = $rentalUnit->accommodation_detail;
                    $squareMeters = $accommodationDetails?->square_metres;

                    if ($squareMeters !== null) {
                        // Convert square meters to square feet
                        $squareFeet = $squareMeters * 10.7639;

                        // Format the space string
                        $space = "$squareMeters m² / $squareFeet ft²";
                    } else {
                        // If square_metres is null, set space to indicate unknown
                        $space = null;
                    }
                    $responseRentalUnit->space = $space;
                    $responseRentalUnit->space_txt = 'Unit size';


                    $existingFacilities = $rentalUnit->facilities->pluck('name')->toArray();
                    // if it has a facility, adds the name to the response a random facility
                    if (count($existingFacilities) > 0) {
                        $responseRentalUnit->facility = $existingFacilities[array_rand($existingFacilities)];
                    } else {
                        $responseRentalUnit->facility = null;
                    }

                    // beds

                    $beds =  $this->venueService->getReadableBedResult($rentalUnit->id);
                    $responseRentalUnit->beds = $beds;

                    // random first photo
                    $gallery = Gallery::where('rental_unit_id', $rentalUnit->id)->with('photo')->get();

                    $modifiedGalleryForRental = $gallery->map(function ($item) {
                        return [
                            'photo_id' => $item->photo_id,
                            'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
                        ];
                    });

                    // if there is a photo, adds the path to the response
                    if (count($modifiedGalleryForRental) > 0) {
                        $responseRentalUnit->photo_path = $modifiedGalleryForRental[0]['photo_path'];
                    } else {
                        $responseRentalUnit->photo_path = null;
                    }
                    $responseRentalUnits[] = $responseRentalUnit;
                }

                $retrievedWhiteLabelInformation->rental_units = $responseRentalUnits;
            }
            if ($venueWithVenueType->venueType->definition === 'sport_entertainment') {
                $retrievedWhiteLabelInformation->description = $venueDetails->description;
                $retrievedWhiteLabelInformation->neighborhood = $venueDetails->neighborhood;
                $retrievedWhiteLabelInformation->parking_details = $venueDetails->parking_details;
                if ($venue->venueType->name === 'Golf Venue') {
                    $retrievedWhiteLabelInformation->field_m2 = $venueDetails->field_m2;
                    $retrievedWhiteLabelInformation->golf_style = $venueDetails->golf_style;
                    $retrievedWhiteLabelInformation->nr_holes = $venueDetails->nr_holes;
                    $retrievedWhiteLabelInformation->offers_restaurant = $venueDetails->offers_restaurant;
                    $retrievedWhiteLabelInformation->offers_bar = $venueDetails->offers_bar;
                    $retrievedWhiteLabelInformation->offers_snackbar = $venueDetails->offers_snackbar;
                    $retrievedWhiteLabelInformation->facilities = json_decode($venueDetails->facilities, true);

                    if($venue->venueType->name === 'Golf Venue') {
                        $managedOpeningHours = GolfAvailability::where('golf_id', $venue->id)->get();
                        $retrievedWhiteLabelInformation->availability = $managedOpeningHours;
                    }
                }
                $retrievedWhiteLabelInformation->additional = $venueDetails->additional;

                $retrievedWhiteLabelInformation->main_tag = $venueDetails->main_tag;
                $retrievedWhiteLabelInformation->tags = json_decode($venueDetails->tags, true);
                $retrievedWhiteLabelInformation->payment_options = json_decode($venueDetails->payment_options, true);
                $retrievedWhiteLabelInformation->has_free_wifi = $venueDetails->has_free_wifi;
                $retrievedWhiteLabelInformation->has_free_breakfast = $venueDetails->has_free_breakfast;
                $retrievedWhiteLabelInformation->benefit_title = $venueDetails->benefit_title;
                $retrievedWhiteLabelInformation->benefits = json_decode($venueDetails->benefits);
                $retrievedWhiteLabelInformation->min_money_value = $venueDetails->min_money_value;
                $retrievedWhiteLabelInformation->max_money_value = $venueDetails->max_money_value;
            }
        } else {
            if ($venueWithVenueType->venueType->definition === 'food') {
                $retrievedWhiteLabelInformation->main_cuisine = null;
                $retrievedWhiteLabelInformation->dining_style = null;
                $retrievedWhiteLabelInformation->dress_code = null;
                $retrievedWhiteLabelInformation->parking_details = null;
                $retrievedWhiteLabelInformation->neighborhood = null;
                $retrievedWhiteLabelInformation->description = null;
                $retrievedWhiteLabelInformation->additional = null;
                $retrievedWhiteLabelInformation->payment_options = null;
                $retrievedWhiteLabelInformation->tags = null;
            }
            if ($venueWithVenueType->venueType->definition === 'accommodation') {
                $retrievedWhiteLabelInformation->has_free_wifi = null;
                $retrievedWhiteLabelInformation->has_spa = null;
                $retrievedWhiteLabelInformation->has_events_hall = null;
                $retrievedWhiteLabelInformation->has_restaurant = null;
                $retrievedWhiteLabelInformation->hotel_type = null;
                $retrievedWhiteLabelInformation->wifi = null;
                $retrievedWhiteLabelInformation->stars = null;
                $retrievedWhiteLabelInformation->room_service_starts_at = null;
                $retrievedWhiteLabelInformation->parking_details = null;
                $retrievedWhiteLabelInformation->description = null;
                $retrievedWhiteLabelInformation->additional = null;
                $retrievedWhiteLabelInformation->restaurant_type = null;
                $retrievedWhiteLabelInformation->neighborhood = null;
            }
            if ($venueWithVenueType->venueType->definition === 'sport_entertainment') {
                $retrievedWhiteLabelInformation->description = null;
                $retrievedWhiteLabelInformation->neighborhood = null;
                $retrievedWhiteLabelInformation->parking_details = null;
                $retrievedWhiteLabelInformation->additional = null;
                $retrievedWhiteLabelInformation->golf_style = null;
                $retrievedWhiteLabelInformation->main_tag = null;
                $retrievedWhiteLabelInformation->tags = null;
                $retrievedWhiteLabelInformation->payment_options = null;
            }
        }
        if ($venueWithVenueType->venueType->definition === 'retail') {
            $storeSetting = StoreSetting::where('venue_id', $venue->id)?->first();
            $retrievedWhiteLabelInformation->description = $storeSetting->description ?? null;
            $retrievedWhiteLabelInformation->neighborhood = $storeSetting->neighborhood ?? null;
            $retrievedWhiteLabelInformation->additional =$storeSetting->additional ?? null;
            $retrievedWhiteLabelInformation->main_tag = $storeSetting->main_tag ?? null;
            $retrievedWhiteLabelInformation->tags = $storeSetting ? json_decode($storeSetting->tags, true) : [];
            $retrievedWhiteLabelInformation->payment_options = $storeSetting?->first() ? json_decode($storeSetting->payment_options, true) : [];

            $shippingZones = $venue->shippingZones()->with(['shippingMethods' => function($query) {
                $query->withPivot('has_minimum_order_amount', 'flat_rate_cost', 'minimum_order_amount');
            }])->orderBy('created_at', 'desc')->get();

            $shippingMethods = [];
            // if exists get first always
            if ($shippingZones->count() > 0) {
                $shippingZone = $shippingZones[0];

                foreach ($shippingZone->shippingMethods as $method) {

                    $shippingMethods[] = [
                        'method_id' => $method->id,
                        'method_type' => $method->type,
                        'method_name' => $method->name === 'flat_rate' ? 'Flat Rate' : 'Free Shipping',
                        'flat_rate_cost' => $method->pivot->flat_rate_cost,
                        'has_minimum_order_amount' => $method->pivot->has_minimum_order_amount,
                        'minimum_order_amount' => $method->pivot->minimum_order_amount
                    ];
                }
            }
            $retrievedWhiteLabelInformation->shipping_methods = $shippingMethods;

        }

        // get brand profile
        $brandProfile =  IndustryBrandCustomizationElement::with(['venueBrandProfileCustomizations' => function ($query) use ($venue) {
            $query->where('venue_id', $venue->id);
        }])
            ->where('industry_id', $venue->venue_industry)
            ->get();

        // if industry is food and beverage return only specific items from brand profile
        // only those with element_name = food and beverage
        // 1. FindATimeButton
        // 2. AllButtons
        // 3. BookNowButton
        // 4. Footer
        // 5. Tags
        // 6. SubscribeBtn
        // 7. TimeSuggested
        // 8. ContactFormLeftBlock
        // 9. ContactFormBtn
        // 10. ContactFormTopLabel

        if ($venue->venueType->definition === 'food') {
            $brandProfile = $brandProfile->filter(function ($item) {
                // Filter directly on collection
                // Return only items that have element_name in the array
                // directly on the collection not in venueBrandProfileCustomizations

                return in_array($item->element_name, [
                    'FindATimeButton',
                    'AllButtons',
                    'BookNowButton',
                    'Footer',
                    'OutOfStock',
                    'Tags',
                    'SubscribeBtn',
                    'TimeSuggested',
                    'ContactFormLeftBlock',
                    'ContactFormBtn',
                    'ContactFormTopLabel'
                ]);
            })->values(); // Use values() to reset the keys and get a re-indexed array
        }

        // if industry is retail return only specific items from brand profile
        // only those with element_name =
        // 1. AllButtons
        // 2. CartPlusButton
        // 3. Footer
        // 4. Tags
        // 5. OutOfStock
        // 6. SubscribeBtn
        // 7. YourCart
        // 8. AddToCart
        // 9. ContactFormLeftBlock
        // 10. ContactFormBtn
        // 11. ContactFormTopLabel

        if ($venue->venueType->definition === 'retail') {
            $brandProfile = $brandProfile->filter(function ($item) {
                // Filter directly on collection
                // Return only items that have element_name in the array
                // directly on the collection not in venueBrandProfileCustomizations

                return in_array($item->element_name, [
                    'AllButtons',
                    'CartPlusButton',
                    'Footer',
                    'Tags',
                    'OutOfStock',
                    'SubscribeBtn',
                    'YourCart',
                    'AddToCart',
                    'ContactFormLeftBlock',
                    'ContactFormBtn',
                    'ContactFormTopLabel'
                ]);
            })->values(); // Use values() to reset the keys and get a re-indexed array
        }

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

        // if industry is sport_entertainment return only specific items from brand profile
        // only those with element_name = sport_entertainment
        // 1. AllButtons
        // 2. Footer
        // 3. ContactFormLeftBlock
        // 4. ContactFormBtn
        // 5. ContactFormTopLabel
        // 6. SubscribeBtn
        if ($venue->venueType->definition === 'sport_entertainment') {
            $brandProfile = $brandProfile->filter(function ($item) {
                // Filter directly on collection
                // Return only items that have element_name in the array
                // directly on the collection not in venueBrandProfileCustomizations
                return in_array($item->element_name, [
                    'AllButtons',
                    'Tags',
                    'Footer',
                    'ContactFormLeftBlock',
                    'ContactFormBtn',
                    'ContactFormTopLabel',
                    'SubscribeBtn',
                ]);
            })->values(); // Use values() to reset the keys and get a re-indexed array
        }


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

        $venueWhiteLabelInformation->additional_information = $retrievedWhiteLabelInformation;
        $venueWhiteLabelInformation->overview = $retrievedWhiteLabelOverview;
        $venueWhiteLabelInformation->photos = $modifiedGallery;
        $venueWhiteLabelInformation->menu = $updatedProductsByCategory;
        $venueWhiteLabelInformation->brand_profile = $brandProfile;
        $venueWhiteLabelInformation->other_customations = $customizationBrandInformation;
        $venueWhiteLabelInformation->full_whitelabel = $venue->full_whitelabel == 1;

        $hotelFacilities = new StdClass();
        $hotelFacilities->gym = null;
        $hotelFacilities->events_hall = null;
        $hotelFacilities->restaraunt = null;

        if ($venueWithVenueType->venueType->short_name === 'hotel') {

            // Check if the hotel events hall exists
            $hasHotelEventsHall = HotelEventsHall::where('venue_id', $venue->id)->first();
            if ($hasHotelEventsHall) {
                $hotelFacilityEventsHall = new StdClass();
                $hotelFacilityEventsHall->id = $hasHotelEventsHall->id;
                $hotelFacilityEventsHall->name = $hasHotelEventsHall->name;
                $hotelFacilityEventsHall->description = $hasHotelEventsHall->description;

                $managedOpeningHours = HotelEventsHallAvailability::where('events_hall_id', $hasHotelEventsHall->id)->get();
                $galleryEventsHall = Gallery::where('hotel_events_hall_id',  $hasHotelEventsHall->id)->get();

                $managedGallery = $galleryEventsHall->map(function ($item) {
                    return [
                        'photo_id' => $item->photo_id,
                        'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
                    ];
                });

                $hotelFacilityEventsHall->availability = $managedOpeningHours;
                $hotelFacilityEventsHall->gallery = $managedGallery;

                $hotelFacilities->events_hall = $hotelFacilityEventsHall;

            }

            // Check if the hotel gym exists
            $hasHotelGym = HotelGym::where('venue_id', $venue->id)->first();
            if ($hasHotelGym) {
                $hotelGym = new StdClass();
                $hotelGym->id = $hasHotelGym->id;
                $hotelGym->name = $hasHotelGym->name;
                $hotelGym->description = $hasHotelGym->description;

                $managedOpeningHours = HotelGymAvailability::where('gym_id', $hasHotelGym->id)->get();
                $galleryGym = Gallery::where('hotel_gym_id',  $hasHotelGym->id)->get();

                $managedGallery = $galleryGym->map(function ($item) {
                    return [
                        'photo_id' => $item->photo_id,
                        'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
                    ];
                });

                $hotelGym->availability = $managedOpeningHours;
                $hotelGym->gallery = $managedGallery;

                $hotelFacilities->gym = $hotelGym;
            }

            // Check if the hotel has restaurant exists
            $hasHotelRestaurant = HotelRestaurant::where('venue_id', $venue->id)->first();
            if ($hasHotelRestaurant) {
                $hotelRestaurant = new StdClass();
                $hotelRestaurant->id = $hasHotelRestaurant->id;
                $hotelRestaurant->name = $hasHotelRestaurant->name;
                $hotelRestaurant->description = $hasHotelRestaurant->description;

                $managedOpeningHours = HotelRestaurantAvailability::where('restaurant_id', $hasHotelRestaurant->id)->get();
                $galleryRestaurant = Gallery::where('hotel_restaurant_id',  $hasHotelRestaurant->id)->get();

                $managedGallery = $galleryRestaurant->map(function ($item) {
                    return [
                        'photo_id' => $item->photo_id,
                        'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
                    ];
                });

                $hotelRestaurant->availability = $managedOpeningHours;
                $hotelRestaurant->gallery = $managedGallery;

                $hotelFacilities->restaraunt = $hotelRestaurant;

            }

        }

        $venueWhiteLabelInformation->facilities = $hotelFacilities;

        // from discounts
        // get latest unexpired and active discount for the venue
        // discount has end date and not is expired
        $restaurantDiscount = Discount::where('venue_id', $venue->id)->where('status', true)->where('end_time', '>=', Carbon::now())->orderBy('created_at', 'desc')->first();
        $venueWhiteLabelInformation->discount = $restaurantDiscount ?: 0;
        $venueWhiteLabelInformation->delivery_fee = $venueDetails->delivery_fee ?? 0;


        // Get currency setting for the venue
        $storeSetting = StoreSetting::where('venue_id', $venue->id)->first();
        $currency = $storeSetting->currency ?? null;

        $allowed_only_from = $venue->venueType->definition === 'retail' ? 'retail': $venue->venueType->short_name;

        if ($venue->venueType->name == 'Pharmacy') {
            $allowed_only_from = 'pharmacy';
        }

        return response()->json([
            'message' => 'Venue profile retrieved successfully',
            'venue' => $venueWhiteLabelInformation,
            'allowed_only_from' => $allowed_only_from,
            'currency' => $currency ?? '$',
        ]);
    }


    public function deletePhoto($id): JsonResponse
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

        $photo = Photo::find($id);

        if (!$photo) {
            return response()->json(['error' => 'Photo not found'], 404);
        }
        if($photo->venue_id != $venue->id) {
            return response()->json(['error' => 'Venue not found for this photo'], 404);
        }

        $gallery = Gallery::where('photo_id', $id)->first();

        // Delete photo from AWS S3
        Storage::disk('s3')->delete($photo->image_path);

        // Delete photo from database
        $photo->delete();

        // Delete photo from gallery
        $gallery->delete();

        return response()->json(['message' => 'Photo deleted successfully']);
    }

    public function uploadPhoto(Request $request): JsonResponse
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
            $gallery->venue_id = $venue->id;
            $gallery->photo_id = $photo->id;
            $gallery->save();

            return response()->json(['message' => 'Photo uploaded successfully']);
        }

        return response()->json(['error' => 'No photo uploaded'], 400);
    }

    public function facilityUploadPhoto(Request $request): JsonResponse
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
            'photo' => 'required|image|max:15360', // Maximum file size of 15MB
            'type' => 'required|string',
            'facility_type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        };



        if ($venue->venueType->name !== 'Hotel' &&
            ($request->facility_type === 'hotel_events_hall' || $request->facility_type === 'hotel_restaurant' || $request->facility_type === 'hotel_gym')) {
            return response()->json(['error' => 'Venue is not a hotel venue'], 400);
        }

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

            if($request->facility_type === 'hotel_restaurant') {

                // Check if the hotel restaurant exists
                $hasHotelRestaurant = HotelRestaurant::where('venue_id', $venue->id)->first();
                if ($hasHotelRestaurant) {
                    $hotelRestaurantId = $hasHotelRestaurant->id;
                } else {
                    $hotelRestaurant = new HotelRestaurant();
                    $hotelRestaurant->name = 'Your Restaurant';
                    $hotelRestaurant->venue_id = $venue->id;
                    $hotelRestaurant->save();
                    $hotelRestaurantId = $hotelRestaurant->id;
                }

                $gallery->hotel_restaurant_id = $hotelRestaurantId;
            }

            if($request->facility_type === 'hotel_gym') {

                // Check if the hotel gym exists
                $hasHotelGym = HotelGym::where('venue_id', $venue->id)->first();
                if ($hasHotelGym) {
                    $hotelGymId = $hasHotelGym->id;
                } else {
                    $hotelGym = new HotelGym();
                    $hotelGym->name = 'Your Gym';
                    $hotelGym->venue_id = $venue->id;
                    $hotelGym->save();
                    $hotelGymId = $hotelGym->id;
                }

                $gallery->hotel_gym_id = $hotelGymId;
            }

            if($request->facility_type === 'hotel_events_hall') {

                // Check if the hotel events hall exists
                $hasHotelEventsHall = HotelEventsHall::where('venue_id', $venue->id)->first();
                if ($hasHotelEventsHall) {
                    $hotelEventsHallId = $hasHotelEventsHall->id;
                } else {
                    $hotelEventsHall = new HotelEventsHall();
                    $hotelEventsHall->name = 'Your Events Hall';
                    $hotelEventsHall->venue_id = $venue->id;
                    $hotelEventsHall->save();
                    $hotelEventsHallId = $hotelEventsHall->id;
                }

                $gallery->hotel_events_hall_id = $hotelEventsHallId;
            }

            $gallery->photo_id = $photo_id;
            $gallery->venue_id = $venue->id;
            $gallery->save();


            return response()->json(['message' => 'Photo uploaded successfully']);
        }

        return response()->json(['error' => 'No photo uploaded'], 400);
    }


    public function manageUpdateAvailability(Request $request): JsonResponse
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
            'type' => 'required|string',
            'availability' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        };


        $scheduleData = $request->availability;

        if ($venue->venueType->name !== 'Golf Venue' && $request->type === 'golf') {
            return response()->json(['error' => 'Venue is not a golf venue'], 400);
        }

        if ($venue->venueType->name !== 'Gym' && $request->type === 'gym') {
            return response()->json(['error' => 'Venue is not a gym venue'], 400);
        }

        if ($venue->venueType->name !== 'Bowling' && $request->type === 'bowling') {
            return response()->json(['error' => 'Venue is not a bowling venue'], 400);
        }

        if ($venue->venueType->name !== 'Hotel' &&
            ($request->type === 'hotel_events_hall' || $request->type === 'hotel_restaurant' || $request->type === 'hotel_gym')) {
            return response()->json(['error' => 'Venue is not a hotel venue'], 400);
        }

        if($request->type === 'golf') {

            foreach ($scheduleData as $record) {
                // Get the day_of_week and other data from the record
                $dayOfWeek = $record['day_of_week'];
                $openTime = $record['open_time'];
                $closeTime = $record['close_time'];

                // Check if the row exists for the specific golf id and day of week
                $existingRow = GolfAvailability::where('golf_id', $venue->id)
                    ->where('day_of_week', $dayOfWeek)
                    ->first();

                if ($existingRow) {
                    // If row exists, update it with the data from the request
                    $existingRow->update([
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                    ]);
                } else {
                    // If row doesn't exist, create a new row
                    GolfAvailability::create([
                        'golf_id' => $venue->id,
                        'day_of_week' => $dayOfWeek,
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                    ]);
                }
            }

        }

        if($request->type === 'gym') {

            foreach ($scheduleData as $record) {
                // Get the day_of_week and other data from the record
                $dayOfWeek = $record['day_of_week'];
                $openTime = $record['open_time'];
                $closeTime = $record['close_time'];

                // Check if the row exists for the specific gym id and day of week
                $existingRow = GymAvailability::where('gym_id', $venue->id)
                    ->where('day_of_week', $dayOfWeek)
                    ->first();

                if ($existingRow) {
                    // If row exists, update it with the data from the request
                    $existingRow->update([
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                    ]);
                } else {
                    // If row doesn't exist, create a new row
                    GymAvailability::create([
                        'gym_id' => $venue->id,
                        'day_of_week' => $dayOfWeek,
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                    ]);
                }
            }

        }

        if($request->type === 'bowling') {

            foreach ($scheduleData as $record) {
                // Get the day_of_week and other data from the record
                $dayOfWeek = $record['day_of_week'];
                $openTime = $record['open_time'];
                $closeTime = $record['close_time'];

                // Check if the row exists for the specific bowling_id id and day of week
                $existingRow = BowlingAvailability::where('bowling_id', $venue->id)
                    ->where('day_of_week', $dayOfWeek)
                    ->first();

                if ($existingRow) {
                    // If row exists, update it with the data from the request
                    $existingRow->update([
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                    ]);
                } else {
                    // If row doesn't exist, create a new row
                    BowlingAvailability::create([
                        'bowling_id' => $venue->id,
                        'day_of_week' => $dayOfWeek,
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                    ]);
                }
            }

        }

        if($request->type === 'hotel_restaurant') {

            // Check if the hotel restaurant exists
            $hasHotelRestaurant = HotelRestaurant::where('venue_id', $venue->id)->first();
            if ($hasHotelRestaurant) {
                $hotelRestaurantId = $hasHotelRestaurant->id;
            } else {
                $hotelRestaurant = new HotelRestaurant();
                $hotelRestaurant->name = 'Your Restaurant';
                $hotelRestaurant->venue_id = $venue->id;
                $hotelRestaurant->save();
                $hotelRestaurantId = $hotelRestaurant->id;
            }

            foreach ($scheduleData as $record) {
                // Get the day_of_week and other data from the record
                $dayOfWeek = $record['day_of_week'];
                $openTime = $record['open_time'];
                $closeTime = $record['close_time'];

                // Check if the row exists for the specific restaurant id and day of week
                $existingRow = HotelRestaurantAvailability::where('restaurant_id', $hotelRestaurantId)
                    ->where('day_of_week', $dayOfWeek)
                    ->first();

                if ($existingRow) {
                    // If row exists, update it with the data from the request
                    $existingRow->update([
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                    ]);
                } else {
                    // If row doesn't exist, create a new row
                    HotelRestaurantAvailability::create([
                        'restaurant_id' => $hotelRestaurantId,
                        'day_of_week' => $dayOfWeek,
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                    ]);
                }
            }
        }

        if($request->type === 'hotel_gym') {

            // Check if the hotel gym exists
            $hasHotelGym = HotelGym::where('venue_id', $venue->id)->first();
            if ($hasHotelGym) {
                $hotelGymId = $hasHotelGym->id;
            } else {
                $hotelGym = new HotelGym();
                $hotelGym->name = 'Your Gym';
                $hotelGym->venue_id = $venue->id;
                $hotelGym->save();
                $hotelGymId = $hotelGym->id;
            }

            foreach ($scheduleData as $record) {
                // Get the day_of_week and other data from the record
                $dayOfWeek = $record['day_of_week'];
                $openTime = $record['open_time'];
                $closeTime = $record['close_time'];

                // Check if the row exists for the specific gym id and day of week
                $existingRow = HotelGymAvailability::where('gym_id', $hotelGymId)
                    ->where('day_of_week', $dayOfWeek)
                    ->first();

                if ($existingRow) {
                    // If row exists, update it with the data from the request
                    $existingRow->update([
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                    ]);
                } else {
                    // If row doesn't exist, create a new row
                    HotelGymAvailability::create([
                        'gym_id' => $hotelGymId,
                        'day_of_week' => $dayOfWeek,
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                    ]);
                }
            }

        }

        if($request->type === 'hotel_events_hall') {

            // Check if the hotel events hall exists
            $hasHotelEventsHall = HotelEventsHall::where('venue_id', $venue->id)->first();
            if ($hasHotelEventsHall) {
                $hotelEventsHallId = $hasHotelEventsHall->id;
            } else {
                $hotelEventsHall = new HotelEventsHall();
                $hotelEventsHall->name = 'Your Events Hall';
                $hotelEventsHall->venue_id = $venue->id;
                $hotelEventsHall->save();
                $hotelEventsHallId = $hotelEventsHall->id;
            }

            foreach ($scheduleData as $record) {
                // Get the day_of_week and other data from the record
                $dayOfWeek = $record['day_of_week'];
                $openTime = $record['open_time'];
                $closeTime = $record['close_time'];

                // Check if the row exists for the specific events hall id and day of week
                $existingRow = HotelEventsHallAvailability::where('events_hall_id', $hotelEventsHallId)
                    ->where('day_of_week', $dayOfWeek)
                    ->first();

                if ($existingRow) {
                    // If row exists, update it with the data from the request
                    $existingRow->update([
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                    ]);
                } else {
                    // If row doesn't exist, create a new row
                    HotelEventsHallAvailability::create([
                        'events_hall_id' => $hotelEventsHallId,
                        'day_of_week' => $dayOfWeek,
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                    ]);
                }
            }

        }

        return response()->json([
            'message' => 'Availability schedule updated successfully',
        ]);


    }

    public function manageWhiteLabelUpdateAvailability(Request $request): JsonResponse
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
            'availability' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        };

        $scheduleData = $request->availability;

            foreach ($scheduleData as $record) {
                // Get the day_of_week and other data from the record
                $dayOfWeek = $record['day_of_week'];
                $openTime = $record['open_time'];
                $closeTime = $record['close_time'];

                // Check if the row exists for the specific venue id and day of week
                $existingRow = OpeningHour::where('restaurant_id', $venue->id)
                    ->where('day_of_week', $dayOfWeek)
                    ->first();

                if ($existingRow) {
                    // If row exists, update it with the data from the request
                    $existingRow->update([
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                        'used_in_white_label' => true
                    ]);
                } else {
                    // If row doesn't exist, create a new row
                    OpeningHour::create([
                        'restaurant_id' => $venue->id,
                        'day_of_week' => $dayOfWeek,
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                        'used_in_white_label' => true
                    ]);
                }
            }

        return response()->json([
            'message' => 'Opening Hours schedule updated successfully',
        ]);


    }

    public function checkVenueCalendarAvailability(Request $request): JsonResponse
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
            "year" => "required|integer",
            "month" => "required|integer",
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        };

        $formattedReservations = [];
        $reservations = [];

        // Get the year and month from the request
        $year = $request->input('year');
        $month = $request->input('month');

        // Calculate the start and end dates for the specified month
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Query reservations for the specified venue and dates
        $reservations = Reservation::where('restaurant_id', $venue->id)
            ->whereBetween('start_time', [$startDate, $endDate])
            ->get();

        // Format the reservations' data as needed
        foreach ($reservations as $reservation) {
            $startTime = Carbon::parse($reservation->start_time);
            $endTime = Carbon::parse($reservation->end_time);
            $formattedReservations[] = [
                'reservation_id' => $reservation->id,
                'day' => $startTime->day, // Get the day of the month from the start time
                'date' => $startTime->format('d M Y h:i A') . ' - ' . $endTime->format('h:i A'), // Format the date range
                'party_size' => $reservation->guest_count,
            ];
        }

        return response()->json($formattedReservations, 200);


    }

    public function addNewVenue(Request $request): \Illuminate\Http\JsonResponse
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
            'restaurant_name' => 'required|string',
            'restaurant_email' => 'required|string|email',
            'phone' => 'required|string',
            'logo_image' => 'nullable|string',
            'venue_type' => 'required|string',
            'venue_industry' => 'required|string',
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
            'country' => 'required|integer',
            'state' => 'required|integer',
            'city' => 'required|integer',
            'postcode' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {


            $restaurant_name = $request->input('restaurant_name');
            $restaurant_email = $request->input('restaurant_email');
            $phone = $request->input('phone');;
            $venue_type = $request->input('venue_type');

            // find state name based on state id
            $state = State::where('id', $request->input('state'))->first();

            // find country name based on country id
            $country = Country::where('id', $request->input('country'))->first();

            // find city name based on city id
            $city = City::where('id', $request->input('city'))->first();

            $restaurantAddressData = [
                'address_line1' => $request->input('address_line1'),
                'address_line2' => $request->input('address_line2'),
                'state' => $state->name,
                'city' => $city->name,
                'country' => $country->name,
                'postcode' => $request->input('postcode'),
                'state_id' => $request->input('state'),
                'city_id' => $request->input('city'),
                'country_id' => $request->input('country'),
            ];

            $owner_user = User::where('id', $venue->user_id)->first();
            $venueType = VenueType::where('short_name', $venue_type)->first();
            $venueIndustry = VenueIndustry::where('short_name', $request->input('venue_industry'))->first();

            $address = Address::create($restaurantAddressData);

            $restaurant = new Restaurant();
            $restaurant->logo = '';
            $restaurant->cover = '';
            $restaurant->name = $restaurant_name;
            $restaurant->short_code = generateStringShortCode($restaurant_name);
            $restaurant->app_key = generateStringAppKey($restaurant_name);;
            $restaurant->venue_type = $venueType->id;
            $restaurant->venue_industry = $venueIndustry->id;
            $restaurant->is_main_venue = 1;
            $restaurant->phone_number = $phone;
            $restaurant->email = $restaurant_email;
            $restaurant->website = "";
            $restaurant->pricing = "";
            $restaurant->capacity = $capacity ?? 0;
            $restaurant->user_id = $owner_user->id;
            $restaurant->status = 'completed';
            $restaurant->save();

            // Check if the email already has a '+' symbol followed by a number
            // If no existing number is found, append a random number between 1 and 100
            $randomNumber = rand(1, 1000);
            $modifiedEmail = substr($owner_user->email, 0, strpos($owner_user->email, '@')) . '+'. $randomNumber . substr($owner_user->email, strpos($owner_user->email, '@'));

            DB::table('employees')->insert([
                'name' => $owner_user->name,
                'email' => $modifiedEmail,
                'role_id' => $venue_type === 'Hotel' ? 5 : ($venue_type === 'Golf Venue' ? 13 : 2),
                'salary' => 0,
                'salary_frequency' => 'monthly',
                'restaurant_id' => $restaurant->id,
                'user_id' => $owner_user->id
            ]);


            if ($venueType->definition === 'accommodation') {
               $this->venueService->manageAccommodationVenueAddonsAndPlan($restaurant->id);
            } elseif ($venueType->definition === 'sport_entertainment') {
                $this->venueService->manageSportsAndEntertainmentVenueAddonsAndPlan($restaurant->id);
            } else {
                $this->venueService->manageGeneralVenueAddonsAndPlan($restaurant->id);
            }

            if ($address) {
                DB::table('restaurant_addresses')->insert([
                    'address_id' => $address->id,
                    'restaurants_id' => $restaurant->id
                ]);
            }

            return response()->json(['message' => 'Venue is created successfully', 'restaurant' => $restaurant ], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function allVenuesByOwner(Request $request): JsonResponse
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

        $allVenues = Restaurant::where('user_id', auth()->user()->id)
            ->with(['venueType', 'venueIndustry', 'plan',
              'venuePauseHistories' =>
                function ($query) {
                $query->whereNull('reactivated_at')
                    ->orderByDesc('created_at');
                },
             'addresses'
            ])
            ->orderByDesc('created_at')
            ->get();

        $formattedVenues = [];
        foreach ($allVenues as $venue) {
            $formattedVenue = [
                'name' => $venue->name ?? '-',
                'app_key' => $venue->app_key,
                'short_code' => $venue->short_code,
                'logo' => $venue->logo && $venue->logo !== 'logo' && $venue->logo !== 'https://via.placeholder.com/300x300' ? Storage::disk('s3')->temporaryUrl($venue->logo, '+5 minutes') : null,
                'venue_type' => $venue->venueType->name ?? '-',
                'venue_industry' => $venue->venueIndustry->name ?? '-',
                'plan' => [
                    'active_plan' => $venue->active_plan,
                    'name' => $venue->plan->name ?? '-',
                    'plan_type' => $venue->plan_type,
                ],
                'paused' => $venue->paused,
                'address' => $venue->addresses[0] ?? '-',
                'venuePauseHistories' => $venue->venuePauseHistories
            ];
            $formattedVenues[] = $formattedVenue;
        }
        return response()->json($formattedVenues, 200);


    }

    public function checkCalendarAvailability(Request $request): JsonResponse
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
            'type' => 'required|string',
            "year" => "required|integer",
            "month" => "required|integer",
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        };


        if ($venue->venueType->name !== 'Golf Venue' && $request->type === 'golf') {
            return response()->json(['error' => 'Venue is not a golf venue'], 400);
        }

        if ($venue->venueType->name !== 'Gym' && $request->type === 'gym') {
            return response()->json(['error' => 'Venue is not a gym venue'], 400);
        }

        if ($venue->venueType->name !== 'Bowling' && $request->type === 'bowling') {
            return response()->json(['error' => 'Venue is not a bowling venue'], 400);
        }

        if ($venue->venueType->name !== 'Hotel' &&
            ($request->type === 'hotel_events_hall' || $request->type === 'hotel_restaurant' || $request->type === 'hotel_gym')) {
            return response()->json(['error' => 'Venue is not a hotel venue'], 400);
        }

        $formattedReservations = [];
        $reservations = [];

        // Get the year and month from the request
        $year = $request->input('year');
        $month = $request->input('month');

        // Calculate the start and end dates for the specified month
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        if($request->type === 'golf') {

            // Query reservations for the specified venue and dates
            $reservations = Reservation::where('restaurant_id', $venue->id)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->get();

        }

        if($request->type === 'gym') {

            // Query reservations for the specified venue and dates
            $reservations = Reservation::where('restaurant_id', $venue->id)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->get();

        }

        if($request->type === 'bowling') {

            // Query reservations for the specified venue and dates
            $reservations = Reservation::where('restaurant_id', $venue->id)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->get();

        }

        if($request->type === 'hotel_restaurant') {

            $hasHotelRestaurantId = 0;
            // Check if the hotel restaurant exists
            $hasHotelRestaurant = HotelRestaurant::where('venue_id', $venue->id)->first();
            if ($hasHotelRestaurant) {
                $hasHotelRestaurantId = $hasHotelRestaurant->id;
            }

            // Query reservations for the specified venue and dates
            $reservations = Reservation::where('hotel_restaurant_id', $hasHotelRestaurantId)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->get();

        }

        if($request->type === 'hotel_gym') {

            $hasHotelGymId = 0;
            $hasHotelGym = HotelGym::where('venue_id', $venue->id)->first();
            if ($hasHotelGym) {
                $hasHotelGymId = $hasHotelGym->id;
            }

            // Query reservations for the specified venue and dates
            $reservations = Reservation::where('hotel_gym_id', $hasHotelGymId)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->get();

        }

        if($request->type === 'hotel_events_hall') {

            // Check if the hotel events hall exists
            $hasHotelEventsHall = HotelEventsHall::where('venue_id', $venue->id)->first();
            if ($hasHotelEventsHall) {
                $hasHotelEventsHallId = $hasHotelEventsHall->id;
            } else {
                $hasHotelEventsHallId = 0;

            }

            // Query reservations for the specified venue and dates
            $reservations = Reservation::where('hotel_events_hall_id', $hasHotelEventsHallId)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->get();

        }

        // Format the reservations data as needed
        foreach ($reservations as $reservation) {
            $startTime = Carbon::parse($reservation->start_time);
            $endTime = Carbon::parse($reservation->end_time);
            $formattedReservations[] = [
                'reservation_id' => $reservation->id,
                'day' => $startTime->day, // Get the day of the month from the start time
                'date' => $startTime->format('d M Y h:i A') . ' - ' . $endTime->format('h:i A'), // Format the date range
                'party_size' => $reservation->guest_count,
            ];
        }

        return response()->json($formattedReservations, 200);


    }

    public function manageUpdateInformation(Request $request): JsonResponse
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
            'type' => 'required|string',
            'name' => 'nullable|string',
            'description' => 'nullable|string',
            'restaurant_type' => 'nullable|string',
            'golf_data' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        };



        if ($venue->venueType->name !== 'Golf Venue' && $request->type === 'golf') {
            return response()->json(['error' => 'Venue is not a golf venue'], 400);
        }

        if ($venue->venueType->name !== 'Gym' && $request->type === 'gym') {
            return response()->json(['error' => 'Venue is not a gym venue'], 400);
        }

        if ($venue->venueType->name !== 'Bowling' && $request->type === 'bowling') {
            return response()->json(['error' => 'Venue is not a bowling venue'], 400);
        }

        if ($venue->venueType->name !== 'Hotel' &&
            ($request->type === 'hotel_events_hall' || $request->type === 'hotel_restaurant' || $request->type === 'hotel_gym')) {
            return response()->json(['error' => 'Venue is not a hotel venue'], 400);
        }

        if($request->type === 'golf') {

            $hasWhiteLabelInformation = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();

            if($hasWhiteLabelInformation) {

                $venueWhiteLabelInfo = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();


            } else {
                $whiteLabelInformation = new VenueWhiteLabelInformation();
                $whiteLabelInformation->venue_id = $venue->id;
                $whiteLabelInformation->save();

                $venueWhiteLabelInfo = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();

            }

            // Check if the 'golf_data' field exists in the request and is not empty
            if ($request->has('golf_data') && !empty($request->input('golf_data'))) {
                $golfData = $request->input('golf_data');



                // Check if each field exists in the 'golf_data' object and update the model accordingly
                if (isset($golfData['neighborhood'])) {
                    $venueWhiteLabelInfo->neighborhood = $golfData['neighborhood'];
                }

                if (isset($golfData['parking_details'])) {
                    $venueWhiteLabelInfo->parking_details = $golfData['parking_details'];
                }

                if (isset($golfData['payment_options'])) {
                    $venueWhiteLabelInfo->payment_options = json_encode($golfData['payment_options']);
                }

                if (isset($golfData['tags'])) {
                    $venueWhiteLabelInfo->tags = json_encode($golfData['tags']);
                }

                if (isset($golfData['additional'])) {
                    $venueWhiteLabelInfo->additional = $golfData['additional'];
                }

                if (isset($golfData['main_tag'])) {
                    $venueWhiteLabelInfo->main_tag = $golfData['main_tag'];
                }

                if (isset($golfData['field_m2'])) {
                    $venueWhiteLabelInfo->field_m2 = $golfData['field_m2'];
                }

                if (isset($golfData['golf_style'])) {
                    $venueWhiteLabelInfo->golf_style = $golfData['golf_style'];
                }

                if (isset($golfData['description'])) {
                    $venueWhiteLabelInfo->description = $golfData['description'];
                }

                if (isset($golfData['nr_holes'])) {
                    $venueWhiteLabelInfo->nr_holes = $golfData['nr_holes'];
                }

                if (isset($golfData['facilities'])) {
                    $venueWhiteLabelInfo->facilities = json_encode($golfData['facilities']);
                }

                if (isset($golfData['offers_restaurant'])) {
                    $venueWhiteLabelInfo->offers_restaurant = $golfData['offers_restaurant'];
                }

                if (isset($golfData['offers_bar'])) {
                    $venueWhiteLabelInfo->offers_bar = $golfData['offers_bar'];
                }

                if (isset($golfData['offers_snackbar'])) {
                    $venueWhiteLabelInfo->offers_snackbar = $golfData['offers_snackbar'];
                }

                // Save the updated model
                $venueWhiteLabelInfo->save();
            }

        }

        if($request->type === 'gym') {

            $hasWhiteLabelInformation = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();

            if($hasWhiteLabelInformation) {

                $venueWhiteLabelInfo = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();


            } else {
                $whiteLabelInformation = new VenueWhiteLabelInformation();
                $whiteLabelInformation->venue_id = $venue->id;
                $whiteLabelInformation->save();

                $venueWhiteLabelInfo = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();

            }

            // Check if the 'gym_data' field exists in the request and is not empty
            if ($request->has('gym_data') && !empty($request->input('gym_data'))) {
                $gymData = $request->input('gym_data');

                // Check if each field exists in the 'gym_data' object and update the model accordingly
                if (isset($gymData['neighborhood'])) {
                    $venueWhiteLabelInfo->neighborhood = $gymData['neighborhood'];
                }

                if (isset($gymData['parking_details'])) {
                    $venueWhiteLabelInfo->parking_details = $gymData['parking_details'];
                }

                if (isset($gymData['payment_options'])) {
                    $venueWhiteLabelInfo->payment_options = json_encode($gymData['payment_options']);
                }

                if (isset($gymData['equipment_types'])) {
                    $venueWhiteLabelInfo->equipment_types = json_encode($gymData['equipment_types']);
                }

                if (isset($gymData['amenities'])) {
                    $venueWhiteLabelInfo->amenities = json_encode($gymData['amenities']);
                }

                if (isset($gymData['tags'])) {
                    $venueWhiteLabelInfo->tags = json_encode($gymData['tags']);
                }

                if (isset($gymData['additional'])) {
                    $venueWhiteLabelInfo->additional = $gymData['additional'];
                }

                if (isset($gymData['main_tag'])) {
                    $venueWhiteLabelInfo->main_tag = $gymData['main_tag'];
                }

                if (isset($gymData['description'])) {
                    $venueWhiteLabelInfo->description = $gymData['description'];
                }

                // Save the updated model
                $venueWhiteLabelInfo->save();
            }

        }

        if($request->type === 'bowling') {

            $hasWhiteLabelInformation = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();

            if($hasWhiteLabelInformation) {

                $venueWhiteLabelInfo = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();


            } else {
                $whiteLabelInformation = new VenueWhiteLabelInformation();
                $whiteLabelInformation->venue_id = $venue->id;
                $whiteLabelInformation->save();

                $venueWhiteLabelInfo = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();

            }

            // Check if the 'bowling_data' field exists in the request and is not empty
            if ($request->has('bowling_data') && !empty($request->input('bowling_data'))) {
                $bowlingData = $request->input('bowling_data');

                // Check if each field exists in the 'bowling_data' object and update the model accordingly
                if (isset($bowlingData['neighborhood'])) {
                    $venueWhiteLabelInfo->neighborhood = $bowlingData['neighborhood'];
                }

                if (isset($bowlingData['parking_details'])) {
                    $venueWhiteLabelInfo->parking_details = $bowlingData['parking_details'];
                }

                if (isset($bowlingData['payment_options'])) {
                    $venueWhiteLabelInfo->payment_options = json_encode($bowlingData['payment_options']);
                }

                if (isset($bowlingData['tags'])) {
                    $venueWhiteLabelInfo->tags = json_encode($bowlingData['tags']);
                }

                if (isset($bowlingData['additional'])) {
                    $venueWhiteLabelInfo->additional = $bowlingData['additional'];
                }

                if (isset($bowlingData['main_tag'])) {
                    $venueWhiteLabelInfo->main_tag = $bowlingData['main_tag'];
                }

                if (isset($bowlingData['lanes'])) {
                    $venueWhiteLabelInfo->lanes = $bowlingData['lanes'];
                }

                if (isset($bowlingData['offers_food_and_beverage'])) {
                    $venueWhiteLabelInfo->offers_food_and_beverage = $bowlingData['offers_food_and_beverage'];
                }

                if (isset($bowlingData['advance_lane_reservation'])) {
                    $venueWhiteLabelInfo->advance_lane_reservation = $bowlingData['advance_lane_reservation'];
                }

                if (isset($bowlingData['facilities'])) {
                    $venueWhiteLabelInfo->facilities = json_encode($bowlingData['facilities']);
                }

                if (isset($bowlingData['description'])) {
                    $venueWhiteLabelInfo->description = $bowlingData['description'];
                }

                // Save the updated model
                $venueWhiteLabelInfo->save();
            }

        }

        if($request->type === 'hotel_restaurant') {

            // Check if the hotel restaurant exists
            $hasHotelRestaurant = HotelRestaurant::where('venue_id', $venue->id)->first();
            if ($hasHotelRestaurant) {
                $hasHotelRestaurant->update([
                    'name' => $request->name,
                    'description' => $request->description ?: $hasHotelRestaurant->description,
                ]);
            } else {
                $hotelRestaurant = new HotelRestaurant();
                $hotelRestaurant->name = $request->name;
                $hotelRestaurant->description = $request->description;;
                $hotelRestaurant->venue_id = $venue->id;
                $hotelRestaurant->save();
            }

            if($request->restaurant_type) {
                $hasWhiteLabelInformation = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();

                if($hasWhiteLabelInformation) {
                    $hasWhiteLabelInformation->update([
                        'restaurant_type' => $request->restaurant_type,
                    ]);
                } else {
                    $whiteLabelInformation = new VenueWhiteLabelInformation();
                    $whiteLabelInformation->restaurant_type = $request->restaurant_type;
                    $whiteLabelInformation->venue_id = $venue->id;
                    $whiteLabelInformation->save();
                }
            }


        }

        if($request->type === 'hotel_gym') {

            // Check if the hotel gym exists
            $hasHotelGym = HotelGym::where('venue_id', $venue->id)->first();
            if ($hasHotelGym) {
                $hasHotelGym->update([
                    'name' => $request->name,
                    'description' => $request->description ?: $hasHotelGym->description,
                ]);
            } else {
                $hotelGym = new HotelGym();
                $hotelGym->name = $request->name;
                $hotelGym->description = $request->description;
                $hotelGym->venue_id = $venue->id;
                $hotelGym->save();
            }

        }

        if($request->type === 'hotel_events_hall') {

            // Check if the hotel events hall exists
            $hasHotelEventsHall = HotelEventsHall::where('venue_id', $venue->id)->first();
            if ($hasHotelEventsHall) {
                $hasHotelEventsHall->update([
                    'name' => $request->name,
                    'description' => $request->description ?: $hasHotelEventsHall->description,
                ]);

            } else {
                $hotelEventsHall = new HotelEventsHall();
                $hotelEventsHall->name = $request->name;
                $hotelEventsHall->description = $request->description;
                $hotelEventsHall->venue_id = $venue->id;
                $hotelEventsHall->save();
            }

        }

        return response()->json([
            'message' => 'Information updated successfully',
        ]);


    }

    public function updateWebProfile(Request $request): JsonResponse
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
            'data' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        };



        $hasWhiteLabelInformation = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();

        if($hasWhiteLabelInformation) {

            $venueWhiteLabelInfo = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();


        } else {
            $whiteLabelInformation = new VenueWhiteLabelInformation();
            $whiteLabelInformation->venue_id = $venue->id;
            $whiteLabelInformation->save();

            $venueWhiteLabelInfo = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();

        }

        // Check if the 'data' field exists in the request and is not empty
        if ($request->has('data') && !empty($request->input('data'))) {
            $data = $request->input('data');

            // Check if each field exists in the 'data' object and update the model accordingly
            if (isset($data['neighborhood'])) {
                $venueWhiteLabelInfo->neighborhood = $data['neighborhood'];
            }

            if (isset($data['parking_details'])) {
                $venueWhiteLabelInfo->parking_details = $data['parking_details'];
            }

            if (isset($data['dining_style'])) {
                $venueWhiteLabelInfo->dining_style = $data['dining_style'];
            }

            if (isset($data['dress_code'])) {
                $venueWhiteLabelInfo->dress_code = $data['dress_code'];
            }

            if (isset($data['payment_options'])) {
                $venueWhiteLabelInfo->payment_options = json_encode($data['payment_options']);
            }

            if (isset($data['tags'])) {
                $venueWhiteLabelInfo->tags = json_encode($data['tags']);
            }

            if (isset($data['benefit_title'])) {
                $venueWhiteLabelInfo->benefit_title = $data['benefit_title'];
            }

            if (isset($data['benefits'])) {
                $venueWhiteLabelInfo->benefits = json_encode($data['benefits']);
            }

            if (isset($data['additional'])) {
                $venueWhiteLabelInfo->additional = $data['additional'];
            }

            if (isset($data['has_free_wifi'])) {
                $venueWhiteLabelInfo->has_free_wifi = $data['has_free_wifi'];
            }

            if (isset($data['has_free_breakfast'])) {
                $venueWhiteLabelInfo->has_free_breakfast = $data['has_free_breakfast'];
            }

            if (isset($data['has_spa'])) {
                $venueWhiteLabelInfo->has_spa = $data['has_spa'];
            }

            if (isset($data['has_events_hall'])) {
                $venueWhiteLabelInfo->has_events_hall = $data['has_events_hall'];
            }


            if (isset($data['has_restaurant'])) {
                $venueWhiteLabelInfo->has_restaurant = $data['has_restaurant'];
            }

            if (isset($data['description'])) {
                $venueWhiteLabelInfo->description = $data['description'];
            }

            if (isset($data['hotel_type'])) {
                $venueWhiteLabelInfo->hotel_type = $data['hotel_type'];
            }

            if (isset($data['restaurant_type'])) {
                $venueWhiteLabelInfo->restaurant_type = $data['restaurant_type'];
            }

            if (isset($data['room_service_starts_at'])) {
                $venueWhiteLabelInfo->room_service_starts_at = $data['room_service_starts_at'];
            }

            if (isset($data['stars'])) {
                $venueWhiteLabelInfo->stars = $data['stars'];
            }

            if (isset($data['wifi'])) {
                $venueWhiteLabelInfo->wifi = $data['wifi'];
            }


            if (isset($data['delivery_fee'])) {
                $venueWhiteLabelInfo->delivery_fee = $data['delivery_fee'];
            }

            if (isset($data['min_money_value'])) {
                $venueWhiteLabelInfo->min_money_value = $data['min_money_value'];
            }

            if (isset($data['max_money_value'])) {
                $venueWhiteLabelInfo->max_money_value = $data['max_money_value'];
            }

            // Save the updated model
            $venueWhiteLabelInfo->save();
        }

        if (isset($data['allow_reservation_from'])) {

            $venueConfiguration = RestaurantConfiguration::where('venue_id', $venue->id)->first();

            if ($venueConfiguration) {
                $venueConfiguration->allow_reservation_from = $data['allow_reservation_from'];
                $venueConfiguration->save();
            } else {
                $venueConfiguration = new RestaurantConfiguration();
                $venueConfiguration->venue_id = $venue->id;
                $venueConfiguration->allow_reservation_from = $data['allow_reservation_from'];
                $venueConfiguration->save();
            }
        }

        return response()->json([
            'message' => 'Information updated successfully',
        ]);


    }


    public function checkWebProfile(): JsonResponse
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


        $whiteLabelInformation = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();

        $retrievedWhiteLabelInformation = new StdClass();

        if ($whiteLabelInformation) {

            if ($venue->venueType->definition === 'food') {
                $retrievedWhiteLabelInformation->has_free_wifi = $whiteLabelInformation->has_free_wifi;
                $retrievedWhiteLabelInformation->has_free_breakfast = $whiteLabelInformation->has_free_breakfast;
                $retrievedWhiteLabelInformation->min_money_value = $whiteLabelInformation->min_money_value;
                $retrievedWhiteLabelInformation->max_money_value = $whiteLabelInformation->max_money_value;
                $retrievedWhiteLabelInformation->dining_style = $whiteLabelInformation->dining_style;
                $retrievedWhiteLabelInformation->dress_code = $whiteLabelInformation->dress_code;
                $retrievedWhiteLabelInformation->parking_details = $whiteLabelInformation->parking_details;
                $retrievedWhiteLabelInformation->neighborhood = $whiteLabelInformation->neighborhood;
                $retrievedWhiteLabelInformation->description = $whiteLabelInformation->description;
                $retrievedWhiteLabelInformation->additional = $whiteLabelInformation->additional;
                $retrievedWhiteLabelInformation->payment_options = json_decode($whiteLabelInformation->payment_options);
                $retrievedWhiteLabelInformation->tags = json_decode($whiteLabelInformation->tags);
                $retrievedWhiteLabelInformation->benefit_title = $whiteLabelInformation->benefit_title;
                $retrievedWhiteLabelInformation->benefits = json_decode($whiteLabelInformation->benefits);

                // check if restaurant has configuration
                $venueConfiguration = RestaurantConfiguration::where('venue_id', $venue->id)->first();

                $allow_reservation_from = false;
                if ($venueConfiguration) {
                    $allow_reservation_from = $venueConfiguration->allow_reservation_from;
                }

                $retrievedWhiteLabelInformation->allow_reservation_from = $allow_reservation_from;
            }

            if ($venue->venueType->definition === 'accommodation') {
                $retrievedWhiteLabelInformation->has_free_wifi = $whiteLabelInformation->has_free_wifi;
                $retrievedWhiteLabelInformation->has_free_breakfast = $whiteLabelInformation->has_free_breakfast;
                $retrievedWhiteLabelInformation->min_money_value = $whiteLabelInformation->min_money_value;
                $retrievedWhiteLabelInformation->max_money_value = $whiteLabelInformation->max_money_value;
                $retrievedWhiteLabelInformation->has_spa = $whiteLabelInformation->has_spa;
                $retrievedWhiteLabelInformation->has_events_hall = $whiteLabelInformation->has_events_hall;
                $retrievedWhiteLabelInformation->has_restaurant = $whiteLabelInformation->has_restaurant;
                $retrievedWhiteLabelInformation->hotel_type = $whiteLabelInformation->hotel_type;
                $retrievedWhiteLabelInformation->neighborhood = $whiteLabelInformation->neighborhood;
                $retrievedWhiteLabelInformation->wifi = $whiteLabelInformation->wifi;
                $retrievedWhiteLabelInformation->stars = $whiteLabelInformation->stars;
                $retrievedWhiteLabelInformation->room_service_starts_at = $whiteLabelInformation->room_service_starts_at;
                $retrievedWhiteLabelInformation->parking_details = $whiteLabelInformation->parking_details;
                $retrievedWhiteLabelInformation->description = $whiteLabelInformation->description;
                $retrievedWhiteLabelInformation->additional = $whiteLabelInformation->additional;
                $retrievedWhiteLabelInformation->restaurant_type = $whiteLabelInformation->restaurant_type;
                $retrievedWhiteLabelInformation->payment_options = json_decode($whiteLabelInformation->payment_options);
                $retrievedWhiteLabelInformation->tags = json_decode($whiteLabelInformation->tags);
                $retrievedWhiteLabelInformation->benefit_title = $whiteLabelInformation->benefit_title;
                $retrievedWhiteLabelInformation->benefits = json_decode($whiteLabelInformation->benefits);
            }
        }


        $gallery = Gallery::where('venue_id', $venue->id)->with('photo')->get();

        $modifiedGallery = $gallery->map(function ($item) {
            return [
                'photo_id' => $item->photo_id,
                'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
            ];
        });

        $url = 'https://venueboost.io/venue/'.$venue->venueType->short_name.'/'.$venue->app_key;

        $finalWhiteLabelInformation = new StdClass();
        $finalWhiteLabelInformation->gallery = $modifiedGallery;
        $finalWhiteLabelInformation->information = $retrievedWhiteLabelInformation;
        $finalWhiteLabelInformation->url = $url;

        return response()->json([
            'message' => 'Web profile retrieved successfully',
            'whiteLabelInformation' => $finalWhiteLabelInformation,
        ]);

    }

    public function usageCreditsHistory(): JsonResponse
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

        // find first the FeatureUsageCredit
        $featureUsageCredit = FeatureUsageCredit::where('venue_id', $venue->id)->first();

        if (!$featureUsageCredit) {
            return response()->json([
                'message' => 'Feature usage credit history retrieved successfully',
                'featureUsageCreditHistory' => [],
                'balance' => '0',
            ]);
        }

        $featureUsageCreditHistory = FeatureUsageCreditHistory::where('feature_usage_credit_id', $featureUsageCredit->id)->get();
        // format date, but leave the other fields as is

        $featureUsageCreditHistory = $featureUsageCreditHistory->map(function ($item) use ($venue) {

            // check first if the venue is referred or referrer from Restaurant Referral
            $referral = RestaurantReferral::where('id', $item->restaurant_referral_id)->first();
            if ($referral) {

                if ($referral->restaurant_id == $venue->id) {
                    $referralName = Restaurant::where('id', $referral->register_id)->first()->name;
                } else {
                    $referralName = Restaurant::where('id', $referral->restaurant_id)->first()->name;
                }
            } else {
                $referralName = '-';
            }
            return [
                'transaction_type' => $item->transaction_type,
                'referral' => $referralName,
                'amount' => $item->amount,
                'note' => $item->credited_by_discovery_plan_monthly ? 'Credited by Discovery Plan Monthly' : $item->used_at_feature,
                'date' => $item->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'message' => 'Feature usage credit history retrieved successfully',
            'featureUsageCreditHistory' => $featureUsageCreditHistory,
            'balance' => $featureUsageCredit->balance,
        ]);

    }

    public function walletHistory(): JsonResponse
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

        // find first the Venue Wallet
        $venueWallet = VenueWallet::where('venue_id', $venue->id)->first();

        if (!$venueWallet) {
            return response()->json([
                'message' => 'Wallet history retrieved successfully',
                'venueWalletHistory' => [],
                'balance' => '0',
            ]);
        }

        $venueWalletHistory = WalletHistory::where('wallet_id', $venueWallet->id)->get();
        // format date, but leave the other fields as is


        $venueWalletHistories = $venueWalletHistory->map(function ($item) use($venue) {
            // check first if the venue is referred or referrer from Restaurant Referral
            $referral = RestaurantReferral::where('id', $item->restaurant_referral_id)->first();
            if ($referral) {
                if ($referral->restaurant_id == $venue->id) {
                    $referralName = Restaurant::where('id', $referral->register_id)->first()->name;
                } else {
                    $referralName = Restaurant::where('id', $referral->restaurant_id)->first()->name;
                }
            }
            else {
                $referralName = '-';
            }
            return [
                'transaction_type' => $item->transaction_type,
                'referral' => $referralName,
                'amount' => $item->amount,
                'reason' => $item->reason ?? '-',
                'date' => $item->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'message' => 'Wallet history retrieved successfully',
            'venueWalletHistory' => $venueWalletHistories,
            'balance' => $venueWallet->balance,
        ]);

    }

    public function checkManageVenue(Request $request): JsonResponse
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
            'type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        };

        if ($venue->venueType->name !== 'Golf Venue' && $request->type === 'golf') {
            return response()->json(['error' => 'Venue is not a golf venue'], 400);
        }

        if ($venue->venueType->name !== 'Gym' && $request->type === 'gym') {
            return response()->json(['error' => 'Venue is not a gym venue'], 400);
        }

        if ($venue->venueType->name !== 'Bowling' && $request->type === 'bowling') {
            return response()->json(['error' => 'Venue is not a bowling venue'], 400);
        }

        if ($venue->venueType->name !== 'Hotel' &&
            ($request->type === 'hotel_events_hall' || $request->type === 'hotel_restaurant' || $request->type === 'hotel_gym')) {
            return response()->json(['error' => 'Venue is not a hotel venue'], 400);
        }

        $hasManaged = false;
        $managedInformation = new stdClass();
        $managedOpeningHours = null;
        $managedGallery = null;

        if ($request->type === 'golf') {
            $whiteLabelInformation = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();
            $managedOpeningHours = GolfAvailability::where('golf_id', $venue->id)->get();
            $galleryGolf  = Gallery::where('venue_id', $venue->id)->get();

            $managedGallery = $galleryGolf->map(function ($item) {
                return [
                    'photo_id' => $item->photo_id,
                    'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
                ];
            });

            if ($whiteLabelInformation || count($managedOpeningHours) > 0 || count($managedGallery) > 0) {
                $hasManaged = true;
            }

            $managedInformation->main_tag = $whiteLabelInformation->main_tag ?? null;
            $managedInformation->nr_holes = $whiteLabelInformation->nr_holes ?? null;
            $managedInformation->field_m2 = $whiteLabelInformation->field_m2 ?? null;
            $managedInformation->offers_restaurant = $whiteLabelInformation->offers_restaurant ?? null;
            $managedInformation->offers_bar = $whiteLabelInformation->offers_bar ?? null;
            $managedInformation->offers_snackbar = $whiteLabelInformation->offers_snackbar ?? null;
            $managedInformation->golf_style = $whiteLabelInformation->golf_style ?? null;
            $managedInformation->parking_details = $whiteLabelInformation->parking_details ?? null;
            $managedInformation->neighborhood = $whiteLabelInformation->neighborhood ?? null;
            $managedInformation->description = $whiteLabelInformation->description ?? null;
            $managedInformation->additional = $whiteLabelInformation->additional ?? null;
            $managedInformation->facilities = $whiteLabelInformation?->facilities ? json_decode($whiteLabelInformation->facilities) : [];
            $managedInformation->tags = $whiteLabelInformation?->tags ? json_decode($whiteLabelInformation->tags) : [];
            $managedInformation->payment_options = $whiteLabelInformation?->payment_options ? json_decode($whiteLabelInformation->payment_options) : [];

        }

        if ($request->type === 'gym') {
            $whiteLabelInformation = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();
            $managedOpeningHours = GymAvailability::where('gym_id', $venue->id)->get();
            $galleryGym  = Gallery::where('venue_id', $venue->id)->get();

            $managedGallery = $galleryGym->map(function ($item) {
                return [
                    'photo_id' => $item->photo_id,
                    'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
                ];
            });

            if ($whiteLabelInformation || count($managedOpeningHours) > 0 || count($managedGallery) > 0) {
                $hasManaged = true;
            }

            $managedInformation->main_tag = $whiteLabelInformation->main_tag ?? null;
            $managedInformation->equipment_types = $whiteLabelInformation?->equipment_types ? json_decode($whiteLabelInformation->equipment_types) : [];
            $managedInformation->amenities = $whiteLabelInformation?->amenities ? json_decode($whiteLabelInformation->amenities) : [];
            $managedInformation->parking_details = $whiteLabelInformation->parking_details ?? null;
            $managedInformation->neighborhood = $whiteLabelInformation->neighborhood ?? null;
            $managedInformation->description = $whiteLabelInformation->description ?? null;
            $managedInformation->additional = $whiteLabelInformation->additional ?? null;
            $managedInformation->tags = $whiteLabelInformation?->tags ? json_decode($whiteLabelInformation->tags) : [];
            $managedInformation->payment_options = $whiteLabelInformation?->payment_options ? json_decode($whiteLabelInformation->payment_options) : [];

        }

        if ($request->type === 'bowling') {
            $whiteLabelInformation = VenueWhiteLabelInformation::where('venue_id', $venue->id)->first();
            $managedOpeningHours = BowlingAvailability::where('bowling_id', $venue->id)->get();
            $galleryBowling  = Gallery::where('venue_id', $venue->id)->get();

            $managedGallery = $galleryBowling->map(function ($item) {
                return [
                    'photo_id' => $item->photo_id,
                    'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
                ];
            });


            if ($whiteLabelInformation || count($managedOpeningHours) > 0 || count($managedGallery) > 0) {
                $hasManaged = true;
            }

            $managedInformation->main_tag = $whiteLabelInformation->main_tag ?? null;
            $managedInformation->offers_food_and_beverage = $whiteLabelInformation->offers_food_and_beverage ?? null;
            $managedInformation->advance_lane_reservation = $whiteLabelInformation->advance_lane_reservation ?? null;
            $managedInformation->lanes = $whiteLabelInformation->lanes ?? null;
            $managedInformation->parking_details = $whiteLabelInformation->parking_details ?? null;
            $managedInformation->neighborhood = $whiteLabelInformation->neighborhood ?? null;
            $managedInformation->description = $whiteLabelInformation->description ?? null;
            $managedInformation->additional = $whiteLabelInformation->additional ?? null;
            $managedInformation->payment_options =  $whiteLabelInformation?->payment_options ? json_decode($whiteLabelInformation->payment_options) : [];
            $managedInformation->facilities = $whiteLabelInformation?->facilities ? json_decode($whiteLabelInformation->facilities) : [];
            $managedInformation->tags = $whiteLabelInformation?->tags ? json_decode($whiteLabelInformation->tags) : [];

        }

        if ($request->type === 'hotel_gym') {
            $managedInformation = HotelGym::where('venue_id', $venue->id)->first();
            if ($managedInformation) {
                $managedOpeningHours = HotelGymAvailability::where('gym_id', $managedInformation->id)->get();
                $galleryGym = Gallery::where('hotel_gym_id',  $managedInformation->id)->get();

                $managedGallery = $galleryGym->map(function ($item) {
                    return [
                        'photo_id' => $item->photo_id,
                        'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
                    ];
                });
            }

            if ($managedInformation || $managedOpeningHours || $managedGallery) {
                $hasManaged = true;
            }

        }

        if ($request->type === 'hotel_events_hall') {
            $managedInformation = HotelEventsHall::where('venue_id', $venue->id)->first();
            if ($managedInformation) {
                $managedOpeningHours = HotelEventsHallAvailability::where('events_hall_id', $managedInformation->id)->get();
                $galleryEventsHall = Gallery::where('hotel_events_hall_id',  $managedInformation->id)->get();

                $managedGallery = $galleryEventsHall->map(function ($item) {
                    return [
                        'photo_id' => $item->photo_id,
                        'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
                    ];
                });
            }

            if ($managedInformation || $managedOpeningHours || $managedGallery) {
                $hasManaged = true;
            }

        }

        if ($request->type === 'hotel_restaurant') {
            $managedInformation = HotelRestaurant::where('venue_id', $venue->id)->first();
            if ($managedInformation) {
                $managedOpeningHours = HotelRestaurantAvailability::where('restaurant_id', $managedInformation->id)->get();
                $galleryRestaurant = Gallery::where('hotel_restaurant_id',  $managedInformation->id)->get();

                $managedGallery = $galleryRestaurant->map(function ($item) {
                    return [
                        'photo_id' => $item->photo_id,
                        'photo_path' =>  Storage::disk('s3')->temporaryUrl($item->photo->image_path, '+5 minutes'),
                    ];
                });
            }

            if ($managedInformation || $managedOpeningHours || $managedGallery) {
                $hasManaged = true;
            }

        }

        $url = null;

        if ($request->type === 'golf') {
            $url = 'https://venueboost.io/venue/golf/'.$venue->app_key;
        }

        if ( $request->type === 'gym') {
            $url = 'https://venueboost.io/venue/gym/'.$venue->app_key;
        }

        if ($request->type === 'bowling') {
            $url = 'https://venueboost.io/venue/bowling/'.$venue->app_key;
        }


        $finalInformation = new StdClass();
        $finalInformation->availability = $managedOpeningHours;
        $finalInformation->gallery = $managedGallery;
        $finalInformation->information = $managedInformation;
        $finalInformation->url = $url;

        return response()->json([
            'message' => 'Manage information retrieved successfully',
            'information' => $finalInformation,
            'hasManaged' => $hasManaged
        ]);

    }


    public function updateEmailPreferences(Request $request): \Illuminate\Http\JsonResponse
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
            'accept_waitlist_email' => 'required|boolean',
            'accept_reservation_status_email' => 'required|boolean',
            'accept_promotion_email' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Find the venue by ID
        $venue = Restaurant::find($venue->id);

        if(!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $venue->accept_waitlist_email = $request->input('accept_waitlist_email');
        $venue->accept_reservation_status_email = $request->input('accept_reservation_status_email');
        $venue->accept_promotion_email = $request->input('accept_promotion_email');

        // Update the venue email preferences
        $venue->save();

        // Return the updated venue with only the id and updated fields
        $updatedVenue = $venue->only('id', 'accept_waitlist_email', 'accept_reservation_status_email', 'accept_promotion_email');


        // Return a response
        return response()->json([
            'venue' => $updatedVenue,
            'message' => 'Venue email preferences updated successfully']);
    }

    public function pause(Request $request): JsonResponse
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

        // Set the start_time to the current moment
        $startTime = Carbon::now();

        // Validate and get the reason, start time, and end time from the request
        $validator = Validator::make($request->all(), [
            'reason' => ['required', new ValidPauseReason],
            'start_time' => 'nullable|date',
            'end_time' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        if (!$request->input('start_time')) {
            // Update paused status of the restaurant only if start_time is not provided
            $venue->update(['paused' => true]);

            // TODO: after v1 testing -> add a cronjob to see if start time is reached and if so, pause the venue
            // TODO: after v1 testing -> add a cronjob to see if end time is reached and if so, unpause the venue
        }

        // Create a pause history record
        $pauseHistory = new VenuePauseHistory([
            'reason' => $request->input('reason'),
            'start_time' => $request->input('start_time') ? $request->input('start_time') : $startTime,
            'end_time' => $request->input('end_time'),
        ]);
        $venue->venuePauseHistories()->save($pauseHistory);

        return response()->json(['message' => 'Venue paused successfully']);
    }

    public function reactivate(): JsonResponse
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

        // Find the latest paused history record
        $latestPauseHistory = $venue->venuePauseHistories()->latest()->first();

        if (!$latestPauseHistory) {
            return response()->json(['message' => 'No pause history found']);
        }

        // Update paused status of the venue
        $venue->update(['paused' => false]);

        // Update the reactivated_at timestamp in the latest pause history record
        $latestPauseHistory->update(['reactivated_at' => now()]);

        return response()->json(['message' => 'Venue reactivated successfully']);
    }


    public function allVenues(Request $request): JsonResponse
    {

        $allVenues = Restaurant::with(['venueType', 'venueIndustry', 'plan', 'addresses', 'user'])
            ->orderByDesc('created_at')
            ->where('id', '!=', 37) // Exclude venue with ID = 37
            ->get();

        $formattedVenues = [];
        foreach ($allVenues as $venue) {

//            dd($venue->user_id);

            // add property onboarded to venue only if it has a plan, an address and a venue customized experience

            if ($venue->plan && $venue->addresses->count() && $venue->venueCustomizedExperience) {
                $venue->onboarded = true;
            } else {
                $venue->onboarded = false;
            }

            // knowing state id, city id, country id, get the name of each and create formated address
            $venueAddress = $venue->addresses[0] ?? null;

            $venueAddressString = $venueAddress->address_line1 ?? '';

            if (!empty($venueAddress->address_line2)) {
                $venueAddressString .= ', ' . $venueAddress->address_line2;
            }

            if (!empty($venueAddress->city) && is_object($venueAddress->city) && property_exists($venueAddress->city, 'name')) {
                $venueAddressString .= ', ' . $venueAddress->city->name;
            }

            if (!empty($venueAddress->state) && is_object($venueAddress->state) && property_exists($venueAddress->state, 'name')) {
                $venueAddressString .= ', ' . $venueAddress->state->name;
            }

            if (!empty($venueAddress->country) && is_object($venueAddress->country) && property_exists($venueAddress->country, 'name')) {
                $venueAddressString .= ', ' . $venueAddress->country->name;
            }

            if (!empty($venueAddress->zip_code)) {
                $venueAddressString .= ', ' . $venueAddress->zip_code;
            }

            $formattedVenue = [
                'id' => $venue->id,
                'name' => $venue->name ?? '-',
                'app_key' => $venue->app_key,
                'short_code' => $venue->short_code,
                'logo' => $venue->logo && $venue->logo !== 'logo' && $venue->logo !== 'https://via.placeholder.com/300x300' ? Storage::disk('s3')->temporaryUrl($venue->logo, '+5 minutes') : null,
                'venue_type' => $venue->venueType->name ?? '-',
                'venue_industry' => $venue->venueIndustry->name ?? '-',
                'plan' => formatPlanDetails([
                    'active_plan' => $venue->active_plan,
                    'name' => $venue->plan->name ?? '-',
                    'plan_type' => $venue->plan_type,
                ]),
                'status' => $venue->status,
                'address' => $venueAddressString,
                'owner' => $venue->user->name,
                'onboarded' => $venue->onboarded,
                'email' => $venue->email ?? '-',
                'created_at' => $venue->created_at ? $venue->created_at->format('d-m-Y H:i:s') : '-',
            ];
            $formattedVenues[] = $formattedVenue;
        }
        return response()->json(['data'=>$formattedVenues], 200);


    }


    public function generateQRCodeForVenue(Request $request): JsonResponse
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

        // check if an already qr code for venue exists
        $venueQrCode = $venue->qr_code_path;
        if (!$venueQrCode) {
            $url = 'https://venueboost.io/venue/' . $venue->venueType->short_name . '/' . $venue->app_key;
            $qrcode = QrCode::format('png')->size(300)->generate($url);
            // Define the file name for the QR code
            $fileName = 'qr_code_' . $venue->id . '.png';

            file_put_contents(storage_path('app/public/uploads/' . $fileName), $qrcode);
            Storage::put('app/public/uploads/' . $fileName, $qrcode);

            $temporaryFilePath = storage_path('app/public/uploads/' . $fileName);

            // Upload QR code to AWS S3
            $path = Storage::disk('s3')->putFileAs(
                'venue-end-user-c-qr-codes/' . $venue->venueType->short_name . '/' . strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)),
                $temporaryFilePath,
                $fileName,
            );

            // update end user card with qr code s3 path and url
            $venue->qr_code_path = $path;
            $venue->save();
        }


        $pathReadable = Storage::disk('s3')->temporaryUrl($venue->qr_code_path, '+5 minutes');

        // return venue qr code s3 path and url
        return response()->json(['data' => $pathReadable], 200);
    }

}

function formatPlanDetails($plan) {
    $status = $plan['active_plan'] ? 'Active' : 'Non-active';
    $name = !empty($plan['name']) ? $plan['name'] : '-';
    $type = !empty($plan['plan_type']) ? $plan['plan_type'] : '-';

    return "$status / $name / $type";
}

function generateStringShortCode($providerName) {
    $prefix = strtoupper(substr($providerName, 0, 3));
    $randomNumbers = sprintf('%04d', mt_rand(0, 9999));
    $suffix = 'SCD';
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomChar = $alphabet[rand(0, strlen($alphabet) - 1)];

    return $prefix . $randomNumbers . $suffix . $randomChar;
}

function generateStringAppKey($providerName) {
    $prefix = strtoupper(substr($providerName, 0, 3));
    $randomNumbers = sprintf('%04d', mt_rand(0, 9999));
    $suffix = 'APP';
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomChar = $alphabet[rand(0, strlen($alphabet) - 1)];

    return $prefix . $randomNumbers . $suffix . $randomChar;
}


