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
            $table->unsignedBigInteger('sold_at')->nullable();
            $table->boolean('sold_at_whitelabel')->default(true);
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
            $table->dropColumn('sold_at');
            $table->dropColumn('sold_at_whitelabel');
        });
    }
};
