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
        DB::statement("ALTER TABLE rooms MODIFY COLUMN type ENUM('living_room', 'bedroom', 'child_room', 'other_spaces', 'bathroom', 'full_bathroom', 'kitchen', 'full_kitchen', 'gym', 'exterior', 'patio', 'utility_room', 'balcony') DEFAULT 'living_room'");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE rooms MODIFY COLUMN type ENUM('living_room', 'bedroom', 'child_room', 'other_spaces', 'bathroom', 'full_bathroom', 'kitchen', 'full_kitchen', 'gym', 'exterior', 'patio', 'utility_room') DEFAULT 'living_room'");

    }
};
