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

        Schema::create('venue_lead_info', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->unsignedBigInteger('potential_venue_lead_id');
            $table->string('gpt_plan_suggested');
            $table->string('assistant_reply');
            $table->string('venue_signed_plan')->nullable();
            $table->enum('venue_signed_plan_recurring_cycle', ['monthly', 'yearly'])->nullable();
            $table->date('date_of_suggestion');
            $table->date('date_of_signed_venue_plan')->nullable();
            $table->unsignedBigInteger('pricing_plan_id')->nullable();
            $table->enum('industry', ['Food', 'Sport & Entertainment', 'Accommodation', 'Retail'])->nullable();
            $table->timestamps();

            // Define foreign key constraints
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('pricing_plan_id')->references('id')->on('pricing_plans');
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
        Schema::dropIfExists('venue_lead_info');
    }
};
