<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Address;
use App\Models\VenueIndustry;
use App\Models\VenueType;
use App\Services\VenueService;
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

/**
 * @OA\Info(
 *   title="Restaurant PreOnboarding API",
 *   version="1.0",
 *   description="This API allows use Restaurant PreOboarding Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="RestaurantPreOnboarding",
 *   description="Operations related to Restaurant PreOnboarding"
 * )
 */

class RestaurantPreOnboardingController extends Controller
{

    protected $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    const BASE_DIRECTORY = 'images/restaurant';

    /**
     * @OA\Post(
     *     path="/restaurants/register",
     *     summary="Create a new restaurant",
     *     tags={"RestaurantPreOnboarding"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="first_name",
     *                 type="string",
     *                 description="First name of the restaurant owner",
     *             ),
     *             @OA\Property(
     *                 property="last_name",
     *                 type="string",
     *                 description="Last name of the restaurant owner",
     *             ),
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 description="Email of the restaurant owner",
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 description="Password for the restaurant owner",
     *             ),
     *             @OA\Property(
     *                 property="restaurant_name",
     *                 type="string",
     *                 description="Name of the restaurant",
     *             ),
     *             @OA\Property(
     *                 property="restaurant_email",
     *                 type="string",
     *                 format="email",
     *                 description="Email of the restaurant",
     *             ),
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 description="Phone number of the restaurant",
     *             ),
     *             @OA\Property(
     *                 property="cuisine_types",
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                 ),
     *                 description="Array of cuisine type IDs",
     *             ),
     *             @OA\Property(
     *                 property="pricing",
     *                 type="string",
     *                 description="Pricing information",
     *             ),
     *             @OA\Property(
     *                 property="logo_image",
     *                 type="string",
     *                 description="Base64-encoded logo image",
     *             ),
     *             @OA\Property(
     *                 property="cover_image",
     *                 type="string",
     *                 nullable=true,
     *                 description="Base64-encoded cover image",
     *             ),
     *             @OA\Property(
     *                 property="amenities",
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                 ),
     *                 description="Array of amenity IDs",
     *             ),
     *             @OA\Property(
     *                 property="address_line1",
     *                 type="string",
     *                 description="Address line 1",
     *             ),
     *             @OA\Property(
     *                 property="address_line2",
     *                 type="string",
     *                 nullable=true,
     *                 description="Address line 2",
     *             ),
     *             @OA\Property(
     *                 property="state",
     *                 type="string",
     *                 description="State",
     *             ),
     *             @OA\Property(
     *                 property="city",
     *                 type="string",
     *                 description="City",
     *             ),
     *             @OA\Property(
     *                 property="postcode",
     *                 type="string",
     *                 description="Postcode",
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Restaurant created successfully",
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
            'logo_image' => 'required|string',
            'cover_image' => 'nullable|string',
            'venue_type' => 'required|string',
            'venue_industry' => 'required|string',
//            'amenities' => 'required|array',
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
            'state' => 'required|string',
            'city' => 'required|string',
            'postcode' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $restaurantOwnerData = $request->only('first_name', 'last_name', 'email', 'password');

            $contact_id = $request->input('contact_id');
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


            $newUserID = DB::table('users')->insertGetId([
                'name' =>  $restaurantOwnerData['first_name']. " " . $restaurantOwnerData['last_name'],
                'country_code' => 'US',
                'email' => $restaurantOwnerData['email'],
                'password' => Hash::make($restaurantOwnerData['password']),
            ]);

            $owner_user = User::where('id', $newUserID)->first();
            $venueType = VenueType::where('name', $venue_type)->first();
            $venueIndustry = VenueIndustry::where('name', $request->input('venue_industry'))->first();



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
            $restaurant->short_code = $this->generateStringShortCode($restaurant_name);
            $restaurant->app_key = $this->generateStringAppKey($restaurant_name);;
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
            $restaurant->status = 'not_verified';
            $restaurant->save();

            $existingContactAffiliate = DB::table('contact_sales')->where('id', $contact_id)->first();
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

            $created_at = Carbon::now();
            $expired_at = $created_at->addMinutes(5); // Add 5mins
            $serverName = 'VenueBoost';

            $data = [
                // 'iat' => $created_at->timestamp, // Issued at: time when the token was generated
                // 'nbf' => $created_at->timestamp, // Not before
                'iss' => $serverName, // Issuer
                'exp' => $expired_at->timestamp, // Expire,
                'id' => $restaurant->id,
            ];

            $jwt_token = JWT::encode($data, env('JWT_SECRET'), 'HS256');
            $email_verify_link = 'https://venueboost.io' . "/restaurants/verify-email/$jwt_token";
            Mail::to($owner_user->email)->send(new VendorVerifyEmail($restaurant->name, $email_verify_link));

            return response()->json(['message' => 'Venue is created successfully', 'restaurant' => $restaurant ], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/restaurants/update-restaurant",
     *     summary="Update restaurant profile",
     *     tags={"RestaurantPreOnboarding"},
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

        $validator = Validator::make($request->all(), [
            'restaurant_name' => 'nullable|string',
            'restaurant_email' => 'nullable|string|email',
            'phone' => 'nullable|string',
            'cuisine_types' => 'nullable|array',
            'pricing' => 'nullable|string',
            'logo_image' => 'nullable|string',
            'cover_image' => 'nullable|string',
            'amenities' => 'nullable|array',
            'address_line1' => 'nullable|string',
            'address_line2' => 'nullable|string',
            'state' => 'nullable|string',
            'city' => 'nullable|string',
            'postcode' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $user = auth()->user();
            $restaurant = $user->restaurant;
            if (!$restaurant) {
                return response()->json(['message' => 'Invalid profile data'], 400);
            }

            $restaurant_name = $request->input('restaurant_name');
            $restaurant_email = $request->input('restaurant_email');
            $phone = $request->input('phone');
            $website = $request->input('website');
            $pricing = $request->input('pricing');
            $capacity = $request->input('capacity');
            $cuisine_types = $request->input('cuisine_types');
            $amenities = $request->input('amenities');
            $restaurantAddressData = $request->only('address_line1', 'address_line2', 'state', 'city', 'postcode');

            $rest_addr = DB::table('restaurant_addresses')->where('restaurants_id', $restaurant->id)->first();
            if ($rest_addr) {
                Address::where('id', $rest_addr->address_id)->update($restaurantAddressData);
            }

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

            $restaurant =  Restaurant::where('id', $restaurant->id)->first();
            if ($logo_image_path) {
                if ($restaurant->logo) {
                    Storage::disk('public')->delete($restaurant->logo);
                }
                $restaurant->logo = $logo_image_path;
            }
            if ($cover_image_path) {
                if ($restaurant->cover && $restaurant->cover != '') {
                    Storage::disk('public')->delete($restaurant->cover);
                }
                $restaurant->cover = $cover_image_path;
            }

            $restaurant->name = $restaurant_name;
            $restaurant->phone_number = $phone;
            $restaurant->email = $restaurant_email;
            $restaurant->website = $website ?? "";
            $restaurant->pricing = $pricing;
            $restaurant->capacity = $capacity ?? 0;
            $restaurant->save();

            if ($cuisine_types) {
                DB::table('restaurant_cuisine_types')->where('restaurants_id', $restaurant->id)->delete();
                foreach ($cuisine_types as $key => $cuisine) {
                    DB::table('restaurant_cuisine_types')->insert([
                        'cuisine_types_id' => $cuisine,
                        'restaurants_id' => $restaurant->id
                    ]);
                }
            }
            if ($amenities) {
                DB::table('restaurant_amenities')->where('restaurants_id', $restaurant->id)->delete();
                foreach ($amenities as $key => $amenity) {
                    DB::table('restaurant_amenities')->insert([
                        'amenities_id' => $amenity,
                        'restaurants_id' => $restaurant->id
                    ]);
                }
            }

            return response()->json(['message' => 'Restaurant has been updated successfully', 'restaurant' => $restaurant ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/restaurants/resend-verify-email",
     *     summary="Resend verification email",
     *     tags={"RestaurantPreOnboarding"},
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
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/restaurants/verify-email",
     *     summary="Verify email",
     *     tags={"RestaurantPreOnboarding"},
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
            } catch (\Exception$e) {
                return response()->json(['message' => 'Invalid link1'], 400);
            }

            $restaurant = Restaurant::where('id', $id)->first();

            if (!$restaurant) {
                return response()->json(['message' => 'Invalid link'], 404);
            }

            if ($restaurant->status != 'not_verified') {
                return response()->json(['message' => 'Invalid link'], 400);
            }
            // TODO: after v1 testing change later to not_payment_setup

//            $restaurant->status = 'not_payment_setup';
            $restaurant->status = 'completed';
            $restaurant->save();



            $owner = User::where('id', $restaurant->user_id)->first();

            if($owner) {
                $owner->email_verified_at = date('Y-m-d H:i:s');
                $owner->save();
            }

            $created_at = Carbon::now();
            $expired_at = $created_at->addWeeks(1); // Add 1 week
            $serverName = 'VenueBoost';
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
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/restaurants/payment-methods",
     *     summary="Get payment methods",
     *     tags={"RestaurantPreOnboarding"},
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

            $stripe = new \Stripe\StripeClient(
                config('services.stripe.key')
            );
            $payment_methods = $stripe->paymentMethods->all([
                'customer' => $restaurant->stripe_customer_id,
                'type' => 'card',
            ]);

            return response()->json(['payment_methods' => $payment_methods], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/restaurants/add-card",
     *     summary="Add card as payment method",
     *     tags={"RestaurantPreOnboarding"},
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
            'cvc' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.key'));

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
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/restaurants/pay-with-card",
     *     summary="Pay with card",
     *     tags={"RestaurantPreOnboarding"},
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
    public function payWithCard(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|integer',
            'payment_method_id' => 'required|string',
            'plan_id' => 'required|integer',
            'mode' => 'required|in:monthly,yearly',
            'addons' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.key'));

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
            }
            else {
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
                'description' => 'Boost Subscription payment '.$plan->name.' '.$mode,
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
                'source' => 'stripe_card_'.$payment_method_id,
                'note' => 'Boost Subscription payment '.$plan->name.' '.$mode
            ]);

            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function generateStringShortCode($providerName) {
    $prefix = strtoupper(substr($providerName, 0, 3));
    $randomNumbers = sprintf('%04d', mt_rand(0, 9999));
    $suffix = 'SCD';
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomChar = $alphabet[rand(0, strlen($alphabet) - 1)];

    return $prefix . $randomNumbers . $suffix . $randomChar;
    }

    private function generateStringAppKey($providerName) {
    $prefix = strtoupper(substr($providerName, 0, 3));
    $randomNumbers = sprintf('%04d', mt_rand(0, 9999));
    $suffix = 'APP';
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomChar = $alphabet[rand(0, strlen($alphabet) - 1)];

    return $prefix . $randomNumbers . $suffix . $randomChar;
}

}


