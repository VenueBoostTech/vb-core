<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $user = DB::table('users')->where('email', 'staffluentweb+2@gmail.com')->first();

        if ($user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'password' => Hash::make('Test1234!'),
                    'updated_at' => now()
                ]);

            Log::info('Password updated successfully for user: staffluentweb+2@gmail.com');
            // Print to console instead of using $this->command
            echo "Password updated successfully for user: staffluentweb+2@gmail.com\n";
        } else {
            Log::warning('User with email staffluentweb+2@gmail.com not found');
            echo "User with email staffluentweb+2@gmail.com not found\n";
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
