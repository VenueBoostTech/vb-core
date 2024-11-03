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
        Schema::table('inventory_sync_errors', function (Blueprint $table) {
            $table->foreignId('synchronization_id')
                ->nullable()
                ->change();
            $table->foreignId('stock_calculation_id')
                ->nullable()
                ->constrained('stock_calculations')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('inventory_sync_errors', function (Blueprint $table) {
            $table->dropForeign(['stock_calculation_id']);
            $table->dropColumn('stock_calculation_id');
            $table->foreignId('synchronization_id')->nullable(false)->change();
        });
    }
};
