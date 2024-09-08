<?php

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
        Schema::table('products', function (Blueprint $table) {
            $table->text('description')->change();
        });

        $roles = [
            [
                'name' => 'Owner',
                'role_type' => 'retail_hierarchy',
            ],
            [
                'name' => 'Manager',
                'role_type' => 'retail_hierarchy',
            ],
            [
                'name' => 'Sales Associate',
                'role_type' => 'retail_hierarchy',
            ],
            [
                'name' => 'Stock Clerk',
                'role_type' => 'retail_hierarchy',
            ],
            [
                'name' => 'Cashier',
                'role_type' => 'retail_hierarchy',
            ],
            [
                'name' => 'Customer Service Representative',
                'role_type' => 'retail_hierarchy',
            ],
            [
                'name' => 'Security',
                'role_type' => 'retail_hierarchy',
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
        Schema::table('products', function (Blueprint $table) {
            $table->string('description')->change();
        });
    }
};
