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
        Schema::table('inventory_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_id')->nullable()->change();
            $table->unsignedBigInteger('inventory_retail_id')->nullable();
            $table->foreign('inventory_retail_id')
            ->references('id')
                ->on('inventory_retail')
                ->onDelete('set null'); // Set to null if the referenced row is deleted    
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('inventory_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_id')->nullable(false)->change();
            // Drop the foreign key first
            $table->dropForeign(['inventory_retail_id']);

            // Then drop the column
            $table->dropColumn('inventory_retail_id');
        });
    }
};
