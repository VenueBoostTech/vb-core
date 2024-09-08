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
        Schema::create('venue_customized_experience', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->unsignedBigInteger('potential_venue_lead_id');
            $table->string('number_of_employees')->nullable();
            $table->string('annual_revenue')->nullable();
            $table->string('website')->nullable();
            $table->json('social_media')->nullable();
            $table->string('business_challenge')->nullable();
            $table->string('other_business_challenge')->nullable();
            $table->string('contact_reason');
            $table->string('how_did_you_hear_about_us')->nullable();
            $table->string('how_did_you_hear_about_us_other')->nullable();
            $table->string('biggest_additional_change')->nullable();
            $table->timestamps();

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('potential_venue_lead_id')->references('id')->on('potential_venue_leads')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('venue_customized_experience');
    }
};
