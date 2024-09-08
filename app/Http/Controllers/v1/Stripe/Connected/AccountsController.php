<?php

namespace App\Http\Controllers\v1\Stripe\Connected;
use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class AccountsController extends Controller
{

    public function create(Request $request): \Illuminate\Http\JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $emailExists = \App\Models\Restaurant::where('email', $request->input('email'))->first();

        if (!$emailExists) {
            return response()->json(['error' => ['email' => ['Email not found']]], 422);
        }

        $isStripeAccountAlreadyCreated = $emailExists->stripe_acc_id;

        if ($isStripeAccountAlreadyCreated) {
            return response()->json(['error' => ['email' => ['Stripe account already created']]], 422);
        }

        $stripe = new \Stripe\StripeClient(
            config('services.stripe.key')
        );

       $newlyCreatedAccount = $stripe->accounts->create([
            'type' => 'custom',
            'country' => 'US',
            'email' => $request->input('email'),
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
        ]);

        $emailExists->stripe_acc_id = $newlyCreatedAccount->id;
        $emailExists->save();

        $response = [
            'success' => true,
            'data' => $newlyCreatedAccount
        ];

        return response()->json($response, 200);

    }

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

        $stripeAccountId = $venue->stripe_acc_id;

        if (!$stripeAccountId) {
            return response()->json(['error' => 'Stripe account not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'venue_stripe_data' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }


        $stripe = new \Stripe\StripeClient(
            config('services.stripe.key')
        );

        $venueStripeData = null;
        // Check if the 'venue_stripe_data' field exists in the request and is not empty
        if ($request->has('venue_stripe_data') && !empty($request->input('venue_stripe_data'))) {
            $venueStripeData = $request->input('venue_stripe_data');

        }

        $updateData = [];

        if (isset($venueStripeData['business_type'])) {
            $updateData['business_type'] = $venueStripeData['business_type'];
        }

        if (isset($venueStripeData['tos_acceptance']['date'])) {
            $timestamp = strtotime($venueStripeData['tos_acceptance']['date']);
            $updateData['tos_acceptance'] = [
                'date' => $timestamp,
                'ip' => $venueStripeData['tos_acceptance']['ip']
            ];
        }

        $updatedStripeAccount = $stripe->accounts->update(
            $stripeAccountId,
            $updateData
        );

        $formattedAccount = [
            'business_profile' => $updatedStripeAccount['business_profile'],
            'business_type' => $updatedStripeAccount['business_type'],
            'capabilities' => $updatedStripeAccount['capabilities'],
            'charges_enabled' => $updatedStripeAccount['charges_enabled'],
            'country' => $updatedStripeAccount['country'],
            'created' => $updatedStripeAccount['created'],
            'default_currency' => $updatedStripeAccount['default_currency'],
            'details_submitted' => $updatedStripeAccount['details_submitted'],
            'email' => $updatedStripeAccount['email'],
            'external_accounts' => $updatedStripeAccount['external_accounts'],
            'requirements' => [
                'currently_due' => $updatedStripeAccount['requirements']['currently_due'],
                'disabled_reason' => $updatedStripeAccount['requirements']['disabled_reason'],
                'eventually_due' => $updatedStripeAccount['requirements']['eventually_due'],
                'past_due' => $updatedStripeAccount['requirements']['past_due'],
                'pending_verification' => $updatedStripeAccount['requirements']['pending_verification'],
            ],
            'stripe_acc_id' => $venue->stripe_acc_id,
        ];

        $response = [
            'success' => true,
            'data' => $formattedAccount
        ];

        return response()->json($response, 200);

    }

    public function get(Request $request): \Illuminate\Http\JsonResponse
    {
        $stripe = new \Stripe\StripeClient(
            config('services.stripe.key')
        );

        $allConnectedAccounts = $stripe->accounts->all(['limit' => 1000]);
        $formattedResponse = [];

        foreach ($allConnectedAccounts['data'] as $account) {
            $restaurant = Restaurant::where('email', $account['email'])
                ->whereNotNull('stripe_acc_id')
                ->first();

            if ($restaurant) {
                $formattedAccount = [
                    'business_profile' => $account['business_profile'],
                    'business_type' => $account['business_type'],
                    'capabilities' => $account['capabilities'],
                    'charges_enabled' => $account['charges_enabled'],
                    'country' => $account['country'],
                    'created' => $account['created'],
                    'default_currency' => $account['default_currency'],
                    'details_submitted' => $account['details_submitted'],
                    'email' => $account['email'],
                    'external_accounts' => $account['external_accounts'],
                    'requirements' => [
                        'currently_due' => $account['requirements']['currently_due'],
                        'disabled_reason' => $account['requirements']['disabled_reason'],
                        'eventually_due' => $account['requirements']['eventually_due'],
                        'past_due' => $account['requirements']['past_due'],
                        'pending_verification' => $account['requirements']['pending_verification'],
                    ],
                    'stripe_acc_id' => $restaurant->stripe_acc_id,
                ];

                $formattedResponse[] = $formattedAccount;
            }
        }

        $response = [
            'success' => true,
            'data' => $formattedResponse
        ];

        return response()->json($response, 200);
    }

    public function getOne(Request $request): \Illuminate\Http\JsonResponse
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

        $stripeAccountId = $venue->stripe_acc_id;

        if (!$stripeAccountId) {
            return response()->json(['error' => 'Stripe account not found'], 404);
        }

        $stripe = new \Stripe\StripeClient(
            config('services.stripe.key')
        );

        $retrieveAccount =  $stripe->accounts->retrieve(
            $stripeAccountId,
            []
        );


        $formattedAccount = [
            'business_profile' => $retrieveAccount['business_profile'],
            'business_type' => $retrieveAccount['business_type'],
            'capabilities' => $retrieveAccount['capabilities'],
            'charges_enabled' => $retrieveAccount['charges_enabled'],
            'country' => $retrieveAccount['country'],
            'created' => $retrieveAccount['created'],
            'default_currency' => $retrieveAccount['default_currency'],
            'details_submitted' => $retrieveAccount['details_submitted'],
            'email' => $retrieveAccount['email'],
            'external_accounts' => $retrieveAccount['external_accounts'],
            'requirements' => [
                'currently_due' => $retrieveAccount['requirements']['currently_due'],
                'disabled_reason' => $retrieveAccount['requirements']['disabled_reason'],
                'eventually_due' => $retrieveAccount['requirements']['eventually_due'],
                'past_due' => $retrieveAccount['requirements']['past_due'],
                'pending_verification' => $retrieveAccount['requirements']['pending_verification'],
            ],
            'stripe_acc_id' => $venue->stripe_acc_id,
        ];

        $response = [
            'success' => true,
            'data' => $formattedAccount
        ];

        return response()->json($response, 200);


    }
}
