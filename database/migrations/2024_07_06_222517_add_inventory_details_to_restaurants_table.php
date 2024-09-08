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
        Schema::table('restaurants', function (Blueprint $table) {
            $table->integer('inventory_warehouses')->default(0);
            $table->boolean('has_ecommerce')->default(false);
            $table->integer('physical_stores')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('inventory_warehouses');
            $table->dropColumn('has_ecommerce');
            $table->dropColumn('physical_stores');
        });
    }
};
