<?php

namespace App\Services;

use App\Models\AppConfiguration;
use App\Models\Employee;
use App\Models\Guest;
use App\Models\RentalUnit;
use App\Models\User;
use App\Models\VbApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Models\Restaurant;


class VenueService
{

    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

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
        if (!auth()->user() || !$this->userService->isOwner(auth()->user())) {
            return response()->json(['error' => 'User not authorized for this action'], 403);
        }

        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        // URL decode the venue short code
        $decodedVenueCode = urldecode($apiCallVenueShortCode);

        // Add logging to debug
        Log::info('Searching for venue:', [
            'raw_code' => $apiCallVenueShortCode,
            'decoded_code' => $decodedVenueCode,
            'available_venues' => auth()->user()->restaurants->pluck('short_code')
        ]);

        $venue = auth()->user()->restaurants->where('short_code', $decodedVenueCode)->first();
        if (!$venue) {
            return response()->json([
                'error' => 'Venue not found',
                'searched_code' => $decodedVenueCode
            ], 404);
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


    /**
     * Get a venue by its app code.
     *
     * @param string|null $appCode
     * @return Restaurant
     * @throws ValidationException
     * @throws \Exception
     */
    public function getVenueByAppCode(?string $appKey): Restaurant
    {
        if (!$appKey) {
            throw ValidationException::withMessages([
                'venue_app_key' => ['Venue app key is required'],
            ]);
        }

        $venue = Restaurant::where('app_key', $appKey)->first();

        if (!$venue) {
            throw new \Exception('Venue not found', 404);
        }

        return $venue;
    }

    /**
     * Get the app configuration for a venue and app source.
     *
     * @param Restaurant $venue
     * @param string $appSource
     * @return array
     * @throws \Exception
     */
    public function getSimplifiedAppConfiguration(Restaurant $venue, string $appSource): array
    {
        $vbApp = VbApp::where('slug', $appSource)->first();

        if (!$vbApp) {
            throw new \Exception('Invalid app source', 400);
        }

        $appConfiguration = AppConfiguration::where('venue_id', $venue->id)
            ->where('vb_app_id', $vbApp->id)
            ->first();

        if (!$appConfiguration) {
            throw new \Exception('App configuration not found for this venue and app source', 404);
        }

        return [
            'app_name' => $appConfiguration->app_name,
            'main_color' => $appConfiguration->main_color,
            'button_color' => $appConfiguration->button_color,
            'logo_url' => $appConfiguration->logo_url,
        ];
    }

    public function employee(): Employee|JsonResponse
    {
        if (!auth()->user()) {
            return response()->json(['error' => 'No authenticated user to make this API call'], 401);
        }

        $userId = auth()->user()->id;

       $employee = Employee::where('user_id', $userId)->first();

        if (!$employee) {
            return response()->json(['error' => 'Employeee not found'], 404);
        }

        return $employee;



    }

}
