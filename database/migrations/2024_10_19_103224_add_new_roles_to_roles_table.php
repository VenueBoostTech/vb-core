<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {

        $roles = [
            ['name' => 'CEO', 'description' => 'Chief Executive Officer', 'role_type' => 'vb_app'],
            ['name' => 'CTO', 'description' => 'Chief Technology Officer', 'role_type' => 'vb_app'],
            ['name' => 'CFO', 'description' => 'Chief Financial Officer', 'role_type' => 'vb_app'],
            ['name' => 'Project Manager', 'description' => 'Oversees project planning and execution', 'role_type' => 'vb_app'],
            ['name' => 'Team Lead', 'description' => 'Leads a team of developers or engineers', 'role_type' => 'vb_app'],
            ['name' => 'Senior Developer', 'description' => 'Experienced software developer', 'role_type' => 'vb_app'],
            ['name' => 'Developer', 'description' => 'Software developer', 'role_type' => 'vb_app'],
            ['name' => 'QA Engineer', 'description' => 'Quality Assurance Engineer', 'role_type' => 'vb_app'],
            ['name' => 'DevOps Engineer', 'description' => 'Development and Operations Engineer', 'role_type' => 'vb_app'],
            ['name' => 'UX/UI Designer', 'description' => 'User Experience and User Interface Designer', 'role_type' => 'vb_app'],
            ['name' => 'Product Manager', 'description' => 'Oversees product development and strategy', 'role_type' => 'vb_app'],
            ['name' => 'HR Manager', 'description' => 'Human Resources Manager', 'role_type' => 'vb_app'],
            ['name' => 'Finance Manager', 'description' => 'Manages financial operations', 'role_type' => 'vb_app'],
            ['name' => 'Accountant', 'description' => 'Handles financial records and reporting', 'role_type' => 'vb_app'],
            ['name' => 'Sales Manager', 'description' => 'Oversees sales team and strategies', 'role_type' => 'vb_app'],
            ['name' => 'Sales Representative', 'description' => 'Handles sales and client relationships', 'role_type' => 'vb_app'],
            ['name' => 'Customer Support Specialist', 'description' => 'Provides customer service and support', 'role_type' => 'vb_app'],
            ['name' => 'Office Manager', 'description' => 'Manages office operations and logistics', 'role_type' => 'vb_app'],
        ];

        DB::table('roles')->insert($roles);
    }

    public function down()
    {

        // do nothing
    }
};
