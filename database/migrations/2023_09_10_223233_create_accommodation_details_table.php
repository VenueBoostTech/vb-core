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
        Schema::create('accommodation_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rental_unit_id');
            $table->unsignedBigInteger('venue_id');
            $table->integer('guest_limit');
            $table->integer('bathroom_count');
            $table->boolean('allow_children');
            $table->boolean('offer_cots');
            $table->float('square_metres');
            $table->timestamps();

            $table->foreign('rental_unit_id')->references('id')->on('rental_units')->onDelete('cascade');
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('accommodation_details');
    }
};
