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
        Schema::create('price_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->decimal('price_difference', 10, 2);
            $table->decimal('total_adjustment', 10, 2);
            $table->decimal('previous_total', 10, 2);
            $table->decimal('new_total', 10, 2);
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('rental_unit_id');
            $table->unsignedBigInteger('venue_id');
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings');
            $table->foreign('rental_unit_id')->references('id')->on('rental_units');
            $table->foreign('venue_id')->references('id')->on('restaurants');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('price_breakdowns');
    }
};
