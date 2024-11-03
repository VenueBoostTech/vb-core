<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('app_projects', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('department_id');
            $table->float('estimated_hours')->nullable()->after('team_id');
            $table->decimal('estimated_budget', 10, 2)->nullable()->after('estimated_hours');
            $table->unsignedBigInteger('project_manager_id')->nullable()->after('team_id');

            $table->foreign('project_manager_id')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('app_projects', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn(['team_id', 'estimated_hours', 'estimated_budget']);
            $table->dropForeign(['project_manager_id']);
            $table->dropColumn('project_manager_id');
        });
    }
};
