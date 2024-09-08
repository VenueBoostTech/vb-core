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

        // Use raw SQL to modify the 'type' column
        DB::statement("ALTER TABLE `photos` MODIFY `type` ENUM('gallery', 'cover', 'logo', 'product', 'other')");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert the 'type' column to its original state using raw SQL
        DB::statement("ALTER TABLE `photos` MODIFY `type` ENUM('gallery', 'cover', 'logo', 'product')");
    }
};
