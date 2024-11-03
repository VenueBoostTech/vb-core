<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_stock', function (Blueprint $table) {
            $table->id();
            $table->string('article_no');
            $table->string('alpha_warehouse');
            $table->integer('stock_quantity');
            $table->dateTime('alpha_date');
            $table->dateTime('synchronize_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->string('bybest_id');
            $table->foreignId('venue_id')->constrained('restaurants');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_stock');
    }
};
