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
        Schema::create('inventory_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_retail_id')->nullable();
            $table->unsignedBigInteger('inventory_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->integer('alert_level');
            $table->timestamps();
            $table->softDeletes(); // Add this line for soft deletes

            $table->foreign('inventory_retail_id')->references('id')->on('inventory_retail')->onDelete('cascade');
            $table->foreign('inventory_id')->references('id')->on('inventories')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_alerts');
    }
};
