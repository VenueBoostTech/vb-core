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
        Schema::create('golf_course_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('price_method');
            $table->integer('price');
            $table->datetime('start_time');
            $table->datetime('end_time');
            $table->integer('max_allowed');
            $table->unsignedBigInteger('venue_id');
            $table->timestamps();

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
        });

        Schema::create('gym_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('price_method');
            $table->integer('price');
            $table->datetime('start_time');
            $table->datetime('end_time');
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
        Schema::dropIfExists('golf_course_types');
        Schema::dropIfExists('gym_classes');
    }
};
