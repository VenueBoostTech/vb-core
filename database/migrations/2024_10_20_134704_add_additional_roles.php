<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $additionalRoles = [
            ['name' => 'Cleaner', 'description' => 'Maintains cleanliness of the office or workspace', 'role_type' => 'vb_app'],
            ['name' => 'Team Leader', 'description' => 'Leads and coordinates a team', 'role_type' => 'vb_app'],
            ['name' => 'Operations Manager', 'description' => 'Oversees daily operations of the company', 'role_type' => 'vb_app'],
            ['name' => 'Administrator', 'description' => 'Manages administrative tasks and operations', 'role_type' => 'vb_app'],
        ];

        DB::table('roles')->insert($additionalRoles);
    }

    public function down()
    {
        DB::table('roles')->whereIn('name', ['Cleaner', 'Team Leader', 'Operations Manager', 'Administrator'])->delete();
    }
};
