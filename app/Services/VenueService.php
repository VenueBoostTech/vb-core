<?php

namespace App\Services;

use App\Models\RentalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VenueService
{

    public function manageGeneralVenueAddonsAndPlan($restaurantId) {
        // Insert into restaurant_addons
        $addons = [1, 7, 8, 9, 10, 12];
        foreach ($addons as $addon) {
            DB::table('restaurant_addons')->insert([
                'restaurants_id' => $restaurantId,
                'addons_id' => $addon,
                'addon_plan_type' => 'monthly',
            ]);
        }

        // Update restaurants/venues pricing plans
        DB::table('restaurants')->where('id', $restaurantId)->update([
            'plan_id' => 2,
            'plan_type' => 'monthly',
            'active_plan' => 1,
        ]);
    }

    public function manageSportsAndEntertainmentVenueAddonsAndPlan($restaurantId) {
        // Insert into restaurant_addons
        $addons = [6, 11, 13, 4, 5, 10];
        foreach ($addons as $addon) {
            DB::table('restaurant_addons')->insert([
                'restaurants_id' => $restaurantId,
                'addons_id' => $addon,
                'addon_plan_type' => 'monthly',
            ]);
        }

        $existingSubFeatureOneTimeOp = DB::table('sub_features')->where('link', 'reservations')->first();
        if ($existingSubFeatureOneTimeOp) {
            $existingSubFeatureOneTimeOpId = $existingSubFeatureOneTimeOp->id;

            $existingSubFeatureOneTimeOpWithPlan = DB::table('plan_sub_features')
                ->where('plan_id', 4)
                ->where('sub_feature_id', $existingSubFeatureOneTimeOpId)
                ->first();

            if (!$existingSubFeatureOneTimeOpWithPlan) {
                DB::table('plan_sub_features')->insert([
                    [
                        'plan_id' => 4,
                        'sub_feature_id' => $existingSubFeatureOneTimeOpId,
                    ]
                ]);
            }
        }

        // Update restaurants pricing plans
        DB::table('restaurants')->where('id', $restaurantId)->update([
            'plan_id' => 4,
            'plan_type' => 'monthly',
            'active_plan' => 1,
        ]);
    }

    public function manageAccommodationVenueAddonsAndPlan($restaurantId) {
        // Insert into restaurant_addons
        $addons = [14, 2, 3, 4, 5, 10];
        foreach ($addons as $addon) {
            DB::table('restaurant_addons')->insert([
                'restaurants_id' => $restaurantId,
                'addons_id' => $addon,
                'addon_plan_type' => 'monthly',
            ]);
        }

        $existingSubFeatureOneTimeOp = DB::table('sub_features')->where('link', 'reservations')->first();
        if ($existingSubFeatureOneTimeOp) {
            $existingSubFeatureOneTimeOpId = $existingSubFeatureOneTimeOp->id;

            $existingSubFeatureOneTimeOpWithPlan = DB::table('plan_sub_features')
                ->where('plan_id', 6)
                ->where('sub_feature_id', $existingSubFeatureOneTimeOpId)
                ->first();

            if (!$existingSubFeatureOneTimeOpWithPlan) {
                DB::table('plan_sub_features')->insert([
                    [
                        'plan_id' => 6,
                        'sub_feature_id' => $existingSubFeatureOneTimeOpId,
                    ]
                ]);
            }
        }

        // Update restaurants pricing plans
        DB::table('restaurants')->where('id', $restaurantId)->update([
            'plan_id' => 6,
            'plan_type' => 'monthly',
            'active_plan' => 1,
        ]);
    }

    public function checkPromotionalCode($venue)
    {
        // Logic to check promotional code
    }

    public function checkReferral($venue)
    {
        // Logic to check referral

        // check if venue is register_id in restaurant_referrals
        // get referee_id from restaurant_referrals
        // Check wallet configuration settings for referee_id and deposit referral amount in terms of feature credits or wallet balance
        // based on subscription plan of venue already subscribed deposit referral amount in terms of feature credits or wallet balance

        // trigger automation with emails
    }

    public function checkAffiliate($venue)
    {
        // TODO check for affiliate and update earnings table of affiliate
        // Logic to check affiliate

        // check if venue id is on venue_affiliates
        // get affiliate id from venue_affiliates
        // check affilaite plan and deposit affiliate amount in terms of feature credits or wallet balance
    }

    public function afterSubscription($venue)
    {
        $this->checkPromotionalCode($venue);
        $this->checkReferral($venue);
        $this->checkAffiliate($venue);
    }

    public function adminAuthCheck()
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

        return $venue;
    }

    public function getVenueIdsForUser($userId): array
    {
        $user = User::find($userId);

        if (!$user) {
            return [];
        }

        return $user->restaurants()->pluck('id')->toArray();
    }

    public function getReadableBedResult($rentalUnitId): array
    {
        // Retrieve rental unit with rooms and beds
        $rooms = RentalUnit::with('rooms.beds')->find($rentalUnitId)->rooms;

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

        return $readableBedResult;
    }
}
