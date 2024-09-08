<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Affiliate;
use App\Models\City;
use App\Models\Country;
use App\Models\PotentialVenueLead;
use App\Models\Restaurant;
use App\Models\State;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VenueCustomizedExperience;
use GuzzleHttp\Client;

class MondayAutomationsService
{

    public function automateWaitlistCreation($waitlister): bool
    {

        $client = new Client();
        $baseUrl = env('MONDAY_COM_API_URL');
        $bearerToken = env('MONDAY_COM_API_TOKEN');

        // Set headers for the request
        $headers = [
            'Authorization' => 'Bearer ' . $bearerToken,
            'Content-Type' => 'application/json',
        ];

        // Define the GraphQL mutation
        $itemName = $waitlister->full_name ?? 'Not provided';
        $itemNameJSON = json_encode($itemName); // This will add the necessary quotes and escape characters
        $itemEmail = $waitlister->email;
        $itemCountryCode = $waitlister->country_code ?? '-';
        $itemVenueName = $waitlister->venue_name ?? '-';
        $itemPromoCode = $waitlister->promo_code ?? '-';

        $dataArrayContact = json_encode([
            "status" => ["label" => "Waitlist"],
            "email" => ["email" => $itemEmail, "text" => $itemEmail],
            "date" => now()->format('Y-m-d H:i:s'),
        ]);


        $columnValuesJSONContact = json_encode($dataArrayContact, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


        // create account first
        $mutationContact = <<<GRAPHQL
            mutation {
                create_item (
                board_id: 1345259347,
                 item_name: $itemNameJSON,
                 column_values: $columnValuesJSONContact
               ) {
                    id
                    name
                }
            }
            GRAPHQL;



        // Send the GraphQL mutation request
        $contactCreated = $client->post($baseUrl, [
            'headers' => $headers,
            'json' => [
                'query' => $mutationContact,
            ],
        ]);

        $jsonString = $contactCreated->getBody()->getContents();
        $responseArray = json_decode($jsonString, true); // Decoding as an associative array

        // Accessing the ID
        $itemId = $responseArray['data']['create_item']['id'];

        // Construct the column_values JSON string with variables
        $dataArray = json_encode([
            "status_11" => $itemEmail,
            "status_16" => $itemVenueName,
            "date4" => now()->format('Y-m-d H:i:s'),
            "status_161" => $itemCountryCode,
            "text" => $itemPromoCode,
            "connect_boards" => ["item_ids" => [$itemId]],
        ]);

        // Convert the array to a JSON string with escaped double quotes
        $columnValuesJSON = json_encode($dataArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Define the GraphQL mutation
        $mutation = <<<GRAPHQL
            mutation {
                create_item (
                board_id: 1333857918,
                 item_name: $itemNameJSON,
                 column_values: $columnValuesJSON
               ) {
                    id
                    name
                }
            }
            GRAPHQL;


        // Send the GraphQL mutation request
       $client->post($baseUrl, [
            'headers' => $headers,
            'json' => [
                'query' => $mutation,
            ],
        ]);

        return true;

    }


    public function automateContactSalesCreation($contactSale, $subscribe): bool
    {

        $client = new Client();
        $baseUrl = env('MONDAY_COM_API_URL');
        $bearerToken = env('MONDAY_COM_API_TOKEN');

        // Set headers for the request
        $headers = [
            'Authorization' => 'Bearer ' . $bearerToken,
            'Content-Type' => 'application/json',
        ];

        // Define the GraphQL mutation
        $itemName = $contactSale->first_name .' ' . $contactSale->last_name;
        $itemNameJSON = json_encode($itemName); // This will add the necessary quotes and escape characters
        $itemEmail = $contactSale->email;
        $itemMobile = $contactSale->mobile;
        $itemPostalCode = $contactSale->restaurant_zipcode;
        $itemVenueName = $contactSale->restaurant_name;
        $itemCity = $contactSale->restaurant_city;
        $itemState = $contactSale->restaurant_state;
        $itemCountry = $contactSale->restaurant_country;
        $itemInterestedIn = $contactSale->contact_reason;
        $itemNrEmployees = $contactSale->number_of_employees;
        $itemAnnualRevenue = $contactSale->annual_revenue;
        $itemWebsite = $contactSale->website;
        $itemIndustry = $contactSale->industry;
        $itemBusinessType = $contactSale->category;

        // from array with strings convert it to string with , separator
        $itemSocialMedia = $contactSale->social_media ? implode(',', json_decode($contactSale->social_media)) : '';
        $itemYearsInBusiness = $contactSale->years_in_business;
        $itemBiggestAdditionalChanel = $contactSale->biggest_additional_change;
        $itemHowDidYouHearAboutUS = $contactSale->how_did_you_hear_about_us === 'Other' ? $contactSale->how_did_you_hear_about_us_other : $contactSale->how_did_you_hear_about_us;
        $itemBusinessChallenges = $contactSale->business_challenge === 'Other' ? $contactSale->other_business_challenge : $contactSale->business_challenge;


        $dataArrayContact = json_encode([
            "status" => ["label" => "Contact Sale"],
            "email" => ["email" => $itemEmail, "text" => $itemEmail],
            "date" => now()->format('Y-m-d H:i:s'),
        ]);



        $columnValuesJSONContact = json_encode($dataArrayContact, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


        // create account first
        $mutationContact = <<<GRAPHQL
            mutation {
                create_item (
                board_id: 1345259347,
                 item_name: $itemNameJSON,
                 column_values: $columnValuesJSONContact
               ) {
                    id
                    name
                }
            }
            GRAPHQL;



        // Send the GraphQL mutation request
        $contactCreated = $client->post($baseUrl, [
            'headers' => $headers,
            'json' => [
                'query' => $mutationContact,
            ],
        ]);

        $jsonString = $contactCreated->getBody()->getContents();

        $responseArray = json_decode($jsonString, true); // Decoding as an associative array

        // Accessing the ID
        $itemId = $responseArray['data']['create_item']['id'];

        // Construct the column_values JSON string with variables
        $dataArray = json_encode([
            "status_16" => $itemVenueName,
            "status_11" => $itemEmail,
            "status_161" => $itemCountry,
            "text" => $itemState,
            "text8" => $itemCity,
            "text1" => $itemPostalCode,
            "text19" => 'VB Web Form',
            "text14" => $itemInterestedIn,
            "text74" => $itemMobile,
            "text5" => $itemNrEmployees,
            "text87" => $itemAnnualRevenue,
            "text0" => $itemWebsite,
            "text140" => $itemSocialMedia,
            "text2" => $itemBusinessChallenges,
            "text3" => $itemHowDidYouHearAboutUS,
            "text7" => $itemIndustry,
            "text6" => $itemBusinessType,
            // convert to string from integer
            "text82" => (string)$itemYearsInBusiness,
            "text4" => $itemBiggestAdditionalChanel,
            "date4" => now()->format('Y-m-d H:i:s'),
            "connect_boards" => ["item_ids" => [$itemId]],
        ]);

        // Convert the array to a JSON string with escaped double quotes
        $columnValuesJSON = json_encode($dataArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Define the GraphQL mutation
        $mutation = <<<GRAPHQL
            mutation {
                create_item (
                board_id: 1347375136,
                 item_name: $itemNameJSON,
                 column_values: $columnValuesJSON
               ) {
                    id
                    name
                }
            }
            GRAPHQL;


        // Send the GraphQL mutation request
        $client->post($baseUrl, [
            'headers' => $headers,
            'json' => [
                'query' => $mutation,
            ],
        ]);


        // if has subscribe new insert in monday
        if ($subscribe) {
            // Define the GraphQL mutation
            $itemNameS = $contactSale->first_name .' ' . $contactSale->last_name;
            $itemNameJSONS = json_encode($itemNameS); // This will add the necessary quotes and escape characters
            $itemRequesterEmailS = $contactSale->email;


            $dataArraySubscribe = json_encode([
                "date5" => now()->format('Y-m-d H:i:s') ,
                "email" => ["email" => $itemRequesterEmailS, "text" => $itemRequesterEmailS],
                "long_text" =>  'from_contact_sales'
            ]);


            $columnValuesJSONSubscribe = json_encode($dataArraySubscribe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $mutationSubscribe = <<<GRAPHQL
            mutation {
                create_item (
                board_id: 1371951278,
                 item_name: $itemNameJSONS,
                 column_values: $columnValuesJSONSubscribe
               ) {
                    id
                    name
                }
            }
            GRAPHQL;

            // Send the GraphQL mutation request
            $client->post($baseUrl, [
                'headers' => $headers,
                'json' => [
                    'query' => $mutationSubscribe,
                ],
            ]);

        }

        return true;

    }


    public function automateLeadCreation($potentialVenueLead, $subscribe): bool
    {

        $client = new Client();
        $baseUrl = env('MONDAY_COM_API_URL');
        $bearerToken = env('MONDAY_COM_API_TOKEN');

        // Set headers for the request
        $headers = [
            'Authorization' => 'Bearer ' . $bearerToken,
            'Content-Type' => 'application/json',
        ];

        // Define the GraphQL mutation
        $itemName = $potentialVenueLead->representative_first_name .' ' . $potentialVenueLead->representative_last_name;
        $itemNameJSON = json_encode($itemName); // This will add the necessary quotes and escape characters
        $itemEmail = $potentialVenueLead->email;

        $dataArrayContact = json_encode([
            "status" => ["label" => "Lead"],
            "email" => ["email" => $itemEmail, "text" => $itemEmail],
            "date" => now()->format('Y-m-d H:i:s'),
        ]);


        $columnValuesJSONContact = json_encode($dataArrayContact, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


        // create account first
        $mutationContact = <<<GRAPHQL
            mutation {
                create_item (
                board_id: 1345259347,
                 item_name: $itemNameJSON,
                 column_values: $columnValuesJSONContact
               ) {
                    id
                    name
                }
            }
            GRAPHQL;



        // Send the GraphQL mutation request
        $contactCreated = $client->post($baseUrl, [
            'headers' => $headers,
            'json' => [
                'query' => $mutationContact,
            ],
        ]);

        $jsonString = $contactCreated->getBody()->getContents();
        $responseArray = json_decode($jsonString, true); // Decoding as an associative array

        // Accessing the ID
        $itemId = $responseArray['data']['create_item']['id'];

        // Construct the column_values JSON string with variables
        $dataArray = json_encode([
            "lead_email" => ["email" => $itemEmail, "text" => $itemEmail],
            "short_text6" => $potentialVenueLead->from_chatbot ? 'chatbot' :  'web/get-started',
            "date" => now()->format('Y-m-d H:i:s'),
            "connect_boards" => ["item_ids" => [$itemId]],
        ]);

        // Convert the array to a JSON string with escaped double quotes
        $columnValuesJSON = json_encode($dataArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Define the GraphQL mutation
        $mutation = <<<GRAPHQL
            mutation {
                create_item (
                board_id: 1292621224,
                 item_name: $itemNameJSON,
                 column_values: $columnValuesJSON
               ) {
                    id
                    name
                }
            }
            GRAPHQL;


        // Send the GraphQL mutation request
        $client->post($baseUrl, [
            'headers' => $headers,
            'json' => [
                'query' => $mutation,
            ],
        ]);

        // if has subscribe new insert in monday
        if ($subscribe) {
            // Define the GraphQL mutation
            $itemNameS = $potentialVenueLead->representative_first_name .' ' . $potentialVenueLead->representative_last_name;
            $itemNameJSONS = json_encode($itemNameS); // This will add the necessary quotes and escape characters
            $itemRequesterEmailS = $potentialVenueLead->email;


            $dataArraySubscribe = json_encode([
                "date5" => now()->format('Y-m-d H:i:s') ,
                "email" => ["email" => $itemRequesterEmailS, "text" => $itemRequesterEmailS],
                "long_text" =>  'from_potential_venue_lead'
            ]);


            $columnValuesJSONSubscribe = json_encode($dataArraySubscribe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $mutationSubscribe = <<<GRAPHQL
            mutation {
                create_item (
                board_id: 1371951278,
                 item_name: $itemNameJSONS,
                 column_values: $columnValuesJSONSubscribe
               ) {
                    id
                    name
                }
            }
            GRAPHQL;

            // Send the GraphQL mutation request
            $client->post($baseUrl, [
                'headers' => $headers,
                'json' => [
                    'query' => $mutationSubscribe,
                ],
            ]);

        }

        return true;

    }

    public function privacyRightRequestCreation($privacyRightRequest): bool
    {

        $client = new Client();
        $baseUrl = env('MONDAY_COM_API_URL');
        $bearerToken = env('MONDAY_COM_API_TOKEN');

        // Set headers for the request
        $headers = [
            'Authorization' => 'Bearer ' . $bearerToken,
            'Content-Type' => 'application/json',
        ];

        // Define the GraphQL mutation
        $itemName = $privacyRightRequest->privacy_request;
        $itemNameJSON = json_encode($itemName); // This will add the necessary quotes and escape characters
        $itemRequesterEmail = $privacyRightRequest->request_contact_email;
        $itemRequesterName = $privacyRightRequest->request_contact_name;
        $itemRequesterPhone = $privacyRightRequest->request_contact_phone;


        $dataArrayContact = json_encode([
            "date1" => now()->format('Y-m-d H:i:s') ,
            "text8" =>  $itemRequesterEmail,
            "text2" =>  $itemRequesterName,
            "text" =>  $itemRequesterPhone,
        ]);


        $columnValuesJSONContact = json_encode($dataArrayContact, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


        // create account first
        $mutationContact = <<<GRAPHQL
            mutation {
                create_item (
                board_id: 1352415057,
                 item_name: $itemNameJSON,
                 column_values: $columnValuesJSONContact
               ) {
                    id
                    name
                }
            }
            GRAPHQL;

        // Send the GraphQL mutation request
       $client->post($baseUrl, [
            'headers' => $headers,
            'json' => [
                'query' => $mutationContact,
            ],
        ]);

        return true;

    }

    public function promoCodeCreation($promotionalCode): bool
    {

        $client = new Client();
        $baseUrl = env('MONDAY_COM_API_URL');
        $bearerToken = env('MONDAY_COM_API_TOKEN');

        // Set headers for the request
        $headers = [
            'Authorization' => 'Bearer ' . $bearerToken,
            'Content-Type' => 'application/json',
        ];
        // Define the GraphQL mutation
        $itemName = $promotionalCode->code;
        $itemNameJSON = json_encode($itemName); // This will add the necessary quotes and escape characters
        $itemTitle = $promotionalCode->title;
        $itemDescription = $promotionalCode->description;
        $itemCategoryDescription = $promotionalCode->category_description;
        $itemCategoryUsage = $promotionalCode->usage;
        $itemCategoryStartTime = $promotionalCode->start;
        $itemCategoryEndTime = $promotionalCode->end;
        $promotionalCodeType = json_decode($promotionalCode->promoCodeType->attributes, true);
        $nrOfMonths = $promotionalCodeType['nr_of_months'];
        $percentageDiscountValue = $promotionalCodeType['percentage_discount_value'];
        $planId = $promotionalCodeType['plan_id'];
        $typeP = $promotionalCode->promoCodeType->type  ;
        $fixedDiscountValue = $promotionalCodeType['fixed_discount_value'];


        $dataArrayPromoCode = json_encode([
            "date" => explode(' ', $itemCategoryStartTime)[0],
            "date8" => explode(' ', $itemCategoryEndTime)[0] ,
            "numbers9" =>  $nrOfMonths,
            "numbers5" =>  $percentageDiscountValue,
            "numbers2" =>  $fixedDiscountValue,
            "title" =>  $itemTitle,
            "description" =>  $itemDescription,
            "numbers" =>  $itemCategoryUsage,
            "dropdown5" =>  $planId ?: 'All plans',
            "dropdown" =>  $promotionalCode->for,
            "dropdown1" =>  $typeP,
            "text54" =>  $itemCategoryDescription,
            "created_by" => "vb_core",
            "status6" => "Active",
        ]);


        $columnValuesJSONPromo = json_encode($dataArrayPromoCode, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


        // create first
        $mutationPromo = <<<GRAPHQL
            mutation {
                create_item (
                board_id: 1310077899,
                 item_name: $itemNameJSON,
                 column_values: $columnValuesJSONPromo
               ) {
                    id
                    name
                }
            }
            GRAPHQL;

        // Send the GraphQL mutation request
        $client->post($baseUrl, [
            'headers' => $headers,
            'json' => [
                'query' => $mutationPromo,
            ],
        ]);

        return true;

    }

    public function onboardProcess($restaurantId, $step) {

        $client = new Client();
        $baseUrl = env('MONDAY_COM_API_URL');
        $bearerToken = env('MONDAY_COM_API_TOKEN');

        // Set headers for the request
        $headers = [
            'Authorization' => 'Bearer ' . $bearerToken,
            'Content-Type' => 'application/json',
            'API-Version'  => '2023-10'
        ];

        if ($step === 'business_details') {

            $venue = Restaurant::where('id', $restaurantId)->first();

            $address = Address::with('country', 'state', 'city')->find(($venue->addresses[0]->id));

            $countryName = Country::where('id', $address->country_id)->first()->name;
            $stateName = State::where('id', $address->state_id)->first()->name;
            $cityName = City::where('id', $address->city_id)->first()->name;

            $yearsInBusiness = $venue->years_in_business;
            $venueType =$venue->venueType->short_name;
            $venueIndustry = $venue->venueIndustry->name;
            $venueName = $venue->name;
            $venueCountry = $countryName;
            $venueCity = $stateName;
            $venueState = $cityName;
            $venueZipcode = $address->postcode;

            $user = User::where('id', $venue->user_id)->first();
            $userName = $user->name ?? $user->first_name . ' ' . $user->last_name;
            $userEmail = $user->email;

            // Define the GraphQL mutation
            $itemName = $userName;
            $itemNameJSON = json_encode($itemName); // This will add the necessary quotes and escape characters

            $itemVenueCreatedAt = $venue->created_at;

            $dataArrayBDetails = json_encode([
                "venue_name" => $venueName,
                "date34" => explode(' ', $itemVenueCreatedAt)[0],
                "country" =>  $venueCountry,
                "state" =>  $venueState,
                "city" =>  $venueCity,
                "postal_code" =>  $venueZipcode,
                "business_type" =>  $venueType,
                "years_in_business" =>  (string) $yearsInBusiness, // tostring
                "industry" =>  $venueIndustry,
                "email" =>  $userEmail,
                'status1' => 'Business Details'
            ]);


            $columnValuesJSONBDetails = json_encode($dataArrayBDetails, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


            // create first
            $mutationBDetails = <<<GRAPHQL
            mutation {
                create_item (
                board_id: 1345259346,
                 item_name: $itemNameJSON,
                 column_values: $columnValuesJSONBDetails
               ) {
                    id
                    name
                }
            }
            GRAPHQL;

            // Send the GraphQL mutation request
            $client->post($baseUrl, [
                'headers' => $headers,
                'json' => [
                    'query' => $mutationBDetails,
                ],
            ]);

            return true;
        }

        if ($step === 'interest_engagement') {
            $venue = Restaurant::where('id', $restaurantId)->first();
            $user = User::where('id', $venue->user_id)->first();
            $userEmail = $user->email;
            $venueCustomizeExperience = VenueCustomizedExperience::where('venue_id', $venue->id)->first();

            $venueEmployees = $venueCustomizeExperience->number_of_employees;
            $venueAnnualRevenue = $venueCustomizeExperience->annual_revenue;
            $venueHasWebsite = $venueCustomizeExperience->website === null ? $venueCustomizeExperience->website ? 'Yes' : 'No' : 'Not answered';

            $itemSocialMedia = $venueCustomizeExperience->social_media ? implode(',', json_decode($venueCustomizeExperience->social_media)) : '';
            $activHowdidYouhear = $venueCustomizeExperience->how_did_you_hear_about_us === 'Other' ? $venueCustomizeExperience->how_did_you_hear_about_us_other : $venueCustomizeExperience->how_did_you_hear_about_us;
            $activeBuinessChallenges = $venueCustomizeExperience->business_challenge === 'Other' ? $venueCustomizeExperience->other_business_challenge : $venueCustomizeExperience->business_challenge;
            $venueBiggestOperationChallenge = $venueCustomizeExperience->biggest_additional_change;
            $venueInterestedIn = $venueCustomizeExperience->contact_reason;

            $boardId = 1345259346;
            $columnId = "email";
            $columnValue = $userEmail;

            // Create the GraphQL query
                        $query = <<<GRAPHQL
            query {
              items_page_by_column_values (limit: 50, board_id: $boardId, columns: [{column_id: "$columnId", column_values: ["$columnValue"]}]) {
                cursor
                items {
                  id
                  name
                }
              }
            }
            GRAPHQL;


            // Send the GraphQL mutation request
            $itemReturn = $client->post($baseUrl, [
                'headers' => $headers,
                'json' => [
                    'query' => $query,
                ],
            ]);

            $jsonReturnString = $itemReturn->getBody()->getContents();
            $responseArray = json_decode($jsonReturnString, true); // Decoding as an associative array

            // Accessing the ID
            $itemId = $responseArray['data']['items_page_by_column_values']['items'][0]['id'];

            $dataArrayIE = json_encode([
                "status1" => "Interest & Engagement",
                "interested_in" => $venueInterestedIn,
                        "nr__of_employees" => $venueEmployees,
                        "annual_revenue"=> $venueAnnualRevenue,
                        "website"=> $venueHasWebsite,
                        "social_media"=> $itemSocialMedia,
                        "business_challenges"=> $activeBuinessChallenges,
                        "text"=> $activHowdidYouhear,
                        "text2"=> $venueBiggestOperationChallenge


            ]);


            $columnValuesJSONIE = json_encode($dataArrayIE, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // Construct the mutation
            $mutationUpdate = <<<GRAPHQL
                mutation {
                    change_multiple_column_values(item_id: $itemId, board_id: 1345259346, column_values: $columnValuesJSONIE
                    ) {
                        id
                    }
                }
                GRAPHQL;

            // Send the GraphQL mutation request
            $client->post($baseUrl, [
                'headers' => $headers,
                'json' => [
                    'query' => $mutationUpdate,
                ],
            ]);

            return true;

        }

        if ($step === 'subscription_plan_selection') {
            $venue = Restaurant::where('id', $restaurantId)->first();

            // get plan name from subscription that are active and related to venue
            $subscription = Subscription::with('pricingPlan')
                ->where('venue_id', $venue->id)
                ->where(function ($query) {
                    $query->where('status', 'active')
                        ->orWhere('status', 'trialing');
                })
                ->first();
            $planName = $subscription->pricingPlan->name;
            $user = User::where('id', $venue->user_id)->first();
            $boardId = 1345259346;
            $columnId = "email";
            $columnValue = $user->email;

            // Create the GraphQL query
            $query = <<<GRAPHQL
            query {
              items_page_by_column_values (limit: 50, board_id: $boardId, columns: [{column_id: "$columnId", column_values: ["$columnValue"]}]) {
                cursor
                items {
                  id
                  name
                }
              }
            }
            GRAPHQL;


            // Send the GraphQL mutation request
            $itemReturn = $client->post($baseUrl, [
                'headers' => $headers,
                'json' => [
                    'query' => $query,
                ],
            ]);

            $jsonReturnString = $itemReturn->getBody()->getContents();
            $responseArray = json_decode($jsonReturnString, true); // Decoding as an associative array

            // Accessing the ID
            $itemId = $responseArray['data']['items_page_by_column_values']['items'][0]['id'];

            $potentialLead = PotentialVenueLead::where('venue_id', $venue->id)->first();

            $dataArrayIE = json_encode([
                "status1" => "Onboarded Completed",
                 "date6" => explode(' ', $potentialLead->onboarded_completed_at)[0],
                //"date6" => "2024-01-01",
                "audience" => $planName
            ]);


            $columnValuesJSONIE = json_encode($dataArrayIE, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // Construct the mutation
            $mutationUpdate = <<<GRAPHQL
                mutation {
                    change_multiple_column_values(item_id: $itemId, board_id: 1345259346, column_values: $columnValuesJSONIE
                    ) {
                        id
                    }
                }
                GRAPHQL;

            // Send the GraphQL mutation request
            $client->post($baseUrl, [
                'headers' => $headers,
                'json' => [
                    'query' => $mutationUpdate,
                ],
            ]);

            return true;

        }
    }

    public function affiliateManage($affiliater, $action = 'create')
    {

        $client = new Client();
        $baseUrl = env('MONDAY_COM_API_URL');
        $bearerToken = env('MONDAY_COM_API_TOKEN');

        // Set headers for the request
        $headers = [
            'Authorization' => 'Bearer ' . $bearerToken,
            'Content-Type' => 'application/json',
            'API-Version' => '2023-10'
        ];


        if ($action === 'create') {

            $userEmail = $affiliater->user->email;
            $userName = $affiliater->first_name . ' ' . $affiliater->last_name;

            // Define the GraphQL mutation
            $itemName = $userName;
            $itemNameJSON = json_encode($itemName); // This will add the necessary quotes and escape characters



            $dataArrayBDetails = json_encode([
                "email" => ["email" => $userEmail, "text" => $userEmail],
                "date5" => explode(' ', $affiliater->created_at)[0],
                "status" =>  'Applied',
                "long_text" =>  $affiliater->affiliateType->name,
                "text" =>  'self',
                "text2" =>  $affiliater->country,
                "text6" =>  $affiliater->website,
            ]);


            $columnValuesJSONBDetails = json_encode($dataArrayBDetails, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // create first
            $mutationBDetails = <<<GRAPHQL
            mutation {
                create_item (
                board_id: 1363355384,
                 item_name: $itemNameJSON,
                 column_values: $columnValuesJSONBDetails
               ) {
                    id
                    name
                }
            }
            GRAPHQL;

            // Send the GraphQL mutation request
            $client->post($baseUrl, [
                'headers' => $headers,
                'json' => [
                    'query' => $mutationBDetails,
                ],
            ]);

            return true;
        }
            if ($action === 'update') {
                $boardId = 1363355384;
                $columnId = "email";
                $columnValue = $affiliater->user->email;

                // Create the GraphQL query
                $query = <<<GRAPHQL
            query {
              items_page_by_column_values (limit: 50, board_id: $boardId, columns: [{column_id: "$columnId", column_values: ["$columnValue"]}]) {
                cursor
                items {
                  id
                  name
                }
              }
            }
            GRAPHQL;


                // Send the GraphQL mutation request
                $itemReturn = $client->post($baseUrl, [
                    'headers' => $headers,
                    'json' => [
                        'query' => $query,
                    ],
                ]);

                $jsonReturnString = $itemReturn->getBody()->getContents();
                $responseArray = json_decode($jsonReturnString, true); // Decoding as an associative array

                // Accessing the ID
                $itemId = $responseArray['data']['items_page_by_column_values']['items'][0]['id'];

                $dataArrayIE = json_encode([
                    "status" => $affiliater->status === 'approved' ? 'Approved' : 'Declined',
                ]);


                $columnValuesJSONIE = json_encode($dataArrayIE, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                // Construct the mutation
                $mutationUpdate = <<<GRAPHQL
                mutation {
                    change_multiple_column_values(item_id: $itemId, board_id: 1363355384, column_values: $columnValuesJSONIE
                    ) {
                        id
                    }
                }
                GRAPHQL;

                // Send the GraphQL mutation request
                $client->post($baseUrl, [
                    'headers' => $headers,
                    'json' => [
                        'query' => $mutationUpdate,
                    ],
                ]);

                return true;

            }

        }

    public function contactFormSubmission($submission, $subscribe): bool
    {

        $client = new Client();
        $baseUrl = env('MONDAY_COM_API_URL');
        $bearerToken = env('MONDAY_COM_API_TOKEN');

        // Set headers for the request
        $headers = [
            'Authorization' => 'Bearer ' . $bearerToken,
            'Content-Type' => 'application/json',
        ];

        // Define the GraphQL mutation
        $itemName = $submission->first_name . ' ' . $submission->last_name;
        $itemNameJSON = json_encode($itemName); // This will add the necessary quotes and escape characters
        $itemRequesterEmail = $submission->email;
        $itemRequesterMessage = $submission->message;


        $dataArrayContact = json_encode([
            "date5" => now()->format('Y-m-d H:i:s') ,
            "email" => ["email" => $itemRequesterEmail, "text" => $itemRequesterEmail],
            "long_text" =>  $itemRequesterMessage
        ]);


        $columnValuesJSONContact = json_encode($dataArrayContact, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // create account first
        $mutationContact = <<<GRAPHQL
            mutation {
                create_item (
                board_id: 1371951258,
                 item_name: $itemNameJSON,
                 column_values: $columnValuesJSONContact
               ) {
                    id
                    name
                }
            }
            GRAPHQL;

        // Send the GraphQL mutation request
        $client->post($baseUrl, [
            'headers' => $headers,
            'json' => [
                'query' => $mutationContact,
            ],
        ]);

        // if has subscribe new insert in monday
        if ($subscribe) {
            // Define the GraphQL mutation
            $itemNameS = $submission->first_name . ' ' . $submission->last_name;
            $itemNameJSONS = json_encode($itemNameS); // This will add the necessary quotes and escape characters
            $itemRequesterEmailS = $submission->email;


            $dataArraySubscribe = json_encode([
                "date5" => now()->format('Y-m-d H:i:s') ,
                "email" => ["email" => $itemRequesterEmailS, "text" => $itemRequesterEmailS],
                "long_text" =>  'from_contact_form_submission'
            ]);


            $columnValuesJSONSubscribe = json_encode($dataArraySubscribe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $mutationSubscribe = <<<GRAPHQL
            mutation {
                create_item (
                board_id: 1371951278,
                 item_name: $itemNameJSONS,
                 column_values: $columnValuesJSONSubscribe
               ) {
                    id
                    name
                }
            }
            GRAPHQL;

            // Send the GraphQL mutation request
            $client->post($baseUrl, [
                'headers' => $headers,
                'json' => [
                    'query' => $mutationSubscribe,
                ],
            ]);

        }

        return true;

    }
}
