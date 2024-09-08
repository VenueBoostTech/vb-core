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
        // Add new boolean columns to the sub_features table
        Schema::table('sub_features', function (Blueprint $table) {
            $table->boolean('is_main_sub_feature')->default(1)->after('active');
            $table->boolean('is_function')->default(0)->after('is_main_sub_feature');
            $table->boolean('plan_restriction')->default(1)->after('is_function');
        });

        DB::table('sub_features')
            ->update(['active' => 0]);

        // Insert subfeatures for 'Hygiene Standards'
        $hygieneFeatureId = DB::table('features')->where('name', 'Hygiene Standards')->value('id');

        if ($hygieneFeatureId) {
            $subFeatures = [
                'Hygiene Checks',
                'Hygiene Checklist',
                'Hygiene Vendor Management'
            ];

            foreach ($subFeatures as $name) {
                DB::table('sub_features')->insert([
                    'feature_id' => $hygieneFeatureId,
                    'name' => $name,
                    'link' => '', // Adjust the link as necessary
                    'active' => 1,
                    'is_main_sub_feature' => 1,
                    'is_function' => 0,
                    'plan_restriction' => 0
                ]);
            }
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sub_features_table_and_insert_records', function (Blueprint $table) {
            //
        });
    }
};
