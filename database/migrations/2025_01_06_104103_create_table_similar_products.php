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
        Schema::create('similar_products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products');
            $table->json('similar_products');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cart_suggestions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products');
            $table->json('cart_suggestions');
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
        Schema::table('similar_products', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('cart_suggestions', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });
    }
};
