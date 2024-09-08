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
        Schema::create('rental_unit_facility', function (Blueprint $table) {
            $table->unsignedBigInteger('rental_unit_id');
            $table->unsignedBigInteger('facility_id');

            $table->foreign('rental_unit_id')->references('id')->on('rental_units')->onDelete('cascade');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');

            $table->primary(['rental_unit_id', 'facility_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rental_unit_facility');
    }
};
