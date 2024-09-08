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

        Schema::create('beach_bar_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->decimal('total_amount');
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('umbrella_id');
            $table->string('paid_with');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');

            $table->unsignedInteger('main_guest_id')->nullable();
            $table->string('main_guest_name')->nullable();
            $table->timestamps();

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('umbrella_id')->references('id')->on('umbrellas')->onDelete('cascade');
            $table->foreign('ticket_id')->references('id')->on('beach_bar_tickets')->onDelete('cascade');
            $table->foreign('main_guest_id')->references('id')->on('guests')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('beach_bar_bookings');
    }
};
