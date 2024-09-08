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
        // Add Ulcinj Municipality
        $municipalityId = DB::table('states')->insertGetId([
            'name' => 'Ulcinj Municipality',
        ]);

        // Add Ulcinj City under Ulcinj Municipality
        DB::table('cities')->insert([
            'name' => 'Ulcinj',
            'states_id' => $municipalityId,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove Ulcinj City
        DB::table('cities')->where('name', 'Ulcinj')->delete();

        // Remove Ulcinj Municipality
        DB::table('states')->where('name', 'Ulcinj Municipality')->delete();
    }
};
