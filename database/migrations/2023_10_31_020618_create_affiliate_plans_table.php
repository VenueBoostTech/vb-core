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
        Schema::create('affiliate_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('affiliate_id');
            $table->unsignedBigInteger('affiliate_program_id');
            $table->decimal('percentage', 8, 2)->nullable();
            $table->integer('nr_of_months')->nullable();
            $table->decimal('fixed_value', 8, 2)->nullable();
            $table->decimal('custom_plan_amount', 8, 2)->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->boolean('lifetime')->default(false);
            $table->enum('preferred_method', ['Fixed Percentage', 'Fixed Amount', 'Sliding Scale']);
            $table->unsignedInteger('customer_interval_start')->nullable();
            $table->unsignedInteger('customer_interval_end')->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('pricing_plans');
            $table->foreign('affiliate_program_id')->references('id')->on('affiliate_programs');
            $table->foreign('affiliate_id')->references('id')->on('affiliates');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('affiliate_plans');
    }
};
