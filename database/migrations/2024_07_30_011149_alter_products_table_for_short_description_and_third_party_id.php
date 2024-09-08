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
            // Change the length of short_description
            $table->text('short_description')->change();

            // Add the new third_party_product_id column
            $table->unsignedBigInteger('third_party_product_id')->nullable()->after('id');

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
            // Reverse the changes
            $table->string('short_description', 255)->change();
            $table->dropColumn('third_party_product_id');
        });
    }
};
