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
        Schema::table('loyalty_tiers', function (Blueprint $table) {
            $table->integer('points_per_booking')->default(1)->after('priority_support');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('loyalty_tiers', function (Blueprint $table) {
            $table->integer('points_per_booking')->default(1)->after('priority_support');
        });
    }
};
