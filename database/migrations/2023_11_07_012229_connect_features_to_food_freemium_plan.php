<?php

use App\Models\Feature;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $foodFreemiumPlanId = DB::table('pricing_plans')->where('short_code', 'FF-PP-01')->value('id');
        $foodFeatures = Feature::whereNotNull('feature_category')->where('feature_category', 'food')->get();

        // Initialize an array to store the features with attributes
        $featuresToInsert = [];

        // Iterate through the features and prepare the data
        foreach ($foodFeatures as $feature) {
            $usageCredit = in_array($feature->name, ['VB Whitelabel', 'VR/AR']) ? 0 : 10;
            $whitelabelAccess = $feature->name === 'VB Whitelabel' ? 'vb_related' : null;

            $featuresToInsert[] = [
                'feature_id' => $feature->id,
                'usage_credit' => $usageCredit,
                'whitelabel_access' => $whitelabelAccess,
                'allow_vr_ar' => $feature->name !== 'VR/AR' ? null : false,
                'plan_id' => $foodFreemiumPlanId,
            ];
        }

        // Insert the features into the plan_features table
        DB::table('plan_features')->insert($featuresToInsert);

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
