<?php

namespace Database\Seeders;

use App\Models\Addon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AddonsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Addons Creation
        $addons = [
            [
                'name' => 'VB Loyalty',
                'category' => 'food_drinks',
                'description' => 'VenueBoost Loyalty Program',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
            ],
            [
                'name' => 'VB Loyalty',
                'category' => 'hotel_resort',
                'description' => 'VenueBoost Loyalty Program',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
            ],
            [
                'name' => 'Restaurants',
                'category' => 'hotel_resort',
                'description' => 'Hotel Restaurant Facility',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
            ],
            [
                'name' => 'Gym',
                'category' => 'hotel_resort',
                'description' => 'Hotel Gym Facility',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
            ],
            [
                'name' => 'Events Hall',
                'category' => 'hotel_resort',
                'description' => 'Hotel Events Hall Facility',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
            ],
            [
                'name' => 'VB Loyalty',
                'category' => 'sport_entertainment',
                'description' => 'VenueBoost Loyalty Program',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
            ],
            [
                'name' => 'Delivery',
                'category' => 'food_drinks',
                'description' => 'Accept Delivery Orders',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
            ],
            [
                'name' => 'Pickup',
                'category' => 'food_drinks',
                'description' => 'Accept Pickup Orders',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
            ],
            [
                'name' => 'Orders & Pay',
                'category' => 'food_drinks',
                'description' => 'Generate Payment Links for your guests to pay faster',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
            ],
            [
                'name' => 'POS & Inventory',
                'category' => 'food_drinks',
                'description' => 'Integration with your own POS',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
            ],
            [
                'name' => 'Golf',
                'category' => 'sport_entertainment',
                'description' => 'Manage your golf venue fields',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
            ],
            [
                'name' => 'Venue White Label',
                'category' => 'sport_entertainment',
                'description' => 'Manage your venue profile on VB Web',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
            ],
            [
                'name' => 'Venue White Label',
                'category' => 'food_drinks',
                'description' => 'Manage your venue profile on VB Web',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
            ],
            [
                'name' => 'Venue White Label',
                'category' => 'hotel_resort',
                'description' => 'Manage your venue profile on VB Web',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
            ],
        ];



        foreach ($addons as $addon) {
            Addon::create($addon);
        }

        // Addon Features
        $addonFeatureID1 = DB::table('addon_features')->insertGetId([
            'name' => 'Loyalty',
            'link' => 'loyalty',
        ]);

        $addonSubFeatureID11 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID1,
            'name' => 'Programs',
            'link' => 'programs',
        ]);

        $addonSubFeatureID12 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID1,
            'name' => 'Guests Enrolled',
            'link' => 'guests-enrolled',
        ]);

        // Addon Features
        $addonFeatureID2 = DB::table('addon_features')->insertGetId([
            'name' => 'Loyalty',
            'link' => 'loyalty',
        ]);

        $addonSubFeatureID21 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID2,
            'name' => 'Programs',
            'link' => 'programs',
        ]);

        $addonSubFeatureID22 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID2,
            'name' => 'Guests Enrolled',
            'link' => 'guests-enrolled',
        ]);


        $addonFeatureIDX1 = DB::table('addon_features')->insertGetId([
            'name' => 'Restaurant',
            'link' => 'hotel-restaurant',
        ]);

        $addonSubFeatureIDX11 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureIDX1,
            'name' => 'Manage',
            'link' => 'rest-manage',
        ]);

        $addonFeatureID3 = DB::table('addon_features')->insertGetId([
            'name' => 'Menu',
            'link' => 'menu',
        ]);

        $addonSubFeatureID31 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID3,
            'name' => 'Menu',
            'link' => 'menu',
        ]);

        $addonSubFeatureID32 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID3,
            'name' => 'Items',
            'link' => 'items',
        ]);

        $addonSubFeatureID33 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID3,
            'name' => 'Categories',
            'link' => 'categories',
        ]);

        $addonFeatureID7 = DB::table('addon_features')->insertGetId([
            'name' => 'Reservations',
            'link' => 'reservation',
        ]);

        $addonSubFeatureID71 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID7,
            'name' => 'Dining Space Locations',
            'link' => 'dining-space-locations',
        ]);

        $addonSubFeatureID72 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID7,
            'name' => 'Waitlist Management',
            'link' => 'waitlist',
        ]);

        $addonSubFeatureID73 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID7,
            'name' => 'Seating Arrangement',
            'link' => 'seating-arrangement',
        ]);

        $addonFeatureID8 = DB::table('addon_features')->insertGetId([
            'name' => 'Reporting',
            'link' => 'reporting',
        ]);

        $addonSubFeatureID81 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID8,
            'name' => 'Orders',
            'link' => 'orders',
        ]);

        $addonSubFeatureID82 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID8,
            'name' => 'Waitlist',
            'link' => 'waitlist-reporting',
        ]);

        $addonSubFeatureID83 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID8,
            'name' => 'Tables',
            'link' => 'tables-reporting',
        ]);

        $addonSubFeatureID84 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID8,
            'name' => 'Advanced',
            'link' => 'advanced',
        ]);

        $addonFeatureID9 = DB::table('addon_features')->insertGetId([
            'name' => 'Tables',
            'link' => 'table',
        ]);

        $addonSubFeatureID91 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID9,
            'name' => 'Tables Management',
            'link' => 'tables',
        ]);

        $addonSubFeatureID92 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID9,
            'name' => 'Available By Date',
            'link' => 'available',
        ]);

        $addonFeatureIDAD1 = DB::table('addon_features')->insertGetId([
            'name' => 'Delivery',
            'link' => 'delivery',
        ]);

        $addonSubFeatureIDAD11 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureIDAD1,
            'name' => 'Orders',
            'link' => 'delivery-orders',
        ]);

        $addonFeatureIDAD2 = DB::table('addon_features')->insertGetId([
            'name' => 'Pickup',
            'link' => 'pickup',
        ]);

        $addonSubFeatureIDAD21 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureIDAD2,
            'name' => 'Orders',
            'link' => 'pickup-orders',
        ]);

        $addonFeatureIDAD3 = DB::table('addon_features')->insertGetId([
            'name' => 'Order & Pay',
            'link' => 'order-and-pay',
        ]);

        $addonSubFeatureIDAD31 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureIDAD3,
            'name' => 'Orders',
            'link' => 'order-and-pay-orders',
        ]);
        $addonSubFeatureIDAD32 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureIDAD3,
            'name' => 'Payment Links',
            'link' => 'payment-links',
        ]);

        $addonFeatureIDAD4 = DB::table('addon_features')->insertGetId([
            'name' => 'Integrations',
            'link' => 'Integrations',
        ]);

        $addonSubFeatureIDAD41 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureIDAD4,
            'name' => 'Delivery & Pickup',
            'link' => 'delivery-and-pickup',
        ]);

        $addonSubFeatureIDAD42 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureIDAD4,
            'name' => 'Inventory',
            'link' => 'inventory',
        ]);

        $addonFeatureID4 = DB::table('addon_features')->insertGetId([
            'name' => 'Gym',
            'link' => 'hotel-gym',
        ]);

        $addonSubFeatureID41 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID4,
            'name' => 'Gyms List',
            'link' => 'gyms-list',
        ]);

        $addonSubFeatureID42 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID4,
            'name' => 'Gyms Availability',
            'link' => 'gyms-availability',
        ]);

        $addonFeatureID5 = DB::table('addon_features')->insertGetId([
            'name' => 'Events Hall',
            'link' => 'events-hall',
        ]);


        $addonSubFeatureID51 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID5,
            'name' => 'Halls List',
            'link' => 'halls-list',
        ]);

        $addonSubFeatureID52 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID5,
            'name' => 'Hall Availability',
            'link' => 'halls-availability',
        ]);

        $addonFeatureID6 = DB::table('addon_features')->insertGetId([
            'name' => 'Golf',
            'link' => 'golf',
        ]);

        $addonSubFeatureID61 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID6,
            'name' => 'Fields',
            'link' => 'fields',
        ]);

        $addonSubFeatureID62 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureID6,
            'name' => 'Availability',
            'link' => 'fields-availability',
        ]);

        $addonFeatureIDLL1 = DB::table('addon_features')->insertGetId([
            'name' => 'Loyalty',
            'link' => 'loyalty',
        ]);

        $addonSubFeatureIDLL111 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureIDLL1,
            'name' => 'Programs',
            'link' => 'programs',
        ]);

        $addonSubFeatureIDLL112 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureIDLL1,
            'name' => 'Guests Enrolled',
            'link' => 'guests-enrolled',
        ]);

        $addonFeatureIDLL2 = DB::table('addon_features')->insertGetId([
            'name' => 'Account',
            'link' => 'account',
        ]);

        $addonSubFeatureIDLL21 = DB::table('addon_sub_features')->insertGetId([
            'addon_feature_id' => $addonFeatureIDLL2,
            'name' => 'VB Web Profile',
            'link' => 'web',
        ]);


        // Restaurant Addon Features / Sub Features Connect
        DB::table('addon_feature_connections')->insert([
            [
                'addon_id' => 1,
                'addon_feature_id' => $addonFeatureID1,
            ],
            [
                'addon_id' => 2,
                'addon_feature_id' => $addonFeatureID2,
            ],
            [
                'addon_id' => 3,
                'addon_feature_id' => $addonFeatureID3,
            ],
            [
                'addon_id' => 3,
                'addon_feature_id' => $addonFeatureIDX1,
            ],
            [
                'addon_id' => 3,
                'addon_feature_id' => $addonFeatureID7,
            ],
            [
                'addon_id' => 3,
                'addon_feature_id' => $addonFeatureID8,
            ],
            [
                'addon_id' => 3,
                'addon_feature_id' => $addonFeatureID9,
            ],
            [
                'addon_id' => 4,
                'addon_feature_id' => $addonFeatureID4,
            ],
            [
                'addon_id' => 5,
                'addon_feature_id' => $addonFeatureID5,
            ],
            [
                'addon_id' => 11,
                'addon_feature_id' => $addonFeatureID6,
            ],
            [
                'addon_id' => 7,
                'addon_feature_id' => $addonFeatureIDAD1,
            ],
            [
                'addon_id' => 8,
                'addon_feature_id' => $addonFeatureIDAD2,
            ],
            [
                'addon_id' => 9,
                'addon_feature_id' => $addonFeatureIDAD3,
            ],
            [
                'addon_id' => 10,
                'addon_feature_id' => $addonFeatureIDAD4,
            ],
            [
                'addon_id' => 6,
                'addon_feature_id' => $addonFeatureIDLL1,
            ],
            [
                'addon_id' => 12,
                'addon_feature_id' => $addonFeatureIDLL2,
            ],
            [
                'addon_id' => 13,
                'addon_feature_id' => $addonFeatureIDLL2,
            ],
            [
                'addon_id' => 14,
                'addon_feature_id' => $addonFeatureIDLL2,
            ],

        ]);


        DB::table('addon_sub_feature_connections')->insert([
            [
                'addon_id' => 1,
                'addon_sub_feature_id' => $addonSubFeatureID11,
            ],
            [
                'addon_id' => 1,
                'addon_sub_feature_id' => $addonSubFeatureID12,
            ],
            [
                'addon_id' => 2,
                'addon_sub_feature_id' => $addonSubFeatureID21,
            ],
            [
                'addon_id' => 2,
                'addon_sub_feature_id' => $addonSubFeatureID22,
            ],
            [
                'addon_id' => 3,
                'addon_sub_feature_id' => $addonSubFeatureIDX11,
            ],
            [
                'addon_id' => 3,
                'addon_sub_feature_id' => $addonSubFeatureID31,
            ],
            [
                'addon_id' => 3,
                'addon_sub_feature_id' => $addonSubFeatureID32,
            ],
            [
                'addon_id' => 3,
                'addon_sub_feature_id' => $addonSubFeatureID33,
            ],
            [
                'addon_id' => 3,
                'addon_sub_feature_id' => $addonSubFeatureID71,
            ],
            [
                'addon_id' => 3,
                'addon_sub_feature_id' => $addonSubFeatureID72,
            ],
            [
                'addon_id' => 3,
                'addon_sub_feature_id' => $addonSubFeatureID73,
            ],
            [
                'addon_id' => 3,
                'addon_sub_feature_id' => $addonSubFeatureID81,
            ],
            [
                'addon_id' => 3,
                'addon_sub_feature_id' => $addonSubFeatureID82,
            ],
            [
                'addon_id' => 3,
                'addon_sub_feature_id' => $addonSubFeatureID83,
            ],
            [
                'addon_id' => 3,
                'addon_sub_feature_id' => $addonSubFeatureID84,
            ],
            [
                'addon_id' => 3,
                'addon_sub_feature_id' => $addonSubFeatureID91,
            ],
            [
                'addon_id' => 3,
                'addon_sub_feature_id' => $addonSubFeatureID92,
            ],
            [
                'addon_id' => 3,
                'addon_sub_feature_id' => $addonSubFeatureIDAD41,
            ],
            [
                'addon_id' => 4,
                'addon_sub_feature_id' => $addonSubFeatureID41,
            ],
            [
                'addon_id' => 4,
                'addon_sub_feature_id' => $addonSubFeatureID42,
            ],
            [
                'addon_id' => 5,
                'addon_sub_feature_id' => $addonSubFeatureID51,
            ],
            [
                'addon_id' => 5,
                'addon_sub_feature_id' => $addonSubFeatureID52,
            ],
            [
                'addon_id' => 6,
                'addon_sub_feature_id' => $addonSubFeatureIDLL111,
            ],
            [
                'addon_id' => 6,
                'addon_sub_feature_id' => $addonSubFeatureIDLL112,
            ],
            [
                'addon_id' => 7,
                'addon_sub_feature_id' => $addonSubFeatureIDAD11,
            ],
            [
                'addon_id' => 8,
                'addon_sub_feature_id' => $addonSubFeatureIDAD21,
            ],
            [
                'addon_id' => 9,
                'addon_sub_feature_id' => $addonSubFeatureIDAD31,
            ],
            [
                'addon_id' => 9,
                'addon_sub_feature_id' => $addonSubFeatureIDAD32,
            ],
            [
                'addon_id' => 10,
                'addon_sub_feature_id' => $addonSubFeatureIDAD42,
            ],
            [
                'addon_id' => 11,
                'addon_sub_feature_id' => $addonSubFeatureID61,
            ],
            [
                'addon_id' => 11,
                'addon_sub_feature_id' => $addonSubFeatureID62,
            ],
            [
                'addon_id' => 12,
                'addon_sub_feature_id' => $addonSubFeatureIDLL21,
            ],
            [
                'addon_id' => 13,
                'addon_sub_feature_id' => $addonSubFeatureIDLL21,
            ],
            [
                'addon_id' => 14,
                'addon_sub_feature_id' => $addonSubFeatureIDLL21,
            ],
        ]);


        // Restaurants Addons Connect
        DB::table('restaurant_addons')->insert([
            'restaurants_id' => 1,
            'addons_id' => 1,
            'addon_plan_type' => 'monthly',
        ]);

        DB::table('restaurant_addons')->insert([
            'restaurants_id' => 1,
            'addons_id' => 7,
            'addon_plan_type' => 'monthly',
        ]);

        DB::table('restaurant_addons')->insert([
            'restaurants_id' => 1,
            'addons_id' => 8,
            'addon_plan_type' => 'monthly',
        ]);

        DB::table('restaurant_addons')->insert([
            'restaurants_id' => 1,
            'addons_id' => 9,
            'addon_plan_type' => 'monthly',
        ]);

        DB::table('restaurant_addons')->insert([
            'restaurants_id' => 1,
            'addons_id' => 10,
            'addon_plan_type' => 'monthly',
        ]);

        DB::table('restaurant_addons')->insert([
            'restaurants_id' => 2,
            'addons_id' => 6,
            'addon_plan_type' => 'monthly',
        ]);

        DB::table('restaurant_addons')->insert([
            'restaurants_id' => 2,
            'addons_id' => 11,
            'addon_plan_type' => 'monthly',
        ]);

        DB::table('restaurant_addons')->insert([
            'restaurants_id' => 3,
            'addons_id' => 2,
            'addon_plan_type' => 'monthly',
        ]);

        DB::table('restaurant_addons')->insert([
            'restaurants_id' => 3,
            'addons_id' => 3,
            'addon_plan_type' => 'monthly',
        ]);

        DB::table('restaurant_addons')->insert([
            'restaurants_id' => 3,
            'addons_id' => 4,
            'addon_plan_type' => 'monthly',
        ]);

        DB::table('restaurant_addons')->insert([
            'restaurants_id' => 3,
            'addons_id' => 5,
            'addon_plan_type' => 'monthly',
        ]);

        DB::table('restaurant_addons')->insert([
            'restaurants_id' => 3,
            'addons_id' => 10,
            'addon_plan_type' => 'monthly',
        ]);

        DB::table('restaurant_addons')->insert([
            'restaurants_id' => 1,
            'addons_id' => 12,
            'addon_plan_type' => 'monthly',
        ]);

        DB::table('restaurant_addons')->insert([
            'restaurants_id' => 2,
            'addons_id' => 13,
            'addon_plan_type' => 'monthly',
        ]);

        DB::table('restaurant_addons')->insert([
            'restaurants_id' => 3,
            'addons_id' => 14,
            'addon_plan_type' => 'monthly',
        ]);

        // Restaurants Pricing Plans
        DB::table('restaurants')->where('id', 1)->update(
            [
                'plan_id' => 2,
                'plan_type' => 'monthly',
                'active_plan' => 1,
            ]
        );
        DB::table('restaurants')->where('id', 2)->update(
            [
                'plan_id' => 3,
                'plan_type' => 'monthly',
                'active_plan' => 1,
            ]
        );
        DB::table('restaurants')->where('id', 3)->update(
            [
                'plan_id' => 6,
                'plan_type' => 'monthly',
                'active_plan' => 1,
            ]
        );

        $pricingPlanFeatureID1 = DB::table('features')->insertGetId([
            'name' => 'Dashboard',
            'link' => 'dashboard',
        ]);

        // sub-feature-id: 1
        $pricingPlanSubFeatureID11 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID1,
            'name' => 'Dashboard',
            'link' => 'venue-dashboard',
        ]);

        // sub-feature-id: 2
        $pricingPlanSubFeatureID12 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID1,
            'name' => 'Analytics',
            'link' => 'analytics',
        ]);

        // sub-feature-id: 3
        $pricingPlanSubFeatureID13 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID1,
            'name' => 'Customer Insights',
            'link' => 'insights',
        ]);

        $pricingPlanFeatureID2 = DB::table('features')->insertGetId([
            'name' => 'Menu',
            'link' => 'menu',
        ]);

        // sub-feature-id: 4
        $pricingPlanSubFeatureID21 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID2,
            'name' => 'Menu',
            'link' => 'menu',
        ]);

        // sub-feature-id: 5
        $pricingPlanSubFeatureID22 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID2,
            'name' => 'Items',
            'link' => 'items',
        ]);

        // sub-feature-id: 6
        $pricingPlanSubFeatureID23 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID2,
            'name' => 'Categories',
            'link' => 'categories',
        ]);

        // sub-feature-id: 7
        $pricingPlanSubFeatureID24 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID2,
            'name' => 'Inventory',
            'link' => 'inventory',
        ]);

        $pricingPlanFeatureID3 = DB::table('features')->insertGetId([
            'name' => 'Guests',
            'link' => 'guests',
        ]);

        // sub-feature-id: 8
        $pricingPlanSubFeatureID31 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID3,
            'name' => 'Guests',
            'link' => 'guests',
        ]);

        $pricingPlanFeatureID4 = DB::table('features')->insertGetId([
            'name' => 'Tables',
            'link' => 'table',
        ]);

        $pricingPlanSubFeatureID41 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID4,
            'name' => 'Tables Management',
            'link' => 'tables',
        ]);

        $pricingPlanSubFeatureID42 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID4,
            'name' => 'Available By Date',
            'link' => 'available',
        ]);

        $pricingPlanFeatureID5 = DB::table('features')->insertGetId([
            'name' => 'Staff Management',
            'link' => 'staff',
        ]);

        $pricingPlanSubFeatureID51 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID5,
            'name' => 'Employees',
            'link' => 'employees',
        ]);

        $pricingPlanSubFeatureID52 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID5,
            'name' => 'Time-off requests',
            'link' => 'time-off-requests',
        ]);

        $pricingPlanFeatureID6 = DB::table('features')->insertGetId([
            'name' => 'Marketing',
            'link' => 'marketing',
        ]);

        $pricingPlanSubFeatureID61 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID6,
            'name' => 'Promotions',
            'link' => 'promotions',
        ]);

        $pricingPlanSubFeatureID62 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID6,
            'name' => 'Emails',
            'link' => 'emails',
        ]);

        $pricingPlanSubFeatureID63 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID6,
            'name' => 'Referrals',
            'link' => 'referrals',
        ]);

        $pricingPlanFeatureID7 = DB::table('features')->insertGetId([
            'name' => 'Reporting',
            'link' => 'reporting',
        ]);

        $pricingPlanSubFeatureID71 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID7,
            'name' => 'Orders',
            'link' => 'orders',
        ]);

        $pricingPlanSubFeatureID72 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID7,
            'name' => 'Staff',
            'link' => 'staff-reporting',
        ]);

        $pricingPlanSubFeatureID73 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID7,
            'name' => 'Financial',
            'link' => 'financial',
        ]);

        $pricingPlanSubFeatureID74 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID7,
            'name' => 'Sales',
            'link' => 'sales-reporting',
        ]);

        $pricingPlanSubFeatureID75 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID7,
            'name' => 'Waitlist',
            'link' => 'waitlist-reporting',
        ]);

        $pricingPlanSubFeatureID76 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID7,
            'name' => 'Tables',
            'link' => 'tables-reporting',
        ]);

        $pricingPlanSubFeatureID77 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID7,
            'name' => 'Advanced',
            'link' => 'advanced',
        ]);

        $pricingPlanFeatureID8 = DB::table('features')->insertGetId([
            'name' => 'Integrations',
            'link' => 'integrations',
        ]);

        $pricingPlanSubFeatureID81 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID8,
            'name' => 'Delivery & Pickup',
            'link' => 'delivery-and-pickup',
        ]);

        $pricingPlanFeatureID9 = DB::table('features')->insertGetId([
            'name' => 'Settings',
            'link' => 'settings',
        ]);

        $pricingPlanSubFeatureID91 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID9,
            'name' => 'Subscription',
            'link' => 'subscription',
        ]);

        $pricingPlanSubFeatureID92 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID9,
            'name' => 'Account',
            'link' => 'account',
        ]);

        $pricingPlanSubFeatureID93 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureID9,
            'name' => 'Space',
            'link' => 'space',
        ]);


        $pricingPlanFeatureIDR1 = DB::table('features')->insertGetId([
            'name' => 'Reservations',
            'link' => 'reservation',
        ]);

        $pricingPlanSubFeatureIDR11 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureIDR1,
            'name' => 'Waitlist Management',
            'link' => 'waitlist',
        ]);

        $pricingPlanSubFeatureIDR12 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureIDR1,
            'name' => 'Seating Arrangement',
            'link' => 'seating-arrangement',
        ]);

        $pricingPlanSubFeatureIDR13 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureIDR1,
            'name' => 'Dining Space Locations',
            'link' => 'dining-space-locations',
        ]);

        $pricingPlanSubFeatureIDR14 = DB::table('sub_features')->insertGetId([
            'feature_id' => $pricingPlanFeatureIDR1,
            'name' => 'Reservations',
            'link' => 'reservations',
        ]);

        DB::table('plan_features')->insert([
            [
                'plan_id' => 1,
                'feature_id' => $pricingPlanFeatureID1,
            ],
            [
                'plan_id' => 1,
                'feature_id' => $pricingPlanFeatureID2,
            ],
            [
                'plan_id' => 1,
                'feature_id' => $pricingPlanFeatureID3,
            ],
            [
                'plan_id' => 2,
                'feature_id' => $pricingPlanFeatureID1,
            ],
            [
                'plan_id' => 2,
                'feature_id' => $pricingPlanFeatureID2,
            ],
            [
                'plan_id' => 2,
                'feature_id' => $pricingPlanFeatureID3,
            ],
            [
                'plan_id' => 3,
                'feature_id' => $pricingPlanFeatureID1,
            ],
            [
                'plan_id' => 3,
                'feature_id' => $pricingPlanFeatureID3,
            ],
            [
                'plan_id' => 4,
                'feature_id' => $pricingPlanFeatureID1,
            ],
            [
                'plan_id' => 4,
                'feature_id' => $pricingPlanFeatureID3,
            ],
            [
                'plan_id' => 5,
                'feature_id' => $pricingPlanFeatureID1,
            ],
            [
                'plan_id' => 5,
                'feature_id' => $pricingPlanFeatureID3,
            ],
            [
                'plan_id' => 6,
                'feature_id' => $pricingPlanFeatureID1,
            ],
            [
                'plan_id' => 6,
                'feature_id' => $pricingPlanFeatureID3,
            ],
            [
                'plan_id' => 1,
                'feature_id' => $pricingPlanFeatureIDR1,
            ],
            [
                'plan_id' => 1,
                'feature_id' => $pricingPlanFeatureID4,
            ],
            [
                'plan_id' => 1,
                'feature_id' => $pricingPlanFeatureID9,
            ],
            [
                'plan_id' => 2,
                'feature_id' => $pricingPlanFeatureID4,
            ],
            [
                'plan_id' => 2,
                'feature_id' => $pricingPlanFeatureID5,
            ],
            [
                'plan_id' => 2,
                'feature_id' => $pricingPlanFeatureID6,
            ],
            [
                'plan_id' => 2,
                'feature_id' => $pricingPlanFeatureID7,
            ],
            [
                'plan_id' => 2,
                'feature_id' => $pricingPlanFeatureID9,
            ],
            [
                'plan_id' => 2,
                'feature_id' => $pricingPlanFeatureIDR1,
            ],
            [
                'plan_id' => 3,
                'feature_id' => $pricingPlanFeatureID9,
            ],
            [
                'plan_id' => 3,
                'feature_id' => $pricingPlanFeatureIDR1,
            ],
            [
                'plan_id' => 4,
                'feature_id' => $pricingPlanFeatureID5,
            ],
            [
                'plan_id' => 4,
                'feature_id' => $pricingPlanFeatureID6,
            ],
            [
                'plan_id' => 4,
                'feature_id' => $pricingPlanFeatureID7,
            ],
            [
                'plan_id' => 4,
                'feature_id' => $pricingPlanFeatureID9,
            ],
            [
                'plan_id' => 4,
                'feature_id' => $pricingPlanFeatureIDR1,
            ],
            [
                'plan_id' => 5,
                'feature_id' => $pricingPlanFeatureID9,
            ],
            [
                'plan_id' => 5,
                'feature_id' => $pricingPlanFeatureIDR1,
            ],
            [
                'plan_id' => 6,
                'feature_id' => $pricingPlanFeatureIDR1,
            ],
            [
                'plan_id' => 6,
                'feature_id' => $pricingPlanFeatureID5,
            ],
            [
                'plan_id' => 6,
                'feature_id' => $pricingPlanFeatureID6,
            ],
            [
                'plan_id' => 6,
                'feature_id' => $pricingPlanFeatureID7,
            ],
            [
                'plan_id' => 6,
                'feature_id' => $pricingPlanFeatureID9,
            ],
            [
                'plan_id' => 2,
                'feature_id' => $pricingPlanFeatureID8,
            ]
        ]);

        DB::table('plan_sub_features')->insert([
            [
                'plan_id' => 1,
                'sub_feature_id' => $pricingPlanSubFeatureID11,
            ],
            [
                'plan_id' => 1,
                'sub_feature_id' => $pricingPlanSubFeatureID12,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID11,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID12,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID13,
            ],
            [
                'plan_id' => 3,
                'sub_feature_id' => $pricingPlanSubFeatureID11,
            ],
            [
                'plan_id' => 3,
                'sub_feature_id' => $pricingPlanSubFeatureID12,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureID11,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureID12,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureID13,
            ],
            [
                'plan_id' => 5,
                'sub_feature_id' => $pricingPlanSubFeatureID11,
            ],
            [
                'plan_id' => 5,
                'sub_feature_id' => $pricingPlanSubFeatureID12,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureID11,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureID12,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureID13,
            ],
            [
                'plan_id' => 1,
                'sub_feature_id' => $pricingPlanSubFeatureID21,
            ],
            [
                'plan_id' => 1,
                'sub_feature_id' => $pricingPlanSubFeatureID22,
            ],
            [
                'plan_id' => 1,
                'sub_feature_id' => $pricingPlanSubFeatureID23,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID21,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID22,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID23,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID24,
            ],
            [
                'plan_id' => 1,
                'sub_feature_id' => $pricingPlanSubFeatureID31,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID31,
            ],
            [
                'plan_id' => 3,
                'sub_feature_id' => $pricingPlanSubFeatureID31,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureID31,
            ],
            [
                'plan_id' => 5,
                'sub_feature_id' => $pricingPlanSubFeatureID31,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureID31,
            ],
            [
                'plan_id' => 1,
                'sub_feature_id' => $pricingPlanSubFeatureID41,
            ],
            [
                'plan_id' => 1,
                'sub_feature_id' => $pricingPlanSubFeatureIDR11,
            ],
            [
                'plan_id' => 1,
                'sub_feature_id' => $pricingPlanSubFeatureID91,
            ],
            [
                'plan_id' => 1,
                'sub_feature_id' => $pricingPlanSubFeatureID92,
            ],
            [
                'plan_id' => 1,
                'sub_feature_id' => $pricingPlanSubFeatureID93,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID41,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID42,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID51,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID52,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID61,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID62,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID63,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID71,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID72,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID73,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID74,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID75,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID76,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID77,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID91,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID92,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID93,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureIDR11,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureIDR12,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureIDR13,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureIDR14,
            ],
            [
                'plan_id' => 3,
                'sub_feature_id' => $pricingPlanSubFeatureIDR11,
            ],
            [
                'plan_id' => 3,
                'sub_feature_id' => $pricingPlanSubFeatureID91,
            ],
            [
                'plan_id' => 3,
                'sub_feature_id' => $pricingPlanSubFeatureID92,
            ],
            [
                'plan_id' => 3,
                'sub_feature_id' => $pricingPlanSubFeatureID93,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureID51,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureID52,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureID61,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureID62,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureID63,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureID72,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureID73,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureID74,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureID91,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureID92,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureID93,
            ],
            [
                'plan_id' => 4,
                'sub_feature_id' => $pricingPlanSubFeatureIDR11,
            ],
            [
                'plan_id' => 5,
                'sub_feature_id' => $pricingPlanSubFeatureID91,
            ],
            [
                'plan_id' => 5,
                'sub_feature_id' => $pricingPlanSubFeatureID92,
            ],
            [
                'plan_id' => 5,
                'sub_feature_id' => $pricingPlanSubFeatureID93,
            ],
            [
                'plan_id' => 5,
                'sub_feature_id' => $pricingPlanSubFeatureIDR11,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureID51,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureID52,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureID61,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureID62,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureID63,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureID72,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureID73,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureID74,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureID91,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureID92,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureID93,
            ],
            [
                'plan_id' => 6,
                'sub_feature_id' => $pricingPlanSubFeatureIDR11,
            ],
            [
                'plan_id' => 2,
                'sub_feature_id' => $pricingPlanSubFeatureID81,
            ],

        ]);


    }
}
