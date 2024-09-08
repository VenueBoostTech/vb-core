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
        Schema::table('guests', function (Blueprint $table) {
            // is for accommodation
            $table->boolean('is_for_accommodation')->default(false)->after('sn_platform_user');
            // is for food and beverage
            $table->boolean('is_for_food_and_beverage')->default(false)->after('is_for_accommodation');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('is_for_accommodation');
            $table->dropColumn('is_for_food_and_beverage');
        });
    }
};
