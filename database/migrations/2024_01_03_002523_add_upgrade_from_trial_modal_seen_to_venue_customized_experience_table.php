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
        Schema::table('venue_customized_experience', function (Blueprint $table) {
            $table->dateTime('upgrade_from_trial_modal_seen')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('venue_customized_experience', function (Blueprint $table) {
            $table->dropColumn('upgrade_from_trial_modal_seen');
        });
    }
};
