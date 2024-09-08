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
        // Update the image field for the blog with id = 175
        DB::table('blogs')
            ->where('id', 175)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/07/DALL·E-2024-07-17-19.32.34-A-high-tech-event-management-setup-with-screens-displaying-advanced-data-analytics-charts-and-graphs.-The-interface-shows-various-metrics-and-key-pe.webp']);

        // Delete blogs with ids 184 and 183
        DB::table('blogs')
            ->whereIn('id', [184, 183])
            ->delete();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
