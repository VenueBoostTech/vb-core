<?php

namespace Database\Seeders;

use App\Models\PricingPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VenueTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $venue_types = [
            [
                'name' => 'Restaurant',
                'definition' => 'restaurant',
            ],
            [
                'name' => 'Bistro',
                'definition' => 'bistro',
            ],
            [
                'name' => 'Bar',
                'definition' => 'bar',
            ],
            [
                'name' => 'Pub & Club',
                'definition' => 'pub_club',
            ],
            [
                'name' => 'Cafe',
                'definition' => 'cafe',
            ],
            [
                'name' => 'Hotel',
                'definition' => 'hotel',
            ],
            [
                'name' => 'Golf Venue',
                'definition' => 'sport_entertainment',
            ],
            [
                'name' => 'Gym',
                'definition' => 'sport_entertainment',
            ],
            [
                'name' => 'Bowling',
                'definition' => 'sport_entertainment',
            ]
        ];

        foreach ($venue_types as $type) {
            DB::table('venue_types')->insert($type);
        }
    }
}
