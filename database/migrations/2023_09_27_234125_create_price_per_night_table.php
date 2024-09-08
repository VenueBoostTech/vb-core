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
        Schema::create('price_per_night', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->unsignedBigInteger('rental_unit_id');
            $table->integer('nr_guests')->default(1);
            $table->decimal('price', 8, 2);
            $table->decimal('discount', 8, 2)->nullable(); // Optional, so nullable
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('rental_unit_id')->references('id')->on('rental_units')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('price_per_night');
    }
};
