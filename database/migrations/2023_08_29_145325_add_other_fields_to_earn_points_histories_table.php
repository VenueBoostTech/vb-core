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

        Schema::table('earn_points_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable();
            // Add foreign key constraint for order_id
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('earn_points_histories', function (Blueprint $table) {
            $table->dropColumn('order_id');
        });
    }
};
