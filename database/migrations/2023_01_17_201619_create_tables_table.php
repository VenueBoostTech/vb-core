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
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('restaurant_id');
            $table->foreign('restaurant_id')->references('id')->on('restaurants');
            $table->enum('size', ['small', 'medium', 'large']);
            $table->integer('seats');
            $table->unsignedBigInteger('dining_space_location_id')->nullable();
            $table->foreign('dining_space_location_id')->references('id')->on('dining_space_locations')->onDelete('set null');
            $table->enum('shape', ['round', 'rectangular', 'square', 'booth', 'bar', 'u-shape', 'communal', 'oval']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tables');
    }
};
