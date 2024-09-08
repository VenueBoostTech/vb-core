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
        Schema::create('venue_wallets', function (Blueprint $table) {
            $table->id();
            $table->integer('balance')->default(0);
            $table->unsignedBigInteger('venue_id');
            $table->timestamps();

            // Add foreign key constraint for venue_id
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
        });

        Schema::create('wallet_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->unsignedBigInteger('restaurant_referral_id')->nullable();
            $table->enum('transaction_type', ['increase', 'decrease']);
            $table->integer('amount');
            // TODO: connect this with stripe or own subscriptions table
            $table->integer('subscription_id')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            // Add foreign key constraint for wallet_id
            $table->foreign('wallet_id')->references('id')->on('venue_wallets')->onDelete('cascade');
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
        Schema::dropIfExists('wallet_history');
        Schema::dropIfExists('venue_wallets');
    }
};
