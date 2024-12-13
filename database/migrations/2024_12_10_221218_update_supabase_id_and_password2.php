<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Update the supabase_id for the given venue_id and user_id
        DB::table('venue_user_supabase_connections')
            ->where('venue_id', 90)
            ->where('user_id', 4428)
            ->update([
                'supabase_id' => 'bb4c0f7b-7d81-4061-b8dd-659728dfbd8f',
                'updated_at' => now()
            ]);

        // Update the password for the user with email 'ivitase@staffluent.xyz'
        DB::table('users')
            ->where('email', 'ivitase@staffluent.xyz')
            ->update([
                'password' => Hash::make('123456'), // Ensure the password is hashed
                'updated_at' => now()
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // If rolling back, you may choose to restore the previous values (if known)
        DB::table('venue_user_supabase_connections')
            ->where('venue_id', 90)
            ->where('user_id', 4428)
            ->update([
                'supabase_id' => '715cc4b20-d41c-4faa-b7dd-51e5f9539bb9',
                'updated_at' => now()
            ]);

        // For password, it's recommended not to store plain-text passwords in migrations, so this part is tricky.
        // You would typically handle this via a password reset request, but if you need to restore an old password:
        // DB::table('users')
        //    ->where('email', 'ivitase@staffluent.xyz')
        //    ->update([
        //        'password' => Hash::make('previous_password'),
        //        'updated_at' => now()
        //    ]);
    }
};
