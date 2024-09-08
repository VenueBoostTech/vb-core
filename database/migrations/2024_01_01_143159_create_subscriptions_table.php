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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('venue_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('pricing_plan_id')->constrained('pricing_plans')->onDelete('cascade');
            $table->string('pricing_plan_stripe_id');
            $table->string('stripe_subscription_id');
            $table->enum('status', ['incomplete', 'incomplete_expired', 'trialing', 'active', 'past_due', 'canceled', 'unpaid']);
            $table->dateTime('trial_start')->nullable();
            $table->dateTime('trial_end')->nullable();
            $table->string('trial_end_behavior')->nullable();
            $table->boolean('cancel_at_period_end');
            $table->boolean('automatic_tax_enabled');
            $table->bigInteger('billing_cycle_anchor')->nullable();
            $table->json('billing_thresholds')->nullable();
            $table->dateTime('cancel_at')->nullable();
            $table->dateTime('canceled_at')->nullable();
            $table->json('cancellation_details')->nullable();
            $table->string('collection_method');
            $table->string('currency');
            $table->dateTime('current_period_start');
            $table->dateTime('current_period_end');
            $table->boolean('requested_custom')->default(false);
            $table->json('pause_collection')->nullable(); // Added pause_collection field
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
        Schema::dropIfExists('subscriptions');
    }
};
