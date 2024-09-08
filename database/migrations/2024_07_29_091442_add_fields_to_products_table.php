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
        Schema::table('products', function (Blueprint $table) {
            $table->double('sale_price')->nullable();
            $table->dateTime('date_sale_start')->nullable();
            $table->dateTime('date_sale_end')->nullable();
            $table->string('product_url');
            $table->enum('product_type', ['single', 'variable', 'with_accessories'])->default('single');
            $table->double('weight')->nullable();
            $table->double('length')->nullable();
            $table->double('width')->nullable();
            $table->double('height')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();

            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropColumn([
                'sale_price', 'date_sale_start', 'date_sale_end', 'product_url',
                'product_type', 'weight', 'length', 'width', 'height', 'brand_id'
            ]);
        });
    }
};
