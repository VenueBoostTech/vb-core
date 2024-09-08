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
        // Names of subfeatures for 'Hygiene Standards'
        $subFeatures = [
            'Hygiene Checks',
            'Hygiene Checklist',
            'Hygiene Vendor Management',
            'Hygiene Inspection'
        ];

        // Feature categories
        $featureCategories = ['food', 'accommodation', 'retail', 'sport_entertainment'];

        // Process each feature category
        foreach ($featureCategories as $category) {
            // Get the feature ID for 'Hygiene Standards' for each category
            $hygieneFeatureId = DB::table('features')
                ->where('name', 'Hygiene Standards')
                ->where('feature_category', $category)
                ->value('id');

            if ($hygieneFeatureId) {
                // Remove existing subfeatures related to this feature ID
                DB::table('sub_features')->where('feature_id', $hygieneFeatureId)->delete();

                // Reinsert the subfeatures for this feature ID
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
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('or_update_hygiene_standards_subfeatures');
    }
};
