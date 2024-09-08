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
        Schema::create('venue_whitelabel_information', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('venue_id');

            // restaurant specific
            $table->unsignedBigInteger('main_cuisine')->nullable();
            $table->string('dining_style')->nullable();
            $table->string('dress_code')->nullable();


            // golf and food specifically
            $table->json('tags')->nullable();

            // everyone specific
            $table->string('neighborhood')->nullable();
            $table->text('parking_details')->nullable();
            $table->text('description')->nullable();
            $table->json('payment_options')->nullable();
            $table->string('additional')->nullable();

            // golf specific
            $table->string('field_m2')->nullable();
            $table->string('golf_style')->nullable();
            $table->string('main_tag')->nullable();

            // hotel specifics
            $table->boolean('has_free_wifi')->default(0);
            $table->boolean('has_spa')->default(0);
            $table->boolean('has_events_hall')->default(0);
            $table->boolean('has_gym')->default(0);
            $table->boolean('has_restaurant')->default(0);
            $table->string('hotel_type')->nullable();
            $table->string('wifi')->nullable();
            $table->string('stars')->nullable();
            $table->string('restaurant_type')->nullable();
            $table->string('room_service_starts_at')->nullable();

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('main_cuisine')->references('id')->on('cuisine_types');
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
        Schema::dropIfExists('venue_whitelabel_information');
    }
};
