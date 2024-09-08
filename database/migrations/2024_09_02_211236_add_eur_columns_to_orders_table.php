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
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_amount_eur', 10, 2)->after('total_amount')->nullable();
            $table->decimal('subtotal_eur', 10, 2)->after('subtotal')->nullable();
            $table->decimal('discount_total_eur', 10, 2)->after('discount_total')->nullable();
            $table->decimal('delivery_fee_eur', 10, 2)->after('delivery_fee')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['total_amount_eur', 'subtotal_eur', 'discount_total_eur', 'delivery_fee_eur']);
        });
    }
};
