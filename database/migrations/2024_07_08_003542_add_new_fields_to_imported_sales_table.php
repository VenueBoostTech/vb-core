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
        Schema::table('imported_sales', function (Blueprint $table) {
            // add new fields
            $table->unsignedBigInteger('physical_store_id')->nullable();
            $table->unsignedBigInteger('ecommerce_platform_id')->nullable();
            $table->string('sale_source')->nullable();

            // add foreign keys
            $table->foreign('physical_store_id')->references('id')->on('physical_stores');
            $table->foreign('ecommerce_platform_id')->references('id')->on('ecommerce_platforms');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('imported_sales', function (Blueprint $table) {
            $table->dropForeign(['physical_store_id']);
            $table->dropForeign(['ecommerce_platform_id']);
            $table->dropColumn(['physical_store_id', 'ecommerce_platform_id', 'sale_source']);
        });
    }
};
