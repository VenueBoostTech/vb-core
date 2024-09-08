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
        Schema::table('potential_venue_leads', function (Blueprint $table) {
            $table->dateTime('onboarded_completed_at')->nullable()->after('completed_onboarding');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('potential_venue_leads', function (Blueprint $table) {
            $table->dropColumn('onboarded_completed_at');

        });
    }
};
