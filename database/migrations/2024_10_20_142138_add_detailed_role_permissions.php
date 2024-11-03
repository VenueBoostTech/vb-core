<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $permissions = [
            'access_mobile_app',
            'access_web_app',
            'workforce_management',
            'clock_in_out',
            'use_chat',
            'upload_media',
            'view_basic_performance_metrics',
            'operations_control',
            'manage_team_schedules',
            'assign_tasks',
            'conduct_quality_inspections',
            'manage_inventory',
            'oversee_multiple_teams',
            'manage_work_orders',
            'customer_relationship_management',
            'view_operations_analysis',
            'handle_compliance_safety',
            'system_configuration',
            'user_management',
            'access_all_reports_analytics',
            'manage_financial_aspects',
            'view_high_level_reports_kpis',
            'access_financial_overview',
            'access_business_intelligence'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $rolePermissions = [
            'Cleaner' => [
                'access_mobile_app',
                'workforce_management',
                'clock_in_out',
                'use_chat',
                'upload_media',
                'view_basic_performance_metrics'
            ],
            'Team Leader' => [
                'access_mobile_app',
                'access_web_app',
                'workforce_management',
                'operations_control',
                'clock_in_out',
                'use_chat',
                'upload_media',
                'view_basic_performance_metrics',
                'manage_team_schedules',
                'assign_tasks',
                'conduct_quality_inspections',
                'manage_inventory'
            ],
            'Operations Manager' => [
                'access_mobile_app',
                'access_web_app',
                'workforce_management',
                'operations_control',
                'oversee_multiple_teams',
                'manage_work_orders',
                'assign_tasks',
                'conduct_quality_inspections',
                'customer_relationship_management',
                'view_operations_analysis',
                'handle_compliance_safety'
            ],
            'Administrator' => [
                'access_web_app',
                'system_configuration',
                'user_management',
                'access_all_reports_analytics',
                'manage_financial_aspects'
            ],
            'Executive' => [
                'access_web_app',
                'access_all_reports_analytics',
                'view_high_level_reports_kpis',
                'access_financial_overview',
                'access_business_intelligence'
            ],
            'Owner' => Permission::pluck('name')->toArray() // All permissions
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                $role = Role::create([
                    'name' => $roleName,
                    'role_type' => 'vb_app'  // Explicitly set the role_type
                ]);
            }

            $permissionIds = Permission::whereIn('name', $permissions)->pluck('id')->toArray();
            $existingPermissionIds = DB::table('role_permission')
                ->where('role_id', $role->id)
                ->pluck('permission_id')
                ->toArray();
            $newPermissionIds = array_diff($permissionIds, $existingPermissionIds);

            foreach ($newPermissionIds as $permissionId) {
                DB::table('role_permission')->insert([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId
                ]);
            }
        }
    }

    public function down()
    {
        // This down method doesn't remove any roles or permissions to prevent accidental data loss
        // If you need to revert, you should create a separate migration
    }
};
