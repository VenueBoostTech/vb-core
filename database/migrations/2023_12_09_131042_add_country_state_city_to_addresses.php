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
        Schema::table('addresses', function (Blueprint $table) {
            // Add country_id column
            $table->unsignedBigInteger('country_id')->nullable();
            $table->foreign('country_id')->references('id')->on('countries');

            // Add state_id column
            $table->unsignedBigInteger('state_id')->nullable();
            $table->foreign('state_id')->references('id')->on('states');

            // Add city_id column
            $table->unsignedBigInteger('city_id')->nullable();
            $table->foreign('city_id')->references('id')->on('cities');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('addresses', function (Blueprint $table) {
            // Drop country_id, state_id, and city_id columns
            $table->dropForeign(['country_id']);
            $table->dropColumn('country_id');
            $table->dropForeign(['state_id']);
            $table->dropColumn('state_id');
            $table->dropForeign(['city_id']);
            $table->dropColumn('city_id');
        });
    }
};
