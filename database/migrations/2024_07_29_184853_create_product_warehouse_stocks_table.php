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

        Schema::create('product_warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('alpha_warehouse');
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses');
            $table->integer('stock_quantity');
            $table->string('article_no');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->timestamp('last_synchronization')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->enum('synced_method', ['csv_import', 'manual', 'api_cronjob'])->default('api_cronjob');
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
        Schema::dropIfExists('product_warehouse_stocks');
    }
};
