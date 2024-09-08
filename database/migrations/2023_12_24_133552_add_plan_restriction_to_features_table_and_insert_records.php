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
        // Add the plan_restriction column
        Schema::table('features', function (Blueprint $table) {
            $table->boolean('plan_restriction')->default(1);
        });

        // Insert 'Hygiene Standards' feature for each category
        $categories = ['food', 'sport_entertainment', 'retail', 'accommodation'];
        foreach ($categories as $category) {
            DB::table('features')->insert([
                'name' => 'Hygiene Standards',
                'link' => '', // Set the appropriate link if needed
                'active' => 1,
                'feature_category' => $category,
                'identified_for_plan_name' => 'Hygiene Standards',
                'plan_restriction' => 0 // or 0 based on requirement
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove the inserted records
        DB::table('features')->where('name', 'Hygiene Standards')->delete();

        // Drop the plan_restriction column
        Schema::table('features', function (Blueprint $table) {
            $table->dropColumn('plan_restriction');
        });
    }
};
