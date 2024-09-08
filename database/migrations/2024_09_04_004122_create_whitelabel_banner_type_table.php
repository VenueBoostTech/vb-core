<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('whitelabel_banner_type', function (Blueprint $table) {
            $table->id();
            $table->json('type');
            $table->json('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Seed the table with initial data
        $types = [
            ['type' => json_encode(['en' => 'Header']), 'description' => json_encode(['en' => null])],
            ['type' => json_encode(['en' => 'Sidebar']), 'description' => json_encode(['en' => null])],
            ['type' => json_encode(['en' => 'Article top']), 'description' => json_encode(['en' => null])],
            ['type' => json_encode(['en' => 'Article bottom']), 'description' => json_encode(['en' => null])],
        ];

        foreach ($types as $type) {
            DB::table('whitelabel_banner_type')->insert([
                'type' => $type['type'],
                'description' => $type['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('whitelabel_banner_type');
    }
};
