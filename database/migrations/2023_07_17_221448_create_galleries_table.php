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
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id')->nullable();
            $table->unsignedInteger('hotel_restaurant_id')->nullable();
            $table->unsignedInteger('hotel_gym_id')->nullable();
            $table->unsignedInteger('hotel_events_hall_id')->nullable();
            $table->unsignedBigInteger('photo_id');
            $table->timestamps();

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('hotel_restaurant_id')->references('id')->on('hotel_restaurants')->onDelete('cascade');
            $table->foreign('hotel_gym_id')->references('id')->on('hotel_gyms')->onDelete('cascade');
            $table->foreign('hotel_events_hall_id')->references('id')->on('hotel_events_halls')->onDelete('cascade');
            $table->foreign('photo_id')->references('id')->on('photos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('galleries');

    }
};
