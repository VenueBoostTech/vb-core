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
        Schema::table('store_settings', function (Blueprint $table) {
            $table->boolean('enable_coupon')->default(false);
            $table->boolean('enable_cash_payment_method')->default(false);
            $table->boolean('enable_card_payment_method')->default(false);
            $table->string('new_order_email_recipient')->nullable();


            // Here we're using JSON fields to store lists of strings for selling_locations and shipping_locations
            $table->json('selling_locations')->nullable();
            $table->json('shipping_locations')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn('enable_coupon');
            $table->dropColumn('enable_cash_payment_method');
            $table->dropColumn('enable_card_payment_method');
            $table->dropColumn('new_order_email_recipient');
            $table->dropColumn('selling_locations');
            $table->dropColumn('shipping_locations');
        });
    }
};
