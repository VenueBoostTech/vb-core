<?php

use App\Models\Feature;
use App\Models\PricingPlan;
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

        $shortCodesToDelete = ['FF-PP-01', 'FA-PP-01', 'FR-PP-01', 'FS-PP-01'];
        PricingPlan::whereIn('short_code', $shortCodesToDelete)->delete();
        Feature::whereNotNull('identified_for_plan_name')->delete();

        // F&B Features
        $featuresFood = [
            'Streamlined Reservations',
            'Inventory Management',
            'Staff Management',
            'Guests Management',
            'Analytics & Reporting',
            'Guests Surveys & Ratings',
            'Menu Management',
            'Marketing Strategy',
            'Loyalty and Retention',
            'Payment Links',
            'Delivery Orders',
            'Affiliate Partnerships',
            'Premium Tables with Bidding',
            'Dining Guests Loyalty Program',
            'Advanced Guests Behavior Analytics',
            'Marketing Automation',
            'In Person Payments',
            'VR/AR',
            'VB Whitelabel', // Added VB Whitelabel
            'Social Media Ordering',
        ];

        foreach ($featuresFood as $featureF) {
            DB::table('features')->insert([
                'name' => $featureF,
                'identified_for_plan_name' => $featureF,
                'feature_category' => 'food',
                'link' => '',
            ]);
        }

        // Accommodation Features
        $featuresAccommodation = [
            'Bookings Management',
            'Inventory Management',
            'Staff Management',
            'Marketing',
            'Loyalty and Retention',
            'Analytics & Reporting',
            'Payment Links',
            'Guests Management',
            'Affiliate Partnerships',
            'Guests Surveys & Ratings',
            'iCal Integration',
            'Accommodation Guest Loyalty Program',
            'Advanced Guests Behavior Analytics',
            'Marketing Automation',
            'In Person Payments',
            'VR/AR',
            'VB Whitelabel',
            'Items Management',
        ];

        foreach ($featuresAccommodation as $featureA) {
            DB::table('features')->insert([
                'name' => $featureA,
                'identified_for_plan_name' => $featureA,
                'feature_category' => 'accommodation',
                'link' => '',
            ]);
        }

        // Retail Management Features
        $featuresRetail = [
            'Order Management',
            'Inventory Management',
            'Staff Management',
            'Marketing',
            'Loyalty and Retention',
            'Dashboard and Revenue',
            'Store Management',
            'Customer Surveys and Ratings',
            'Affiliate Partnerships',
            'Centralized Analytics for Multi-Brand Retailers',
            'Consistent Inventory',
            'Retail Customer Loyalty Program',
            'Advanced Customer Behavior Analytics',
            'Marketing Automation',
            'In Person Payments',
            'VR/AR',
            'VB Whitelabel',
            'Products Management'
        ];

        foreach ($featuresRetail as $featureR) {
            DB::table('features')->insert([
                'name' => $featureR,
                'identified_for_plan_name' => $featureR,
                'feature_category' => 'retail',
                'link' => '',
            ]);
        }

        // Entertainment Venues Features
        $featuresEntertainment = [
            'Bookings Management',
            'Inventory Management',
            'Staff Management',
            'Marketing',
            'Loyalty and Retention',
            'Analytics & Reporting',
            'Payment Links',
            'Customers Management',
            'Guest Surveys and Ratings',
            'Affiliate Partnerships',
            'Entertainment Membership Program',
            'Advanced Customer Behavior Analytics',
            'Marketing Automation',
            'In Person Payments',
            'VR/AR',
            'VB Whitelabel',
            'Items Management',
        ];

        foreach ($featuresEntertainment as $featureE) {
            DB::table('features')->insert([
                'name' => $featureE,
                'identified_for_plan_name' => $featureE,
                'feature_category' => 'sport_entertainment',
                'link' => '',
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
        //
    }
};
