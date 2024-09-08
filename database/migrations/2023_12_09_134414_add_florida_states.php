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
                'name' => 'Florida',
                'cities' => [
                    'Jacksonville',
                    'Miami',
                    'Tampa',
                    'Orlando',
                    'St. Petersburg',
                    'Hialeah',
                    'Tallahassee',
                    'Port St. Lucie',
                    'Cape Coral',
                    'Fort Lauderdale',
                    'Pembroke Pines',
                    'Hollywood',
                    'Miramar',
                    'Gainesville',
                    'Coral Springs',
                    'Miami Gardens',
                    'Clearwater',
                    'Palm Bay',
                    'Pompano Beach',
                    'West Palm Beach',
                    'Lakeland',
                    'Davie',
                    'Miami Beach',
                    'Sunrise',
                    'Plantation',
                    'Boca Raton',
                    'Deltona',
                    'Largo',
                    'Deerfield Beach',
                    'Palm Coast',
                    'Melbourne',
                    'Boynton Beach',
                    'Lauderhill',
                    'Weston',
                    'Fort Myers',
                    'Kissimmee',
                    'Homestead',
                    'Tamarac',
                    'Delray Beach',
                    'Daytona Beach',
                    'North Miami',
                    'Wellington',
                    'North Port',
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
