<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $ownerPermissions = [
            'Can appoint new staff',
            'Can fire existing staff',
            'Can view sales report',
            'Can manage inventory'
        ];

        $managerPermissions = [
            'Can report to owner',
            'Can view sales report'
        ];

        $waiterPermissions = [
            'Can report to manager'
        ];

        $cookPermissions = [
            'Can report to manager'
        ];

        $owner = Role::find(1);
        $manager = Role::find(2);
        $waiter = Role::find(3);
        $cook = Role::find(4);

        $ownerPermissionsIds = Permission::whereIn('name', $ownerPermissions)->pluck('id');
        $managerPermissionsIds = Permission::whereIn('name', $managerPermissions)->pluck('id');
        $waiterPermissionsIds = Permission::whereIn('name', $waiterPermissions)->pluck('id');
        $cookPermissionsIds = Permission::whereIn('name', $cookPermissions)->pluck('id');

        $owner->permissions()->attach($ownerPermissionsIds);
        $manager->permissions()->attach($managerPermissionsIds);
        $waiter->permissions()->attach($waiterPermissionsIds);
        $cook->permissions()->attach($cookPermissionsIds);
    }
}
