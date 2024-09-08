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

        // Add new countries if they don't exist
        $newCountries = [
            ['name' => 'Ireland', 'code' => 'IE'],
            ['name' => 'New Zealand', 'code' => 'NZ']
        ];

        foreach ($newCountries as $country) {
            DB::table('countries')->insertOrIgnore([
                'name' => $country['name'],
                'code' => $country['code'],
            ]);
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
