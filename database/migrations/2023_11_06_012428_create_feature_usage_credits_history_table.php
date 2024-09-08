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
        Schema::create('feature_usage_credits_history', function (Blueprint $table) {
            $table->unsignedBigInteger('feature_usage_credit_id');
            $table->unsignedBigInteger('restaurant_referral_id')->nullable();
            $table->enum('transaction_type', ['increase', 'decrease']);
            $table->integer('amount');
            $table->string('used_at_feature');
            $table->timestamps();

            // Add foreign key constraint for feature_usage_credit_id and restaurant_referral_id
            $table->foreign('feature_usage_credit_id')->references('id')->on('feature_usage_credits')->onDelete('cascade');
            $table->foreign('restaurant_referral_id')->references('id')->on('restaurant_referrals')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('feature_usage_credits_history');
    }
};
