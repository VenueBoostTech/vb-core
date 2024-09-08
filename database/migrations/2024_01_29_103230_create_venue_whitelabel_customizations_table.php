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

        Schema::create('venue_whitelabel_customizations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('venue_id');
            $table->unsignedBigInteger('v_wl_information_id')->unique();

            // Social Media Links
            $table->string('facebook_link')->nullable();
            $table->string('twitter_link')->nullable();
            $table->string('instagram_link')->nullable();

            // Booking Sites
            // example
            /**
             * {
                "booking_site_1": {
                        "name": "Airbnb",
                "url": "https://www.airbnb.com"
                },
                "booking_site_2": {
                "name": "Booking.com",
                "url": "https://www.booking.com"
                },
                "booking_site_3": {
                "name": "VRBO",
                "url": "https://www.vrbo.com"
                }
            } */

            $table->json('booking_sites')->nullable();


            // Other Properties
            $table->boolean('show_logo_header')->default(false);
            $table->boolean('show_logo_footer')->default(false);
            $table->json('header_links')->nullable();

            $table->foreign('v_wl_information_id')->references('id')->on('venue_whitelabel_information')->onDelete('cascade');
            $table->foreign('venue_id')->references('id')->on('restaurants');
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
        Schema::dropIfExists('venue_whitelabel_customizations');
    }
};
