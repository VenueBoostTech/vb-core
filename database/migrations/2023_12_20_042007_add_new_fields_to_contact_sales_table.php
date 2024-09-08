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
        Schema::table('contact_sales', function (Blueprint $table) {
            $table->string('number_of_employees')->nullable();
            $table->string('annual_revenue')->nullable();
            $table->string('website')->nullable();
            $table->json('social_media')->nullable();
            $table->string('business_challenge')->nullable();
            $table->string('other_business_challenge')->nullable();
            $table->string('how_did_you_hear_about_us')->nullable();
            $table->string('how_did_you_hear_about_us_other')->nullable();
            $table->string('biggest_additional_change')->nullable();
            $table->integer('years_in_business')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contact_sales', function (Blueprint $table) {
            $table->dropColumn([
                'number_of_employees',
                'annual_revenue',
                'website',
                'social_media',
                'business_challenge',
                'other_business_challenge',
                'how_did_you_hear_about_us',
                'how_did_you_hear_about_us_other',
                'biggest_additional_change',
                'years_in_business'
            ]);
        });
    }
};
