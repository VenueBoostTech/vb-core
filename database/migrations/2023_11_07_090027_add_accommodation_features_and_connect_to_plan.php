<?php

use App\Models\Feature;
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
        // Accommodation Features
        $featuresAccommodation = [
            'Units/Rooms Management',
            'Housekeeping',
        ];

        foreach ($featuresAccommodation as $featureA) {
            DB::table('features')->insert([
                'name' => $featureA,
                'identified_for_plan_name' => $featureA,
                'feature_category' => 'accommodation',
                'link' => '',
            ]);
        }

        $accommodationFreemiumPlanId = DB::table('pricing_plans')->where('short_code', 'FA-PP-01')->value('id');
        $accommodationFeature1 = Feature::whereNotNull('feature_category')->where('feature_category', 'accommodation')->where('name', 'Units/Rooms Management')->first();
        $accommodationFeature2 = Feature::whereNotNull('feature_category')->where('feature_category', 'accommodation')->where('name', 'Housekeeping')->first();

        $usageCredit = 10;
        $whitelabelAccess = null;
        $allow_vr_ar = null;


        // insert the features into the plan_features table
        DB::table('plan_features')->insert([
            [
                'feature_id' => $accommodationFeature1->id,
                'usage_credit' => $usageCredit,
                'whitelabel_access' => $whitelabelAccess,
                'allow_vr_ar' => $allow_vr_ar,
                'plan_id' => $accommodationFreemiumPlanId,
            ],
            [
                'feature_id' => $accommodationFeature2->id,
                'usage_credit' => $usageCredit,
                'whitelabel_access' => $whitelabelAccess,
                'allow_vr_ar' => $allow_vr_ar,
                'plan_id' => $accommodationFreemiumPlanId,
            ],
        ]);

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('plan', function (Blueprint $table) {
            //
        });
    }
};
