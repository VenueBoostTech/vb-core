<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\NotificationType;

class NotificationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if(!NotificationType::where('name', 'new_project_assigned')->exists()){
            NotificationType::create([
                'name' => 'new_project_assigned',
                'description' => 'New Project Assigned',
            ]);
        }

        if(!NotificationType::where('name', 'site_manager_assigned')->exists()){
            NotificationType::create([
                'name' => 'site_manager_assigned',
                'description' => 'Site Manager Assigned',
            ]);
        }

        if(!NotificationType::where('name', 'new_construction_site_assigned')->exists()){
            NotificationType::create([
                'name' => 'new_construction_site_assigned',
                'description' => 'New Construction Site Assigned',
            ]);
        }
    }
}
