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
            $table->string('variation_sku')->nullable()->change();
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
            $table->string('variation_sku')->nullable(false)->change();
        });
    }
};
