<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('venue_types', function (Blueprint $table) {
            $table->string('short_name');
            $table->integer('industry_id');
        });


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

        // Get the industry ID based on the `name`
        foreach ($venue_types as &$type) {
            switch ($type['name']) {
                case 'Restaurant':
                case 'Bistro':
                case 'Bar':
                case 'Pub & Club':
                case 'Cafe':
                    $type['industry_id'] = 1; // Replace 1 with the ID of the 'Food' industry in the venue_industries table
                    $type['definition'] = 'food';
                    break;
                case 'Golf Venue':
                case 'Bowling':
                case 'Gym':
                    $type['industry_id'] = 2; // Replace 2 with the ID of the 'Sport & Entertainment' industry in the venue_industries table
                    $type['definition'] = 'sport_entertainment';
                break;
                case 'Hotel':
                    $type['industry_id'] = 3; // Replace 3 with the ID of the 'Accommodation' industry in the venue_industries table
                    $type['definition'] = 'accommodation';
                    break;
                // Add more cases for other industries
            }

            // Set the `short_name` based on the `name`
            $type['short_name'] = $this->getShortName($type['name']);
        }

        DB::table('venue_types')
            ->where('name', '=', 'Bowling')
            ->update([
                'short_name' => 'bowling',
                'definition' => 'sport_entertainment',
                'industry_id' => 2,
            ]);


        // Update the existing rows in the venue_types table with the new values
        foreach ($venue_types as $type) {
            DB::table('venue_types')
                ->where('name', $type['name'])
                ->update([
                    'short_name' => $type['short_name'],
                    'definition' => $type['definition'],
                    'industry_id' => $type['industry_id'],
                ]);
        }


        // Insert data into the venue_types table
        $data = [
            [
                'name' => 'Food Truck',
                'short_name' => 'food_truck',
                'definition' => 'food',
                'industry_id' => 1
            ],
            [
                'name' => 'Cozy Retreat',
                'short_name' => 'cozy_retreat',
                'definition' => 'accommodation',
                'industry_id' => 3
            ],
            [
                'name' => 'Hostel',
                'short_name' => 'hostel',
                'definition' => 'accommodation',
                'industry_id' => 3
            ],
            [
                'name' => 'Hotel Chain',
                'short_name' => 'hotel_chain',
                'definition' => 'accommodation',
                'industry_id' => 3
            ],
            [
                'name' => 'Vacation Rental',
                'short_name' => 'vacation_rental',
                'definition' => 'accommodation',
                'industry_id' => 3
            ],
            [
                'name' => 'Sport Essentials',
                'short_name' => 'sport_essentials',
                'definition' => 'retail',
                'industry_id' => 4
            ],
            [
                'name' => 'Home Decor',
                'short_name' => 'home_decor',
                'definition' => 'retail',
                'industry_id' => 4
            ],
            [
                'name' => 'Fashion & Threads',
                'short_name' => 'fashion_threads',
                'definition' => 'retail',
                'industry_id' => 4
            ],
            [
                'name' => 'Hospital',
                'short_name' => 'hospital',
                'definition' => 'healthcare',
                'industry_id' => 5
            ],
            [
                'name' => 'Dental Clinic',
                'short_name' => 'dental_clinic',
                'definition' => 'healthcare',
                'industry_id' => 5
            ],
        ];

        DB::table('venue_types')->insert($data);


        DB::table('venue_types')
            ->where('name', '=', 'Food Truck')
            ->update([
                'short_name' => 'food_truck',
                'industry_id' => 1,
            ]);

        DB::table('venue_types')
            ->where('name', '=', 'Cozy Retreat')
            ->update([
                'short_name' => 'cozy_retreat',
                'industry_id' => 3,
            ]);

        DB::table('venue_types')
            ->where('name', '=', 'Hostel')
            ->update([
                'short_name' => 'hostel',
                'industry_id' => 3,
            ]);


        DB::table('venue_types')
            ->where('name', '=', 'Hotel Chain')
            ->update([
                'short_name' => 'hotel_chain',
                'industry_id' => 3,
            ]);

        DB::table('venue_types')
            ->where('name', '=', 'Vacation Rental')
            ->update([
                'short_name' => 'vacation_rental',
                'industry_id' => 3,
            ]);


        DB::table('venue_types')
            ->where('name', '=', 'Sport Essentials')
            ->update([
                'short_name' => 'sport_essentials',
                'industry_id' => 4,
            ]);

        DB::table('venue_types')
            ->where('name', '=', 'Home Decor')
            ->update([
                'short_name' => 'home_decor',
                'industry_id' => 4,
            ]);

        DB::table('venue_types')
            ->where('name', '=', 'Fashion & Threads')
            ->update([
                'short_name' => 'fashion_threads',
                'industry_id' => 4,
            ]);

        DB::table('venue_types')
            ->where('name', '=', 'Hospital')
            ->update([
                'short_name' => 'hospital',
                'industry_id' => 5,
            ]);

        DB::table('venue_types')
            ->where('name', '=', 'Dental Clinic')
            ->update([
                'short_name' => 'dental_clinic',
                'industry_id' => 5,
            ]);

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('venue_types', function (Blueprint $table) {
            $table->dropColumn('industry_id');
            $table->dropColumn('short_name');
        });
    }

    /**
     * Get the short name based on the name.
     *
     * @param  string  $name
     * @return string
     */
    private function getShortName(string $name): string
    {
        switch ($name) {
            case 'Restaurant':
                return 'restaurant';
            case 'Bistro':
                return 'bistro';
            case 'Bar':
                return 'bar';
            case 'Pub & Club':
                return 'pub_club';
            case 'Cafe':
                return 'cafe';
            case 'Hotel':
                return 'hotel';
            case 'Golf Venue':
                return 'golf_venue';
            case 'Bowling':
                return 'bowling';
            case 'Gym':
                return 'gym';
            // Add more cases for other names
            default:
                return '';
        }
    }
};
