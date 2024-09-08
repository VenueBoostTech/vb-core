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
        Schema::create('end_user_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->string('card_type');
            $table->string('uuid')->unique();

            // Add foreign key to connect with Wallet table
            $table->unsignedBigInteger('wallet_id')->nullable();
            $table->foreign('wallet_id')->references('id')->on('wallets');

            // Add foreign key to connect with EarnsPointHistory table
            $table->unsignedBigInteger('earn_points_history_id')->nullable();
            $table->foreign('earn_points_history_id')->references('id')->on('earn_points_histories');

            // Additional Fields
            $table->unsignedInteger('guest_id')->nullable();
            $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade');

            $table->date('expiration_date')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_verified')->default(false);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->text('notes')->nullable();

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
        Schema::dropIfExists('end_user_cards');
    }
};
