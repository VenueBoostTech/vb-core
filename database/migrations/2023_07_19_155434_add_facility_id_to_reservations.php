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
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedInteger('hotel_restaurant_id')->nullable();
            $table->unsignedInteger('hotel_gym_id')->nullable();
            $table->unsignedInteger('hotel_events_hall_id')->nullable();

            // Modify the source field
            DB::statement("ALTER TABLE reservations MODIFY COLUMN source ENUM('snapfood', 'facebook', 'call', 'instagram', 'google', 'website', 'other', 'ubereats', 'vb-web') DEFAULT 'call'");

            $table->foreign('hotel_restaurant_id')->references('id')->on('hotel_restaurants')->onDelete('cascade');
            $table->foreign('hotel_gym_id')->references('id')->on('hotel_gyms')->onDelete('cascade');
            $table->foreign('hotel_events_hall_id')->references('id')->on('hotel_events_halls')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Drop the fields
            $table->dropColumn('hotel_restaurant_id');
            $table->dropColumn('hotel_gym_id');
            $table->dropColumn('hotel_events_hall_id');

            // Restore the original source field options
            DB::statement("ALTER TABLE reservations MODIFY COLUMN source VARCHAR(255) DEFAULT 'call'");
        });
    }
};
