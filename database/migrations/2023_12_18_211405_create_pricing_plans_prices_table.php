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
        Schema::create('pricing_plans_prices', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_id');
            $table->boolean('active');
            $table->string('billing_scheme')->default('per_unit');
            $table->string('currency');
            $table->string('custom_unit_amount')->nullable();
            $table->string('stripe_product_id');
            $table->unsignedBigInteger('pricing_plan_id');

            // Example of recurring
            //  "recurring": {
            //    "aggregate_usage": null,
            //    "interval": "month",
            //    "interval_count": 1,
            //    "trial_period_days": null,
            //    "usage_type": "licensed"
            //  },

            $table->json('recurring');
            $table->string('tax_behavior')->default('unspecified');
            $table->string('type')->default('recurring');
            $table->unsignedInteger('unit_amount');
            $table->string('unit_amount_decimal');
            $table->timestamps();

            $table->foreign('pricing_plan_id')->references('id')->on('pricing_plans');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pricing_plans_prices');
    }
};
