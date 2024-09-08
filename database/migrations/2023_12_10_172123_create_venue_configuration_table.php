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
        Schema::create('venue_configuration', function (Blueprint $table) {
            $table->id();
            $table->string('email_language')->nullable();
            $table->string('stripe_connected_acc_id')->nullable();
            $table->datetime('connected_account_created_at')->nullable();
            $table->datetime('connected_account_updated_at')->nullable();

            $table->boolean('onboarding_completed')->default(false);
            $table->unsignedBigInteger('venue_id');
            $table->timestamps();

            // Define foreign key constraints
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('venue_configuration');
    }
};
