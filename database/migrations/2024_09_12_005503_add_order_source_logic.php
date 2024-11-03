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
        Schema::create('order_source', function (Blueprint $table) {
            $table->id();
            $table->string('source')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->integer(column: 'bybest_id')->nullable();
            $table->integer('source_id')->nullable();
            $table->double('postal')->nullable();
            $table->string('ip')->nullable();
            $table->integer('shipping_id')->nullable();
            $table->string('shipping_name')->nullable();
            $table->string('shipping_surname')->nullable();
            $table->integer('shipping_state')->nullable();
            $table->integer('shipping_city')->nullable();
            $table->integer('bb_shipping_state')->nullable();
            $table->integer('bb_shipping_city')->nullable();
            $table->string('shipping_phone_no')->nullable();
            $table->string('shipping_email')->nullable();
            $table->string('shipping_address')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->string('billing_name')->nullable();
            $table->string('billing_surname')->nullable();
            $table->integer('billing_state')->nullable();
            $table->integer('billing_city')->nullable();
            $table->integer('bb_billing_state')->nullable();
            $table->integer('bb_billing_city')->nullable();
            $table->string('billing_phone_no')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_address')->nullable();
            $table->string('billing_postal_code')->nullable();
            $table->double('exchange_rate_eur')->nullable()->default(0);
            $table->double('exchange_rate_all')->nullable()->default(0);
            $table->tinyInteger('has_postal_invoice')->nullable(false)->default(0);
            $table->integer('tracking_latitude')->nullable();
            $table->integer('tracking_longtitude')->nullable();
            $table->string('tracking_countryCode')->nullable();
            $table->string('tracking_cityName')->nullable();
            $table->text('internal_note')->nullable()->after('notes');
            $table->integer('bb_coupon_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_source');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'bybest_id',
                'source_id',
                'postal',
                'ip',
                'shipping_id',
                'shipping_name',
                'shipping_surname',
                'shipping_state',
                'shipping_city',
                'bb_shipping_state',
                'bb_shipping_city',
                'shipping_phone_no',
                'shipping_email',
                'shipping_address',
                'shipping_postal_code',
                'billing_name',
                'billing_surname',
                'billing_state',
                'billing_city',
                'bb_billing_state',
                'bb_billing_city',
                'billing_phone_no',
                'billing_email',
                'billing_address',
                'billing_postal_code',
                'exchange_rate_eur',
                'exchange_rate_all',
                'has_postal_invoice',
                'tracking_latitude',
                'tracking_longtitude',
                'tracking_countryCode',
                'tracking_cityName',
                'internal_note',
                'bb_coupon_id'
            ]);
        });
    }
};
