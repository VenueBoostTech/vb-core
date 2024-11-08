<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add new notification types
        DB::table('notification_types')->insert([
            [
                'name' => 'team_leader_assignment',
                'description' => 'Assigned as team leader notification',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'team_member_added',
                'description' => 'Added to team notification',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'team_member_removed',
                'description' => 'Removed from team notification',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    public function down()
    {
        DB::table('notification_types')
            ->whereIn('name', [
                'team_leader_assignment',
                'team_member_added',
                'team_member_removed'
            ])
            ->delete();
    }
};
