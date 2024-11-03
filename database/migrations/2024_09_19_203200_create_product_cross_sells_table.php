<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_cross_sells', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('base_product_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('type_id');
            $table->unsignedBigInteger('venue_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('base_product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('type_id')->references('id')->on('product_cross_sells_type')->onDelete('cascade');
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_cross_sells');
    }
};
