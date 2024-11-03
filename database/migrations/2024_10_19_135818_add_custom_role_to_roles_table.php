<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddCustomRoleToRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Insert the custom role into the roles table
        DB::table('roles')->insert([
            'name' => 'CUSTOM ROLE',
            'description' => 'Role for employees with custom role definitions',
            'role_type'=> 'vp_app',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Delete the custom role if rolling back the migration
        DB::table('roles')->where('name', 'CUSTOM ROLE')->delete();
    }
}
