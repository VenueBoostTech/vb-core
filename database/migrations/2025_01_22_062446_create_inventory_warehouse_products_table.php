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
        Schema::create('inventory_warehouse_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_warehouse_id');
            $table->foreign('inventory_warehouse_id')->references('id')->on('inventory_warehouses')->onDelete('cascade');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->integer('quantity');
            $table->timestamps();
            $table->softdeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('inventory_warehouse_products', function (Blueprint $table) {
            $table->dropForeign('inventory_warehouse_products_inventory_warehouse_id_foreign');
            $table->dropForeign('inventory_warehouse_products_product_id_foreign');
        });
    }
};
