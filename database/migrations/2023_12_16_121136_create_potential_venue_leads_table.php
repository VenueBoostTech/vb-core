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
        Schema::create('potential_venue_leads', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('representative_first_name');
            $table->string('representative_last_name');
            $table->string('source')->default('web/get-started');
            $table->boolean('email_verified')->default(false);
            $table->boolean('started_onboarding')->default(false);
            $table->boolean('completed_onboarding')->default(false);
            $table->enum('current_onboarding_step', ['initial_form_submitted', 'email_verified', 'business_details', 'interest_engagement', 'subscription_plan_selection'])->default('initial_form_submitted');
            $table->unsignedBigInteger('venue_id')->nullable();
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');

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
        Schema::dropIfExists('potential_venue_leads');
    }
};
