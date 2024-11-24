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
        $oldEmail = 'staffluentweb@gmail.com';
        $newEmail = 'march@staffluenttechsolutions.com';
        $newPassword = Hash::make('securepassword1234');

        $affected = DB::table('users')
            ->where('email', $oldEmail)
            ->update([
                'email' => $newEmail,
                'password' => $newPassword
            ]);

        if ($affected === 0) {
            throw new Exception("No user found with email: {$oldEmail}");
        } elseif ($affected > 1) {
            throw new Exception("Multiple users found with email: {$oldEmail}");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $oldEmail = 'staffluentweb@gmail.com';
        $newEmail = 'march@staffluenttechsolutions.com';
        // Store old hashed password for rollback
        $oldPassword = Hash::make('old_password'); // Replace with actual old password if needed

        DB::table('users')
            ->where('email', $newEmail)
            ->update([
                'email' => $oldEmail,
                'password' => $oldPassword
            ]);
    }
};
