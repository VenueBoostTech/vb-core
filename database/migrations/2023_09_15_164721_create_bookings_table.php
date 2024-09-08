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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->unsignedBigInteger('rental_unit_id')->nullable();
            $table->unsignedInteger('guest_id')->nullable();
            $table->integer('guest_nr');
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->integer('total_amount');
            $table->enum('status', ['Pending', 'Processing', 'Confirmed', 'Cancelled', 'Completed']);
            $table->enum('paid_with', ['card', 'cash'])->default('card');
            $table->integer('prepayment_amount');

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('rental_unit_id')->references('id')->on('rental_units')->onDelete('cascade');
            $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bookings');
    }
};
