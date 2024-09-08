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
        Schema::create('earn_points_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('guest_id');
            $table->unsignedInteger('reservation_id')->nullable();
            $table->unsignedBigInteger('venue_id');
            $table->integer('points_earned');
            $table->timestamps();

            // Add foreign key constraint for guest_id
            $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade');
            // Add foreign key constraint for reservation_id
            $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('set null');
            // Add foreign key constraint for venue_id
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
        Schema::dropIfExists('earn_points_histories');
    }
};
