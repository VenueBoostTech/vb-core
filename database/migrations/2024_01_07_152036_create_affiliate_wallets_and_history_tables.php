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
        Schema::create('affiliate_wallets', function (Blueprint $table) {
            $table->id();
            $table->integer('balance')->default(0);
            $table->unsignedBigInteger('affiliate_id');
            $table->timestamps();

            $table->foreign('affiliate_id')->references('id')->on('affiliates')->onDelete('cascade');
        });

        Schema::create('affiliate_wallet_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('affiliate_wallet_id');
            $table->enum('transaction_type', ['increase', 'withdraw']);
            $table->integer('amount');
            $table->unsignedBigInteger('registered_venue_id')->nullable();
            $table->unsignedBigInteger('affiliate_plan_id')->nullable();
            $table->timestamps();

            $table->foreign('affiliate_wallet_id')->references('id')->on('affiliate_wallets')->onDelete('cascade');
            $table->foreign('registered_venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('affiliate_plan_id')->references('id')->on('pricing_plans')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('affiliate_wallet_history');
        Schema::dropIfExists('affiliate_wallets');
    }
};
