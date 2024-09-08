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
        // Find the role with the old name and update it to the new name
        DB::table('roles')->where('name', 'Housekeeping Supervisor')->update(['name' => 'Housekeeping Staff Hotel']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert the change if needed
        DB::table('roles')->where('name', 'Housekeeping Staff Hotel')->update(['name' => 'Housekeeping Supervisor']);
    }
};
