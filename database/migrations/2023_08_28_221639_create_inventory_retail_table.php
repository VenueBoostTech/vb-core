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
        Schema::create('inventory_retail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->unsignedBigInteger('product_id'); // Foreign Key to products table
            $table->unsignedBigInteger('supplier_id')->nullable(); // Foreign Key to suppliers table
            $table->string('sku')->unique()->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->boolean('manage_stock')->default(false);
            $table->integer('low_stock_threshold')->nullable();
            $table->boolean('sold_individually')->default(false);
            $table->timestamps();

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_retail');
    }
};
