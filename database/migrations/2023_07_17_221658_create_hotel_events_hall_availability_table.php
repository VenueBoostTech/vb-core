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
        Schema::create('hotel_events_hall_availability', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('events_hall_id');
            $table->string('day_of_week');
            $table->time('open_time');
            $table->time('close_time');
            $table->timestamps();

            $table->foreign('events_hall_id')->references('id')->on('hotel_events_halls')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hotel_events_hall_availability');
    }
};
