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
        Schema::table('tables', function (Blueprint $table) {
            $table->boolean('show_table_name')->default(false);
            $table->boolean('show_table_number')->default(false);
            $table->boolean('show_floorplan')->default(false);
            $table->decimal('pricing', 10, 2)->nullable();
            $table->boolean('show_premium_table_bid')->default(false);
            $table->enum('premium_table_bid', ['high_bid_wins', 'choose_winner_manually'])->nullable();
            $table->decimal('min_bid', 10, 2)->nullable();
            $table->decimal('max_bid', 10, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tables', function (Blueprint $table) {
            //
        });
    }
};
