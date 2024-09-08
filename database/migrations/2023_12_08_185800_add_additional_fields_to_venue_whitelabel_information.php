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
        Schema::table('venue_whitelabel_information', function (Blueprint $table) {
            $table->json('equipment_types')->nullable();
            $table->json('facilities')->nullable();
            $table->json('amenities')->nullable();
            $table->boolean('offers_food_and_beverage')->default(0);
            $table->boolean('offers_restaurant')->default(0);
            $table->boolean('offers_bar')->default(0);
            $table->boolean('offers_snackbar')->default(0);
            $table->string('nr_holes')->nullable();
            $table->boolean('advance_lane_reservation')->default(0);
            $table->json('lanes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('venue_whitelabel_information', function (Blueprint $table) {
            $table->dropColumn('equipment_types');
            $table->dropColumn('amenities');
            $table->dropColumn('facilities');
            $table->dropColumn('offers_food_and_beverage');
            $table->dropColumn('offers_restaurant');
            $table->dropColumn('offers_bar');
            $table->dropColumn('offers_snackbar');
            $table->dropColumn('nr_holes');
            $table->dropColumn('advance_lane_reservation');
            $table->dropColumn('lanes');
        });
    }
};
