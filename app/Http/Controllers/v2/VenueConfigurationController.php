<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\VenueConfiguration;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VenueConfigurationController extends Controller
{

    public function get(): JsonResponse
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

            $languages = [];
            // prepare it in title value pair
            // check if venue has address and from address get country_id and from country id get main_language and other languages

            $rest_addr = DB::table('restaurant_addresses')->where('restaurants_id', $venue->id)->first();
            if ($rest_addr) {
                $addressRetrieve = Address::where('id', $rest_addr->address_id)->first();

                 $country_id = $addressRetrieve->country_id;
                 if (!$country_id) {
                     $country_id = 1;
                 }

                $country = DB::table('countries')->where('id', $country_id)->first();
                $main_language = $country->main_language;
                $other_languages = $country->other_languages;

                // push main language to languages array with key value pair
                if($main_language){
                    $languages[] = ['title' => $main_language, 'value' => $main_language];
                }
                if($other_languages){
                    // push other languages to languages array with key value pair
                    $other_languages = explode(',', $other_languages);
                    foreach($other_languages as $other_language){
                        $languages[] = ['title' => $other_language, 'value' => $other_language];
                    }
                }
            } else {
                $languages[] = ['title' => 'English', 'value' => 'English'];
            }


            // check if venue has venue configuration
            // if not return properties null
            // if yes return properties

            $allowedVenueIds = [11, 1, 12, 13, 14, 15, 23, 25, 26];
            // for these venues if venue configuration please create one
            $venueId = $venue->id;
            if (in_array($venueId, $allowedVenueIds)) {

                $venueConfiguration = VenueConfiguration::where('venue_id', $venue->id)->first();
                if (!$venueConfiguration) {
                    $venueConfiguration = VenueConfiguration::create([
                        'venue_id' => $venue->id,
                        'email_language' => 'English',
                        'stripe_connected_acc_id' => null,
                        'onboarding_completed' => true,
                        'connected_account_created_at' => null,
                        'connected_account_updated_at' => null,
                        'more_information_required' => false
                    ]);
                }
            } else {
                $venueConfiguration = VenueConfiguration::where('venue_id', $venue->id)->first();
            }

            if (!$venueConfiguration) {
                $properties = new \stdClass();
                $properties->email_language = null;
                $properties->can_process_transactions = false;
                $properties->onboarding_completed = false;

                return response()->json([
                    'message' => 'Venue configuration not found',
                    'properties' => $properties,
                    'languages' => $languages
                ], 200);
            }


            $properties = new \stdClass();
            $properties->email_language = $venueConfiguration->email_language;
            $properties->can_process_transactions = $venue->can_process_transactions;
            $properties->onboarding_completed = $venueConfiguration->onboarding_completed;


            return response()->json([
                'message' => 'Venue configuration retrieved successfully',
                'properties' => $properties,
                'languages' => $languages
            ], 200);

        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function cuVenueConfiguration(Request $request): \Illuminate\Http\JsonResponse
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

        // Validation
        $validator = Validator::make($request->all(), [
            'can_process_transactions' => 'nullable | boolean',
            "email_language" => 'nullable|string',
        ]);



        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Fetch existing vendorConfiguration for the venue or create new
        $vendorConfiguration = $venue->venueConfiguration()->firstOrCreate(
            ['venue_id' => $venue->id],
        );

        // Update the data
        $vendorConfiguration->email_language = $request->email_language ?? $vendorConfiguration->email_language;

        $vendorConfiguration->save();

        // Update the venue can_process_transactions
        $venue->can_process_transactions = $request->can_process_transactions ?? $venue->can_process_transactions;
        $venue->save();

        return response()->json(['message' => 'Venue configuration completed successfully']);
    }

    public function createConnectedAccount(Request $request): JsonResponse
    {

        try {
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

            $stripe = new \Stripe\StripeClient (
                config('services.stripe.key')
            );

            $isStripeAccountAlreadyCreated = $venue->stripe_acc_id;

            if ($isStripeAccountAlreadyCreated) {
                return response()->json(['error' => ['email' => ['Stripe account already created']]], 422);
            }

            $venueCountryCode = 'US';
            $rest_addr = DB::table('restaurant_addresses')->where('restaurants_id', $venue->id)->first();
            if ($rest_addr) {
                $addressRetrieve = Address::where('id', $rest_addr->address_id)->first();

                $country_id = $addressRetrieve->country_id;
                if ($country_id === 1) {
                    $venueCountryCode = 'US';
                } elseif ($country_id === 4) {
                    $venueCountryCode = 'CA';
                }
            }

            $newlyCreatedAccount = $stripe->accounts->create([
                'type' => 'express',
                'country' => $venueCountryCode,
                'email' => $venue->email,
                'business_type' => 'company',
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ]);

            // Fetch existing vendorConfiguration for the venue or create new
            $vendorConfiguration = $venue->venueConfiguration()->firstOrCreate(
                ['venue_id' => $venue->id],
            );

            // Update the data
            $vendorConfiguration->stripe_connected_acc_id = $vendorConfiguration->stripe_connected_acc_id ?? $newlyCreatedAccount->id;
            $vendorConfiguration->onboarding_completed = true;
            $vendorConfiguration->connected_account_created_at = $vendorConfiguration->connected_account_created_at ?? Carbon::now();
            $vendorConfiguration->connected_account_updated_at = $vendorConfiguration->connected_account_updated_at ?? Carbon::now();
            $vendorConfiguration->save();

            $venue->stripe_acc_id = $newlyCreatedAccount->id;
            $venue->save();

            $accountURL = $stripe->accountLinks->create([
                'account' => $newlyCreatedAccount->id,
                'refresh_url' => config('services.stripe.admin_redirect_url').'/'.$apiCallVenueShortCode. '/admin/settings/space?refresh=true',
                'return_url' => config('services.stripe.admin_redirect_url').'/'.$apiCallVenueShortCode. '/admin/settings/space?success=true',
                'type' => 'account_onboarding',
            ]);

            return response()->json([
                'url' => $accountURL->url,
            ], 200);



        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
