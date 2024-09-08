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
        Schema::create('guest_reservation', function (Blueprint $table) {
            $table->unsignedInteger('guest_id');
            $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade');
            $table->unsignedInteger('reservation_id');
            $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('cascade');
            $table->primary(['guest_id', 'reservation_id']);
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
        Schema::dropIfExists('guest_reservation');
    }
};
