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
        Schema::table('venue_whitelabel_information', function (Blueprint $table) {
            $table->text('benefits')->nullable()->after('additional');
            $table->integer('min_money_value')->nullable(false)->default(0)->after('benefits');
            $table->integer('max_money_value')->nullable(false)->default(0)->after('benefits');
            $table->boolean('has_free_breakfast')->default(false)->after('has_free_wifi');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('venue_whitelabel_information', function (Blueprint $table) {
            $table->dropColumn('benefits');
            $table->dropColumn('min_money_value');
            $table->dropColumn('max_money_value');
            $table->dropColumn('has_free_breakfast');
        });
    }
};
