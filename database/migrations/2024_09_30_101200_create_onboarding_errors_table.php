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
        Schema::create('onboarding_errors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('potential_venue_lead_id')->nullable();
            $table->string('step');
            $table->string('error_type'); // Add this line
            $table->text('error_message');
            $table->text('stack_trace')->nullable();
            $table->json('validation_errors')->nullable(); // Add this line
            $table->timestamps();

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
        Schema::dropIfExists('onboarding_errors');
    }
};
