<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateVtPlansTable extends Migration
{
    public function up()
    {
        Schema::create('vt_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->integer('max_cameras');
            $table->integer('days_of_activity');
            $table->decimal('price_monthly', 8, 2);
            $table->decimal('price_yearly', 8, 2);
            $table->json('features');
            $table->timestamps();
        });

        // Insert plans with URLs for each feature
        DB::table('vt_plans')->insert([
            [
                'name' => 'Basic Plan',
                'slug' => 'basic_plan',
                'description' => 'Suitable for small venues',
                'max_cameras' => 2,
                'days_of_activity' => 30,
                'price_monthly' => 29.99,
                'price_yearly' => 299.99,
                'features' => json_encode([
                    'Dashboard' => ['Main' => 'vision-track/dashboard'],
                    'Analytics' => [],
                    'Devices' => [],
                    'Staff Management' => [
                        'Work Activity Monitoring' => 'vision-track/staff/work-activity',
                        'Working Sessions' => 'vision-track/staff/working-sessions',
                        'Sleeping Detection' => 'vision-track/staff/sleeping-detection',
                        'Break Monitoring' => 'vision-track/staff/break-monitoring',
                    ],
                    'Environment' => ['Monitor' => 'vision-track/environment/monitor'],
                    'Settings' => ['General' => 'vision-track/settings'],
                ]),
            ],
            [
                'name' => 'Medium Plan',
                'slug' => 'medium_plan',
                'description' => 'Ideal for medium-sized venues',
                'max_cameras' => 5,
                'days_of_activity' => 60,
                'price_monthly' => 59.99,
                'price_yearly' => 599.99,
                'features' => json_encode([
                    'Dashboard' => [
                        'Main' => 'vision-track/dashboard',
                        'Monitoring Summary' => 'vision-track/dashboard/monitoring-summary'
                    ],
                    'Analytics' => [],
                    'Devices' => [],
                    'Staff Management' => [
                        'Work Activity Monitoring' => 'vision-track/staff/work-activity',
                        'Working Sessions' => 'vision-track/staff/working-sessions',
                        'Sleeping Detection' => 'vision-track/staff/sleeping-detection',
                        'Break Monitoring' => 'vision-track/staff/break-monitoring',
                        'Behavioral Analysis' => 'vision-track/staff/behavioral-analysis',
                    ],
                    'Security' => [
                        'Threat Detection' => 'vision-track/security/threat-detection',
                        'Multi-Point Threat Detection' => 'vision-track/security/multi-point-threat-detection',
                        'Sleeping Detection' => 'vision-track/security/sleeping-detection',
                    ],
                    'Environment' => [
                        'Monitor' => 'vision-track/environment/monitor',
                        'Smoke Detection' => 'vision-track/environment/smoke-detection',
                        'Activity Recognition' => 'vision-track/environment/activity-recognition',
                    ],
                    'Settings' => [
                        'General' => 'vision-track/settings',
                        'Monitoring Parameters' => 'vision-track/settings/monitoring-parameters',
                    ],
                ]),
            ],
            [
                'name' => 'Advanced Plan',
                'slug' => 'advanced_plan',
                'description' => 'Perfect for large venues',
                'max_cameras' => 10,
                'days_of_activity' => 90,
                'price_monthly' => 99.99,
                'price_yearly' => 999.99,
                'features' => json_encode([
                    'Dashboard' => [
                        'Main' => 'vision-track/dashboard',
                        'Monitoring Summary' => 'vision-track/dashboard/monitoring-summary'
                    ],
                    'Analytics' => [],
                    'Devices' => [],
                    'Staff Management' => [
                        'Work Activity Monitoring' => 'vision-track/staff/work-activity',
                        'Working Sessions' => 'vision-track/staff/working-sessions',
                        'Sleeping Detection' => 'vision-track/staff/sleeping-detection',
                        'Break Monitoring' => 'vision-track/staff/break-monitoring',
                        'Behavioral Analysis' => 'vision-track/staff/behavioral-analysis',
                    ],
                    'Security' => [
                        'Threat Detection' => 'vision-track/security/threat-detection',
                        'Multi-Point Threat Detection' => 'vision-track/security/multi-point-threat-detection',
                        'Sleeping Detection' => 'vision-track/security/sleeping-detection',
                    ],
                    'Environment' => [
                        'Monitor' => 'vision-track/environment/monitor',
                        'Smoke Detection' => 'vision-track/environment/smoke-detection',
                        'Activity Recognition' => 'vision-track/environment/activity-recognition',
                    ],
                    'Vehicle Management' => [
                        'Vehicle Detection' => 'vision-track/vehicle/detection',
                        'Parking Occupancy' => 'vision-track/vehicle/parking-occupancy',
                        'Valet Optimization' => 'vision-track/vehicle/valet-optimization',
                    ],
                    'Settings' => [
                        'General' => 'vision-track/settings',
                        'Monitoring Parameters' => 'vision-track/settings/monitoring-parameters',
                    ],
                ]),
            ],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('vt_plans');
    }
}
