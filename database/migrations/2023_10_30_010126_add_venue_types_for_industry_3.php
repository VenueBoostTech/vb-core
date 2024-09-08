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

        $data = [
            [
                'name' => 'Luxury Resort',
                'short_name' => 'luxury_resort',
                'definition' => 'accommodation',
                'industry_id' => 3
            ],
            [
                'name' => 'Family Resorts',
                'short_name' => 'family_resort',
                'definition' => 'accommodation',
                'industry_id' => 3
            ],
            [
                'name' => 'Service Apartment',
                'short_name' => 'service_apartment',
                'definition' => 'accommodation',
                'industry_id' => 3
            ],
            [
                'name' => 'Bed and Breakfast',
                'short_name' => 'bed_and_breakfast',
                'definition' => 'accommodation',
                'industry_id' => 3
            ],
            [
                'name' => 'Motel',
                'short_name' => 'motel',
                'definition' => 'accommodation',
                'industry_id' => 3
            ],
            [
                'name' => 'Capsule Hotel',
                'short_name' => 'capsule_hotel',
                'definition' => 'accommodation',
                'industry_id' => 3
            ],
            [
                'name' => 'Youth Hostel',
                'short_name' => 'youth_hostel',
                'definition' => 'accommodation',
                'industry_id' => 3
            ],
            [
                'name' => 'Campground',
                'short_name' => 'campground',
                'definition' => 'accommodation',
                'industry_id' => 3
            ],
            [
                'name' => 'RV Park',
                'short_name' => 'rv_park',
                'definition' => 'accommodation',
                'industry_id' => 3
            ],
        ];

        DB::table('venue_types')->insert($data);

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
