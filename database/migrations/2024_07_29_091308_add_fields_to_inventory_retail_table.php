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
        Schema::table('inventory_retail', function (Blueprint $table) {
            $table->string('currency_alpha')->nullable();
            $table->string('currency')->nullable();
            $table->string('sku_alpha')->nullable();
            $table->string('unit_code_alpha')->nullable();
            $table->string('unit_code')->nullable();
            $table->string('tax_code_alpha')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('warehouse_alpha')->nullable();
            $table->timestamp('last_synchronization')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->enum('synced_method', ['csv_import', 'manual', 'api_cronjob'])->default('api_cronjob');
            $table->enum('product_stock_status', ['available', 'comes_soon', 'not_available', 'never_comes'])->default('available');

            $table->foreign('warehouse_id')->references('id')->on('inventory_warehouses')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('inventory_retail', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn([
                'currency_alpha', 'currency', 'sku_alpha', 'unit_code_alpha', 'unit_code',
                'tax_code_alpha', 'warehouse_id', 'warehouse_alpha', 'last_synchronization',
                'synced_at', 'synced_method', 'product_stock_status'
            ]);
        });
    }
};
