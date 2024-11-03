<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTasksTable extends Migration
{
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Ensure to add new fields
            $table->enum('priority', ['high', 'medium', 'low'])->default('medium')->after('status'); // Add priority field
            $table->text('labels')->nullable()->after('priority'); // Add labels field
        });
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Rollback changes if needed
            $table->dropColumn([ 'priority', 'labels']);
        });
    }
}
