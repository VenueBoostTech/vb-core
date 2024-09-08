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
        $roles = [
            [
                'name' => 'Owner',
                'role_type' => 'vacation_rental_hierarchy',
            ],
            [
                'name' => 'Manager',
                'role_type' => 'vacation_rental_hierarchy',
            ],
            [
                'name' => 'Housekeeping staff',
                'role_type' => 'vacation_rental_hierarchy',
            ],
        ];

        DB::table('roles')->insert($roles);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('roles', function (Blueprint $table) {
            //
        });
    }
};
