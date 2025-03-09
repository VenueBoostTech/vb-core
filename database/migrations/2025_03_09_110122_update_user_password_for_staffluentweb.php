<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateUserPasswordForStaffluentweb extends Migration
{
    /**
     * Run the migration.
     *
     * @return void
     */
    public function up()
    {
        $user = DB::table('users')->where('email', 'staffluentweb+6@gmail.com')->first();

        if ($user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'password' => Hash::make('Test1234!'),
                    'updated_at' => now()
                ]);

            Log::info('Password updated successfully for user: staffluentweb+6@gmail.com');
            // Print to console instead of using $this->command
            echo "Password updated successfully for user: staffluentweb+6@gmail.com\n";
        } else {
            Log::warning('User with email staffluentweb+6@gmail.com not found');
            echo "User with email staffluentweb+6@gmail.com not found\n";
        }
    }

    /**
     * Reverse the migration.
     *
     * @return void
     */
    public function down()
    {
        // Cannot safely restore the original password as it was hashed
        // This migration cannot be reversed
    }
}
