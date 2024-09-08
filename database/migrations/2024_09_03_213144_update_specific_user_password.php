<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        $email = 'development@bybest-shop.xyz';
        $newPassword = 'ELr2drjCC4kZ2qH!';

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
        $email = 'development@bybest-shop.xyz';

        DB::table('users')
            ->where('email', $email)
            ->update(['password' => Hash::make('placeholder_password')]);
    }
};
