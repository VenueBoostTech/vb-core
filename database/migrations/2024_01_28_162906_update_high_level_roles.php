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
        $roles = ['Superadmin', 'Admin', 'EndUser', 'Affiliate User'];

        foreach ($roles as $role) {
            if (DB::table('high_level_roles')->where('name', $role)->doesntExist()) {
                DB::table('high_level_roles')->insert(['name' => $role]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
