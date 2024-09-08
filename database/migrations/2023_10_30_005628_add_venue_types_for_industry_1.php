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
                'name' => 'Fine Dining',
                'short_name' => 'fine_dining',
                'definition' => 'food',
                'industry_id' => 1
            ],
            [
                'name' => 'Bakery',
                'short_name' => 'bakery',
                'definition' => 'food',
                'industry_id' => 1
            ],
            [
                'name' => 'Catering Service',
                'short_name' => 'catering_service',
                'definition' => 'food',
                'industry_id' => 1
            ],
            [
                'name' => 'Fast Food',
                'short_name' => 'fast_food',
                'definition' => 'food',
                'industry_id' => 1
            ],
            [
                'name' => 'Ice Cream Parlor',
                'short_name' => 'ice_cream_parlor',
                'definition' => 'food',
                'industry_id' => 1
            ],
            [
                'name' => 'Food Stall',
                'short_name' => 'food_stall',
                'definition' => 'food',
                'industry_id' => 1
            ],
            [
                'name' => 'Buffet',
                'short_name' => 'buffet',
                'definition' => 'food',
                'industry_id' => 1
            ],
            [
                'name' => 'Pop-Up Dining',
                'short_name' => 'pop_up_dining',
                'definition' => 'food',
                'industry_id' => 1
            ],
            [
                'name' => 'Juice Bar',
                'short_name' => 'juice_bar',
                'definition' => 'food',
                'industry_id' => 1
            ],
            [
                'name' => 'Food Hall',
                'short_name' => 'food_hall',
                'definition' => 'food',
                'industry_id' => 1
            ],
            [
                'name' => 'Supper Club',
                'short_name' => 'supper_club',
                'definition' => 'food',
                'industry_id' => 1
            ],
            [
                'name' => 'Pizzeria',
                'short_name' => 'pizzeria',
                'definition' => 'food',
                'industry_id' => 1
            ],
            [
                'name' => 'Coffee Shop',
                'short_name' => 'coffee_shop',
                'definition' => 'food',
                'industry_id' => 1
            ],
            [
                'name' => 'Delis',
                'short_name' => 'delis',
                'definition' => 'food',
                'industry_id' => 1
            ],
            [
                'name' => 'Food Court',
                'short_name' => 'food_court',
                'definition' => 'food',
                'industry_id' => 1
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
