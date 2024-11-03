<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AssignVtIdAndSubscriptionToRestaurant extends Migration
{
    public function up()
    {
        $restaurant = DB::table('restaurants')->where('id', 23)->first();

        if ($restaurant) {
            // Update the restaurant with VT ID
            DB::table('restaurants')
                ->where('id', 23)
                ->update(['vt_id' => 1]);

            // Get the advanced plan
            $advancedPlan = DB::table('vt_plans')
                ->where('slug', 'advanced_plan')
                ->first();

            if ($advancedPlan) {
                // Create a VT subscription for the restaurant
                DB::table('vt_subscriptions')->insert([
                    'restaurant_id' => 23,
                    'vt_plan_id' => $advancedPlan->id,
                    'status' => 'active',
                    'billing_cycle' => 'monthly', // You can change this to 'yearly' if needed
                    'current_period_start' => Carbon::now(),
                    'current_period_end' => Carbon::now()->addMonth(), // or addYear() for yearly billing
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }
    }

    public function down()
    {
        // Remove the VT subscription
        DB::table('vt_subscriptions')
            ->where('restaurant_id', 23)
            ->delete();

        // Remove the VT ID from the restaurant
        DB::table('restaurants')
            ->where('id', 23)
            ->update(['vt_id' => null]);
    }
}
