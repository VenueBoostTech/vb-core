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
        Schema::create('additional_fee_and_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->unsignedBigInteger('rental_unit_id');
            $table->unsignedBigInteger('fee_name_id');
            $table->decimal('amount', 10, 2);
            // Add other fields if needed
            $table->timestamps();

            // Define foreign key relationships
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('rental_unit_id')->references('id')->on('rental_units')->onDelete('cascade');
            $table->foreign('fee_name_id')->references('id')->on('additional_fee_and_charges_names');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('additional_fee_and_charges');
    }
};
