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
        $retailFreemiumPlanId = DB::table('pricing_plans')->where('short_code', 'FR-PP-01')->value('id');
        $retailFeatures = Feature::whereNotNull('feature_category')->where('feature_category', 'retail')->get();

        $featuresToInsert = [];

        foreach ($retailFeatures as $feature) {
            $usageCredit = (in_array($feature->name, ['VB Whitelabel', 'VR/AR']) ? 0 : $feature->name === 'Store Management') ? 1 : 10;
            $whitelabelAccess = $feature->name === 'VB Whitelabel' ? 'vb_related' : null;

            $featuresToInsert[] = [
                'feature_id' => $feature->id,
                'usage_credit' => $usageCredit,
                'whitelabel_access' => $whitelabelAccess,
                'allow_vr_ar' => $feature->name !== 'VR/AR' ? null : false,
                'plan_id' => $retailFreemiumPlanId,
            ];
        }

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
