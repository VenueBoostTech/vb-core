<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateVbAppsTable extends Migration
{
    public function up()
    {
        Schema::create('vb_apps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->decimal('price_per_user', 8, 2);
            $table->decimal('initial_fee', 8, 2)->nullable();
            $table->timestamps();
        });

        // Insert initial apps
        DB::table('vb_apps')->insert([
            [
                'name' => 'Event',
                'slug' => 'event',
                'description' => 'Comprehensive event management application',
                'price_per_user' => 0.99,
                'initial_fee' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'FlowMaster',
                'slug' => 'flow-master',
                'description' => 'Streamline business processes and workflows',
                'price_per_user' => 0.99,
                'initial_fee' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Inventory',
                'slug' => 'inventory',
                'description' => 'Efficient inventory management system',
                'price_per_user' => 0.99,
                'initial_fee' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sales Associate',
                'slug' => 'sales-associate',
                'description' => 'Empower your sales team with powerful tools',
                'price_per_user' => 0.99,
                'initial_fee' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Staff',
                'slug' => 'staff',
                'description' => 'Comprehensive staff management solution',
                'price_per_user' => 0.99,
                'initial_fee' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'MetriCoach',
                'slug' => 'metri-coach',
                'description' => 'Analytics-driven performance coaching tool',
                'price_per_user' => 0.99,
                'initial_fee' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('vb_apps');
    }
}
