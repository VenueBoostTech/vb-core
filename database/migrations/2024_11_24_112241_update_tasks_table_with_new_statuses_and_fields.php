<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        DB::statement("ALTER TABLE tasks MODIFY status ENUM('backlog', 'todo', 'in_progress', 'done', 'cancelled', 'draft', 'on_hold') DEFAULT 'todo'");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE tasks MODIFY status ENUM('todo', 'in_progress', 'done', 'on_hold') DEFAULT 'todo'");

    }
};
