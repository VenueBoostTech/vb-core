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
        $states = [
            [
                'name' => 'Delaware',
                'cities' => [
                    'Wilmington',
                    'Dover',
                    'Newark',
                    'Middletown',
                    'Smyrna',
                    'Milford',
                    'Seaford',
                    'Georgetown',
                    'Elsmere',
                    'New Castle',
                    'Millsboro',
                    'Laurel',
                    'Harrington',
                    'Camden',
                    'Clayton',
                    'Lewes',
                    'Milton',
                    'Selbyville',
                    'Bridgeville',
                    'Ocean View',
                    'Delmar',
                    'Townsend',
                    'Blades',
                    'Millville',
                    'Cheswold',
                    'Rehoboth Beach',
                    'Wyoming',
                    'Felton',
                    'Bellefonte',
                    'Bethany Beach',
                    'Newport',
                    'Greenwood',
                    'Frankford',
                    'Dagsboro',
                    'Frederica',
                    'South Bethany',
                    'Arden',
                    'Ellendale',
                    'Fenwick Island',
                    'Houston',
                    'Odessa',
                    'Bowers',
                    'Ardencroft',
                    'Ardentown',
                    'Magnolia',
                    'Little Creek',
                    'Slaughter Beach',
                    'Leipsic',
                    'Woodside',
                    'Kenton',
                    'Bethel',
                    'Henlopen Acres',
                    'Farmington',
                    'Viola',
                    'Hartly',
                    'Felton',
                    'Ardencroft',
                    'Ardentown',
                    'Bowers',
                    'Bethel',
                    'Bethany Beach',
                    'Magnolia',
                    'Leipsic',
                    'Little Creek',
                    'Henlopen Acres',
                    'Farmington',
                    'Ellendale',
                ]
            ],
        ];

        foreach ($states as $key => $state) {
            $stateId = DB::table('states')->insertGetId([
                'name' => $state['name'],
                'country_id' => 1
            ]);

            foreach ($state['cities'] as $key => $city) {
                DB::table('cities')->insert([
                    'name' => $city,
                    'states_id' => $stateId
                ]);
            }
        }

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
