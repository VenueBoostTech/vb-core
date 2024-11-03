<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('project_assignments', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->foreign('project_id')
                ->references('id')
                ->on('app_projects')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('project_assignments', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('cascade');
        });
    }
};
