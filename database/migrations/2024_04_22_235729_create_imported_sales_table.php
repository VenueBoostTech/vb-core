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
        Schema::create('imported_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedBigInteger('venue_id');
            $table->enum('unit_type', ['piece', 'box', 'bottle', 'unit', ]);
            $table->integer('quantity_sold');
            $table->enum('period', ['1_month', '3_month', '6_month', '12_month']);
            $table->timestamps();

            $table->foreign('venue_id')->references('id')->on('restaurants');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('imported_sales');
    }
};
