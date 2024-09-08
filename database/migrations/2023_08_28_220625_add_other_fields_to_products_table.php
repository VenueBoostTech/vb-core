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
            $table->string('short_description', 255)->nullable(); // assuming short_description is a string with a maximum length of 255 and can be nullable
            $table->boolean('is_for_retail')->default(false); // boolean field with a default value of false
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
            // We need to drop the columns in reverse order
            $table->dropColumn('is_for_retail');
            $table->dropColumn('short_description');
        });
    }
};
