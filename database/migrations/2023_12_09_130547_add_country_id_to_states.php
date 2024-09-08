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

        // Check if New York state exists and set its country_id to USA
        $newYork = DB::table('states')->where('name', 'New York')->first();
        if ($newYork) {
            DB::table('states')->where('name', 'New York')->update(['country_id' => 1]);
        }

        // Check if Tirana state exists and set its country_id to Albania
        $tirana = DB::table('states')->where('name', 'Tirana')->first();
        if ($tirana) {
            DB::table('states')->where('name', 'Tirana')->update(['country_id' => 13]);
        }

        // Check if Ulcinj Municipality exists and set its country_id to Montenegro
        $ulcinj = DB::table('states')->where('name', 'Ulcinj Municipality')->first();
        if ($ulcinj) {
            DB::table('states')->where('name', 'Ulcinj Municipality')->update(['country_id' => 14]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
};
