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
        Schema::create('parking_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rental_unit_id');
            $table->unsignedBigInteger('venue_id');
            $table->enum('availability', ['free', 'paid', 'no']);
            $table->enum('reservation', ['needed', 'not_needed']);
            $table->enum('location', ['on_site', 'off_site']);
            $table->enum('type', ['private', 'public']);
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
        Schema::dropIfExists('parking_details');
    }
};
