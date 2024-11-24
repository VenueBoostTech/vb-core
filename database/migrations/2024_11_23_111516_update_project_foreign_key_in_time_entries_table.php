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
        Schema::table('time_entries', function (Blueprint $table) {
            // Drop the existing foreign key
            $table->dropForeign(['project_id']);

            // Add the new foreign key referencing `app_projects`
            $table->foreign('project_id')->references('id')->on('app_projects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('time_entries', function (Blueprint $table) {
            // Drop the updated foreign key
            $table->dropForeign(['project_id']);

            // Revert to the original foreign key referencing `projects`
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }
};
