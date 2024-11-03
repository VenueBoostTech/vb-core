<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Drop the existing foreign key constraint on app_project_id
        Schema::table('supplies_requests', function (Blueprint $table) {
            $table->dropForeign(['app_project_id']);
        });

        // Change the foreign key to reference app_projects instead of projects
        Schema::table('supplies_requests', function (Blueprint $table) {
            $table->foreign('app_project_id')->references('id')->on('app_projects')->onDelete('cascade');
        });
    }

    public function down()
    {
        // Revert the changes by dropping the foreign key
        Schema::table('supplies_requests', function (Blueprint $table) {
            $table->dropForeign(['app_project_id']);
        });

        // Restore the foreign key to reference the original projects table
        Schema::table('supplies_requests', function (Blueprint $table) {
            $table->foreign('app_project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }
};
