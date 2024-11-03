<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('operations_manager_id')->nullable()->after('team_leader_id');
            $table->foreign('operations_manager_id')->references('id')->on('employees')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['operations_manager_id']);
            $table->dropColumn('operations_manager_id');
        });
    }
};
