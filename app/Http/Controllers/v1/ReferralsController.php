<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\MarketingLink;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ReferralsController extends Controller
{
    public function getReferrals(Request $request): \Illuminate\Http\JsonResponse
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
            $restaurant = Restaurant::find($venue->id);
            if (!$restaurant->referral_code) {
                $restaurant->referral_code = Controller::generateReferralCode($restaurant->name);
                $restaurant->save();
            }

            // Check if a link exists for the referral code
            $link = MarketingLink::where('referral_code', $restaurant->referral_code)->where('type', 'referral')->first();

            if (!$link) {

                // Create a new link using Rebrandly API
                $response = Http::withHeaders([
                    'apikey' => env('REBRANDLY_API_KEY'),
                    'Content-Type' => 'application/json'
                ])->post(env('REBRANDLY_API_URL'), [
                    'title' => 'Referral Link from ' . $restaurant->name,
                    // add timestamp to the end of the referral code to make it unique
                    // generate random slashtag
                    'slashtag' => time() . '' . Controller::generateRandomString(8),
                    'destination' => 'https://venueboost.io/referrals?referral_code=' . $restaurant->referral_code,
                    'domain' => [
                        'id' => env('REBRANDLY_DOMAIN_ID'),
                        'fullName' => env('REBRANDLY_DOMAIN_NAME')
                    ]
                ]);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $link = new MarketingLink();
                    $link->venue_id = $restaurant->id;
                    $link->referral_code = $restaurant->referral_code;
                    $link->short_url = $responseData['shortUrl'];
                    $link->type = 'referral';
                    $link->save();
                } else {
                    return response()->json(['message' => 'Problem generating referral link'], 500);
                }

            }

            $referrals = DB::table('restaurant_referrals')
                ->join('restaurants', 'restaurants.id', '=', 'restaurant_referrals.register_id')
                ->join('potential_venue_leads', 'potential_venue_leads.id', '=', 'restaurant_referrals.potential_venue_lead_id')
                ->selectRaw("
                    restaurant_referrals.*,
                    restaurants.name as restaurant_name,
                    potential_venue_leads.referral_status,
                    CASE WHEN restaurant_referrals.is_used = 0 THEN 'Processing' ELSE 'Used' END as status,
                    5.00 as earnings
                ")
                ->where('restaurant_referrals.restaurant_id', $venue->id)
                ->orderBy('restaurant_referrals.used_time', 'DESC')
                ->get();


            return response()->json([
                'message' => 'Referrals retrieved successfully',
                'referral_code' => $restaurant->referral_code,
                'referrals' => $referrals,
                'referral_link' => $link->short_url,
                'use_referrals_for' => $restaurant->use_referrals_for,
            ], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function updateUserReferralCreditFor(Request $request): \Illuminate\Http\JsonResponse
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
            'option' => 'required|in:Balance,Credits'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $venue->use_referrals_for = $request->get('option') === 'Balance' ? 'wallet_balance' : 'feature_usage_credit';
        $venue->save();

        return response()->json(['message' => 'Referral credit usage updated successfully'], 200);

    }
}
