<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        DB::statement("ALTER TABLE tables MODIFY shape ENUM('round', 'rectangular', 'square', 'booth', 'bar', 'u-shape', 'communal', 'oval', 'picnic', 'hexagonal', 'triangle', 'conference', 'octagonal', 'horseshoe')");

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
