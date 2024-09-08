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
        Schema::table('inventory_retail', function (Blueprint $table) {
            $table->boolean('used_in_whitelabel')->default(true);
            $table->json('used_in_stores')->nullable();
            $table->json('used_in_ecommerces')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('inventory_retail', function (Blueprint $table) {
            $table->dropColumn('used_in_whitelabel');
            $table->dropColumn('used_in_stores');
            $table->dropColumn('used_in_ecommerces');
        });
    }
};
