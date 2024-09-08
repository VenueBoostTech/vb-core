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
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        // Drop existing tables
        Schema::dropIfExists('variations');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('attribute_values');

        // Enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // create vb_store_product_attributes  if exists

        // Create store_product_attributes table
        Schema::create('vb_store_product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('vb_store_attributes_options')->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('restaurants')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });



        // Create store_products_variants table
        Schema::create('vb_store_products_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('variation_sku');
            $table->string('article_no')->nullable();
            $table->string('currency_alpha')->nullable();
            $table->string('currency')->nullable();
            $table->string('sku_alpha')->nullable();
            $table->string('unit_code_alpha')->nullable();
            $table->string('unit_code')->nullable();
            $table->string('tax_code_alpha')->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
            $table->string('warehouse_alpha')->nullable();
            $table->timestamp('last_synchronization')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->enum('synced_method', ['csv_import', 'manual', 'api_cronjob'])->default('api_cronjob');
            $table->enum('product_stock_status', ['available', 'comes soon', 'not available', 'never comes'])->default('available');
            $table->string('name')->nullable();
            $table->string('variation_image')->nullable();
            $table->double('sale_price')->nullable();
            $table->dateTime('date_sale_start')->nullable();
            $table->dateTime('date_sale_end')->nullable();
            $table->double('price');
            $table->integer('stock_quantity');
            $table->boolean('manage_stock');
            $table->boolean('sell_eventually');
            $table->boolean('allow_back_orders');
            $table->double('weight')->nullable();
            $table->double('length')->nullable();
            $table->double('width')->nullable();
            $table->double('height')->nullable();
            $table->text('product_long_description')->nullable();
            $table->text('short_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Create store_product_variant_attributes table
        Schema::create('vb_store_product_variant_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('vb_store_products_variants')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('vb_store_attributes_options')->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('restaurants')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
