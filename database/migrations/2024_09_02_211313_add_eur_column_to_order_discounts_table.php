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
        Schema::table('order_discounts', function (Blueprint $table) {
            $table->decimal('discount_value_eur', 10, 2)->after('discount_value');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_discounts', function (Blueprint $table) {
            $table->dropColumn('discount_value_eur');
        });
    }
};
