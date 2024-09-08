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
        // Modify the source field
        DB::statement("ALTER TABLE rental_units MODIFY COLUMN unit_status ENUM('Snoozed', 'Unlisted', 'Listed', 'Deactivated') DEFAULT 'Unlisted'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE rental_units MODIFY COLUMN unit_status ENUM('Snoozed', 'Unlisted', 'Deactivated') DEFAULT 'Unlisted'");
    }
};
