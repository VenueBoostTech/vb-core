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
        Schema::create('bowling_lane', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('price_method');
            $table->integer('price');
            $table->integer('max_allowed');
            $table->unsignedBigInteger('venue_id');
            $table->timestamps();

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
        Schema::dropIfExists('bowling_lane');
    }
};
