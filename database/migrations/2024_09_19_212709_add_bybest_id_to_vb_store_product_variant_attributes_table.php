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
        Schema::table('vb_store_product_variant_attributes', function (Blueprint $table) {
            $table->string('bybest_id')->nullable()->after('venue_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vb_store_product_variant_attributes', function (Blueprint $table) {
            $table->dropColumn('bybest_id');
        });
    }
};
