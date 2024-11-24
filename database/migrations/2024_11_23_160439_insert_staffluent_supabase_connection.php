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
        // First delete the incorrect record
        DB::table('venue_user_supabase_connections')
            ->where('supabase_id', '715cc4b20-d41c-4faa-b7dd-51e5f9539bb9')
            ->delete();

        // Then insert the new record with correct data
        DB::table('venue_user_supabase_connections')->insert([
            'venue_id' => 90,
            'user_id' => 4428,
            'supabase_id' => '715cc4b20-d41c-4faa-b7dd-51e5f9539bb9',
            'created_at' => now(),
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
        // Remove the new record
        DB::table('venue_user_supabase_connections')
            ->where('supabase_id', '715cc4b20-d41c-4faa-b7dd-51e5f9539bb9')
            ->delete();
    }
};
