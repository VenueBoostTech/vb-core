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
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->boolean('allow_restaurant_msg')->default(false);
            $table->boolean('allow_venueboost_msg')->default(false);
            $table->boolean('allow_remind_msg')->default(false);
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
            $table->dropColumn('allow_restaurant_msg');
            $table->dropColumn('allow_venueboost_msg');
            $table->dropColumn('allow_remind_msg');
        });
    }
};
