<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Delete the duplicate record for user 213
        DB::table('venue_user_supabase_connections')
            ->where('user_id', 213)
            ->where('supabase_id', '15cc4b20-d41c-4faa-b7dd-51e5f9539bb9')
            ->delete();

        // Update the record for user 4428
        DB::table('venue_user_supabase_connections')
            ->where('user_id', 4428)
            ->where('supabase_id', '715cc4b20-d41c-4faa-b7dd-51e5f9539bb9')
            ->update([
                'supabase_id' => '15cc4b20-d41c-4faa-b7dd-51e5f9539bb9',
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
        // Restore the original state
        // First revert the update
        DB::table('venue_user_supabase_connections')
            ->where('user_id', 4428)
            ->where('supabase_id', '15cc4b20-d41c-4faa-b7dd-51e5f9539bb9')
            ->update([
                'supabase_id' => '715cc4b20-d41c-4faa-b7dd-51e5f9539bb9',
                'updated_at' => now()
            ]);

        // Then reinsert the deleted record
        DB::table('venue_user_supabase_connections')->insert([
            'user_id' => 213,
            'venue_id' => 85,
            'supabase_id' => '15cc4b20-d41c-4faa-b7dd-51e5f9539bb9',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
};
