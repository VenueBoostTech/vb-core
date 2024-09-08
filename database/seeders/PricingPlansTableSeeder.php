<?php

namespace Database\Seeders;

use App\Models\PricingPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PricingPlansTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $plans = [
            [
                'short_code' => 'VB-PP-FD-L0',
                'category' => 'food_drinks',
                'name' => 'Free',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
                'features' => [
                    'Table Management',
                    'Staff Management',
                    'Guest Management',
                    'Seating Arrangement',
                    'Table Reservation',
                    'Table Booking',
                    'Table Booking Calendar',
                    'Order & Pay',
                    'Order Management',

                ]
            ],
            [
                'short_code' => 'VB-PP-FD-L1',
                'category' => 'food_drinks',
                'name' => 'Starter',
                'monthly_cost' => 199,
                'yearly_cost' => 999,
                'currency' => 'USD',
                'features' => [
                    'Table Management',
                    'Staff Management',
                    'Guest Management',
                    'Seating Arrangement',
                    'Table Reservation',
                    'Table Booking',
                    'Table Booking Calendar',
                    'Order & Pay',
                    'Order Management',
                    'Inventory Management',
                    'Analytics',
                    'Reports',
                    'Promotions',
                ]
            ],
            [
                'short_code' => 'VB-PP-SE-L0',
                'category' => 'sport_entertainment',
                'name' => 'Sports & Entertainment Free',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
                'features' => [
                    'Table Management',
                    'Staff Management',
                    'Guest Management',
                    'Seating Arrangement',
                    'Table Reservation',
                    'Table Booking',
                    'Table Booking Calendar',
                    'Order & Pay',
                    'Order Management',

                ]
            ],
            [
                'short_code' => 'VB-PP-SE-L1',
                'category' => 'sport_entertainment',
                'name' => 'Sport & Entertainment Starter',
                'monthly_cost' => 199,
                'yearly_cost' => 999,
                'currency' => 'USD',
                'features' => [
                    'Table Management',
                    'Staff Management',
                    'Guest Management',
                    'Seating Arrangement',
                    'Table Reservation',
                    'Table Booking',
                    'Table Booking Calendar',
                    'Order & Pay',
                    'Order Management',
                    'Inventory Management',
                    'Analytics',
                    'Reports',
                    'Promotions',
                ]
            ],
            [
                'short_code' => 'VB-PP-HR-L0',
                'category' => 'hotel_resort',
                'name' => 'Hotel Free',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
                'features' => [
                    'Table Management',
                    'Staff Management',
                    'Guest Management',
                    'Seating Arrangement',
                    'Table Reservation',
                    'Table Booking',
                    'Table Booking Calendar',
                    'Order & Pay',
                    'Order Management',

                ]
            ],
            [
                'short_code' => 'VB-PP-HR-L1',
                'category' => 'hotel_resort',
                'name' => 'Hotel Starter',
                'monthly_cost' => 199,
                'yearly_cost' => 999,
                'currency' => 'USD',
                'features' => [
                    'Table Management',
                    'Staff Management',
                    'Guest Management',
                    'Seating Arrangement',
                    'Table Reservation',
                    'Table Booking',
                    'Table Booking Calendar',
                    'Order & Pay',
                    'Order Management',
                    'Inventory Management',
                    'Analytics',
                    'Reports',
                    'Promotions',
                ]
            ],
        ];

        foreach ($plans as $plan) {
            PricingPlan::create($plan);
        }
    }
}
