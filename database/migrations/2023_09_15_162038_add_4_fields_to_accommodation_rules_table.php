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
        Schema::table('accommodation_rules', function (Blueprint $table) {
            $table->text('key_pick_up');
            $table->text('check_in_method');
            $table->text('check_out_method');
            $table->text('wifi_detail');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accommodation_rules', function (Blueprint $table) {
            $table->dropColumn(['key_pick_up', 'check_in_method', 'check_out_method', 'wifi_detail']);
        });
    }
};
