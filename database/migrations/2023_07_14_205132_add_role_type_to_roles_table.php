<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('role_type')->after('name'); // Add the role_type field
        });

        $roles = [
            ['id' => 1, 'role_type' => 'restaurant_hierarchy'],
            ['id' => 2, 'role_type' => 'restaurant_hierarchy'],
            ['id' => 3, 'role_type' => 'restaurant_hierarchy'],
            ['id' => 4, 'role_type' => 'restaurant_hierarchy'],
        ];

        foreach ($roles as $role) {
            $roleModel = Role::find($role['id']);
            if ($roleModel) {
                $roleModel->role_type = $role['role_type'];
                $roleModel->save();
            }
        }

        $roles = [
            [
                'name' => 'Owner',
                'role_type' => 'hotel_hierarchy',
            ],
            [
                'name' => 'Manager',
                'role_type' => 'hotel_hierarchy',
            ],
            [
                'name' => 'Front Desk Supervisor',
                'role_type' => 'hotel_hierarchy',
            ],
            [
                'name' => 'Housekeeping Supervisor',
                'role_type' => 'hotel_hierarchy',
            ],
            [
                'name' => 'Restaurant Manager',
                'role_type' => 'hotel_hierarchy',
            ],
            [
                'name' => 'Sales and Marketing Manager',
                'role_type' => 'hotel_hierarchy',
            ],
            [
                'name' => 'Waiter',
                'role_type' => 'hotel_hierarchy',
            ],
            [
                'name' => 'Cook',
                'role_type' => 'hotel_hierarchy',
            ],
            [
                'name' => 'Owner',
                'role_type' => 'golf_hierarchy',
            ],
            [
                'name' => 'Manager',
                'role_type' => 'golf_hierarchy',
            ],
            [
                'name' => 'Superintendent',
                'role_type' => 'golf_hierarchy',
            ],
            [
                'name' => 'Golf Pro/Head Golf Professional',
                'role_type' => 'golf_hierarchy',
            ],
            [
                'name' => 'Operations Manager',
                'role_type' => 'golf_hierarchy',
            ],
        ];

        DB::table('roles')->insert($roles);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('role_type'); // Remove the role_type field
        });
    }
};
