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
        // Update in employees table
        DB::table('employees')->where('id', 56)->update([
            'name' => 'Arsen Hoxhaj',
            'email' => 'arsenhoxhaj85@yahoo.it',
        ]);

        // Update in users table
        DB::table('users')->where('id', 33)->update([
            'name' => 'Arsen Hoxhaj',
        ]);

        // Update another record in employees table
        DB::table('employees')->where('id', 42)->update([
            'name' => 'Ejona',
            'email' => 'bybestapartments@gmail.com',
        ]);

        // Update another record in users table
        DB::table('users')->where('id', 18)->update([
            'name' => 'Ejona',
            'email' => 'bybestapartments@gmail.com',
        ]);
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
