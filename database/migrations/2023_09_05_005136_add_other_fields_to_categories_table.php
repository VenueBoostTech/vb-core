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
        Schema::table('categories', function (Blueprint $table) {
            // Adding the parent_id column
            $table->unsignedBigInteger('parent_id')->nullable()->after('restaurant_id');

            // Adding the foreign key constraint
            $table->foreign('parent_id')->references('id')->on('categories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            // Removing the foreign key and then the column
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
