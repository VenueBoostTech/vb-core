<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // First drop the existing foreign key
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        // Then update the foreign key to reference app_projects
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('project_id')
                ->references('id')
                ->on('app_projects')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        // Reverse the changes if needed to rollback
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);

            // Restore the original foreign key reference
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('cascade');
        });
    }
};
