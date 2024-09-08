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
        // Subfeatures for 'Customizable Brand Profile'
        $subFeatures = [
            'Strings Customizations',
            'Theme Customizations'
        ];

        // Feature categories
        $featureCategories = ['food', 'accommodation', 'retail', 'sport_entertainment'];

        // Process each feature category
        foreach ($featureCategories as $category) {
            // Get the feature ID for 'Customizable Brand Profile' for each category
            $brandFeatureId = DB::table('features')
                ->where('name', 'Customizable Brand Profile')
                ->where('feature_category', $category)
                ->value('id');

            if ($brandFeatureId) {
                // Remove existing subfeatures related to this feature ID
                DB::table('sub_features')->where('feature_id', $brandFeatureId)->delete();

                // Reinsert the subfeatures for this feature ID
                foreach ($subFeatures as $name) {
                    DB::table('sub_features')->insert([
                        'feature_id' => $brandFeatureId,
                        'name' => $name,
                        'link' => '', // Adjust the link as necessary
                        'active' => 1,
                        'is_main_sub_feature' => 1,
                        'is_function' => 0,
                        'plan_restriction' => 1
                    ]);
                }
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

    }
};
