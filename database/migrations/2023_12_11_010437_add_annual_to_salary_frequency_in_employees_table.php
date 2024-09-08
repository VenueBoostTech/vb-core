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
        DB::statement("ALTER TABLE employees MODIFY COLUMN salary_frequency ENUM('daily', 'weekly', 'bi-weekly', 'monthly', 'annual', 'custom', 'hourly') DEFAULT 'monthly'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('salary_frequency_in_employees', function (Blueprint $table) {
            DB::statement("ALTER TABLE employees MODIFY COLUMN salary_frequency ENUM('daily', 'weekly', 'bi-weekly', 'monthly') DEFAULT 'monthly'");
        });
    }
};
