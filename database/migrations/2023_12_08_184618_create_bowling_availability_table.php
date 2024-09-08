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
        Schema::create('bowling_availability', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bowling_id');
            $table->string('day_of_week');
            $table->time('open_time');
            $table->time('close_time');
            $table->timestamps();

            $table->foreign('bowling_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bowling_availability');
    }
};
