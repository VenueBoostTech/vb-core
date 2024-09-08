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

        Schema::create('reservations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('table_id')->nullable();
            $table->foreign('table_id')->references('id')->on('tables');
            $table->unsignedBigInteger('restaurant_id');
            $table->foreign('restaurant_id')->references('id')->on('restaurants');
            $table->datetime('start_time');
            $table->datetime('end_time');
            $table->string('seating_arrangement')->nullable();
            $table->unsignedInteger('guest_count');
            $table->string('notes')->nullable();
            $table->enum('insertion_type', ['snapfood_app', 'manually_entered', 'from_integration'])->default('manually_entered');
            $table->enum('source', ['snapfood', 'facebook', 'call', 'instagram', 'google', 'website', 'other', 'ubereats'])->default('call');
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
        Schema::dropIfExists('reservations');
    }
};
