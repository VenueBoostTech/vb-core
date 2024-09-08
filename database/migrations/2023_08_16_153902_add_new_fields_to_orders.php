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
            $table->decimal('subtotal', 8, 2);
            $table->string('payment_status');
            $table->unsignedBigInteger('payment_method_id')->nullable()->after('status');

            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
            $table->unsignedBigInteger('promotion_id')->nullable()->after('reservation_id');
            $table->decimal('discount_total', 8, 2)->nullable()->after('total_amount');

            $table->foreign('promotion_id')->references('id')->on('promotions');
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
            //
        });
    }
};
