<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('team_leader_id')->nullable()->after('venue_id');
            $table->foreign('team_leader_id')->references('id')->on('employees')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['team_leader_id']);
            $table->dropColumn('team_leader_id');
        });
    }
};
