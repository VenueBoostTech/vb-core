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
        Schema::table('order_products', function (Blueprint $table) {
            $table->decimal('product_total_price_eur', 10, 2)->after('product_total_price')->nullable();
            $table->decimal('product_discount_price_eur', 10, 2)->after('product_discount_price')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropColumn(['product_total_price_eur', 'product_discount_price_eur']);
        });
    }
};
