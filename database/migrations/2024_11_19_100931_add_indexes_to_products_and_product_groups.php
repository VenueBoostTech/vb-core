<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToProductsAndProductGroups extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::table('products', function (Blueprint $table) {
            $table->index('product_status', 'idx_product_status');
            $table->index('stock_quantity', 'idx_stock_quantity');
            $table->index('restaurant_id', 'idx_restaurant_id');
            $table->index('currency_alpha', 'idx_currency_alpha');
        });

        Schema::table('product_groups', function (Blueprint $table) {
            $table->index('group_id', 'idx_group_id');
            $table->index('created_at', 'idx_created_at');
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
            $table->dropIndex('idx_product_status');
            $table->dropIndex('idx_stock_quantity');
            $table->dropIndex('idx_restaurant_id');
            $table->dropIndex('idx_currency_alpha');
        });

        Schema::table('product_groups', function (Blueprint $table) {
            $table->dropIndex('idx_group_id');
            $table->dropIndex('idx_created_at');
        });
    }
}

