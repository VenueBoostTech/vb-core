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
        Schema::table('restaurant_referrals', function (Blueprint $table) {
            $table->unsignedBigInteger('potential_venue_lead_id')->nullable();

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
        Schema::table('restaurant_referrals', function (Blueprint $table) {
            $table->dropForeign(['potential_venue_lead_id']);
            $table->dropColumn('potential_venue_lead_id');
        });
    }
};
