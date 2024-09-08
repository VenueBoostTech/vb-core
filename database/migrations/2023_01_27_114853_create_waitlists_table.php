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
        Schema::create('waitlists', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('reservation_id')->nullable();
            $table->foreign('reservation_id')->references('id')->on('reservations');
            $table->unsignedInteger('guest_id');
            $table->foreign('guest_id')->references('id')->on('guests');
            $table->unsignedBigInteger('restaurant_id');
            $table->foreign('restaurant_id')->references('id')->on('restaurants');
            $table->integer('party_size');
            $table->string('guest_name');
            $table->string('guest_phone');
            $table->string('guest_email');
            $table->timestamp('added_at')->useCurrent();
            $table->boolean('notified')->default(0);
            $table->integer('estimated_wait_time')->nullable();
            $table->timestamp('guest_notified_at')->nullable();
            $table->boolean('is_vip')->default(false);
            $table->boolean('is_regular')->default(false);
            $table->timestamp('arrival_time')->nullable();
            $table->timestamp('seat_time')->nullable();
            $table->timestamp('left_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('waitlists');
    }
};
