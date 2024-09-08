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
        Schema::table('sub_features', function (Blueprint $table) {
            $hygieneFeatureId = DB::table('features')->where('name', 'Hygiene Standards')->value('id');

            if ($hygieneFeatureId) {
                DB::table('sub_features')->insert([
                    'feature_id' => $hygieneFeatureId,
                    'name' => 'Hygiene Inspection',
                    'link' => '', // Adjust the link as necessary
                    'active' => 1,
                    'is_main_sub_feature' => 1,
                    'is_function' => 0,
                    'plan_restriction' => 0
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sub_features', function (Blueprint $table) {
            // Logic to remove the 'Hygiene Inspection' sub-feature during rollback
            $hygieneFeatureId = DB::table('features')->where('name', 'Hygiene Standards')->value('id');

            if ($hygieneFeatureId) {
                DB::table('sub_features')
                    ->where('feature_id', $hygieneFeatureId)
                    ->where('name', 'Hygiene Inspection')
                    ->delete();
            }
        });
    }
};
