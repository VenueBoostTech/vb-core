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
        Schema::table('potential_venue_leads', function (Blueprint $table) {
            $table->string('affiliate_code')->nullable();
            $table->unsignedBigInteger('affiliate_id')->nullable();
            $table->enum('affiliate_status', ['pending', 'started', 'registered'])->nullable();

            $table->foreign('affiliate_id')->references('id')->on('affiliates')->onDelete('cascade');

            $table->string('referral_code')->nullable();
            $table->unsignedBigInteger('referer_id')->nullable();
            $table->enum('referral_status', ['pending', 'started', 'registered'])->nullable();

            $table->foreign('referer_id')->references('id')->on('restaurants')->onDelete('cascade');

            $table->unsignedBigInteger('promo_code_id')->nullable();
            $table->string('promo_code')->nullable();

            $table->foreign('promo_code_id')->references('id')->on('promotional_codes')->onDelete('cascade');


        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('potential_venue_leads', function (Blueprint $table) {
            $table->dropForeign(['affiliate_id']);
            $table->dropForeign(['referer_id']);
            $table->dropForeign(['promo_code_id']);
            $table->dropColumn(['affiliate_code', 'affiliate_id', 'affiliate_status', 'referral_code', 'referer_id', 'referral_status', 'promo_code_id', 'promo_code']);
        });
    }
};
