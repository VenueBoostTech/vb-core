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
        Schema::table('vb_store_products_variants', function (Blueprint $table) {
            $table->string('price_without_tax_alpha')->nullable();
            $table->integer('gender_id')->nullable();
            $table->integer('bb_points')->nullable();
            $table->integer('shipping_class')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vb_store_products_variants', function (Blueprint $table) {
            $table->dropColumn([
                'price_without_tax_alpha',
                'gender_id',
                'bb_points',
                'shipping_class',
            ]);
        });
    }
};
