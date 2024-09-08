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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('venue_id');
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->date('start_time');
            $table->date('expiry_time');
            $table->enum('discount_type', ['fixed_cart_discount', 'percentage_cart_discount']);
            $table->decimal('discount_amount', 8, 2);
            $table->decimal('minimum_spent', 8, 2)->nullable();
            $table->decimal('maximum_spent', 8, 2)->nullable();
            $table->unsignedInteger('usage_limit_per_coupon')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->timestamps();

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('promotion_id')->references('id')->on('promotions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('coupons');
    }
};
