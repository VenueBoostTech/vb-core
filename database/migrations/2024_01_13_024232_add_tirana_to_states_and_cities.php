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
            // ... Your existing states and cities for Delaware
            [
                'name' => 'Tirana',
                'country_id' => 13, // Assuming country_id 13 is for the country you want
                'cities' => [
                    'Tirana', // Add other cities in this state if needed
                ]
            ],
        ];

        foreach ($states as $state) {
            $stateId = DB::table('states')->insertGetId([
                'name' => $state['name'],
                'country_id' => $state['country_id']
            ]);

            foreach ($state['cities'] as $city) {
                DB::table('cities')->insert([
                    'name' => $city,
                    'states_id' => $stateId // Make sure the column name is 'state_id' not 'states_id'
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
        Schema::table('states_and_cities', function (Blueprint $table) {
            //
        });
    }
};
