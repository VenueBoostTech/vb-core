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

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('item_id');

            // Stripe subscription item fields...
            $table->string('stripe_subscription_id');
            $table->string('stripe_item_id');

            $table->timestamps();

            $table->foreign('subscription_id')->references('id')->on('subscriptions');
            $table->foreign('item_id')->references('id')->on('pricing_plans_prices');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscription_items');
    }
};
