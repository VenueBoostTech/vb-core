<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UpdateRandomUserPassword extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $email = 'ggerveni+vbacc@gmail.com';
        $newPassword = 'Test1234!';

        DB::table('users')
            ->where('email', $email)
            ->update(['password' => Hash::make($newPassword)]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Note: We can't revert to the original password as it was hashed
        // You may want to implement a different strategy for the down() method
        // or leave it empty if you don't want to provide a rollback option
    }
}
