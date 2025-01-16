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
            $table->id();
            $table->unsignedBigInteger('bybest_id');
            $table->json('similar_products');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cart_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bybest_id');
            $table->json('cart_suggestions');
            $table->softDeletes();
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
        Schema::dropIfExists('similar_products');

        Schema::dropIfExists('cart_suggestions');
    }
};
