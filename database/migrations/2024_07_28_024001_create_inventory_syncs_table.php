<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::create('inventory_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Insert the default sync
        DB::table('inventory_syncs')->insert([
            ['name' => 'SKU Sync', 'slug' => 'sku-sync'],
            ['name' => 'Price Sync', 'slug' => 'price-sync'],
            ['name' => 'Stock Sync', 'slug' => 'stock-sync'],
            ['name' => 'Sales Sync', 'slug' => 'sales-sync'],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_syncs');
    }
};
