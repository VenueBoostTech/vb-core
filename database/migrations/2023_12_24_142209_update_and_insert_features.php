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
        // Update existing features, except 'Hygiene Standards'
        DB::table('features')
            ->where('name', '<>', 'Hygiene Standards')
            ->update(['active' => 0, 'plan_restriction' => 0]);

        // Define new features for each category
        $newFeatures = [
            'food' => [
                'Reservations',
                'Inventory Management',
                'Analytics & Reporting',
                'Guest Management',
                'Marketing Strategy',
                'Loyalty and Retention',
                'Payment Links',
                'Guest Surveys and Ratings',
                'Marketing Automation',
                'Affiliate Partnerships',
                'Premium Tables with Bidding / Pricing',
                'Customizable Brand Profile',
                'Staff Management',
                'Delivery Orders Management',
                'Advanced Guest Behavior Analytics',
                'Menu Management',
                'In-Person Payments',
                'Dining Guest Loyalty Program',

            ],
            'accommodation' => [
                'Bookings Management',
                'Inventory Management',
                'Staff Management',
                'Marketing Strategy',
                'Loyalty and Retention',
                'Analytics & Reporting',
                'Payment Links',
                'Guests Management',
                'Marketing Automation',
                'Accommodation Guest Loyalty Program',
                'iCal Integration',
                'Affiliates Partnerships',
                'Guest Surveys and Ratings',
                'Advanced Customer Behavior Analytics',
                'Items Management',
                'Units/Rooms Management',
                'Housekeeping',
                'Customizable Brand Profile',
                'In Person Payments',
            ],
            'retail' => [
                'Orders Management',
                'Inventory Management',
                'Staff Management',
                'Marketing Strategy',
                'Loyalty and Retention',
                'Dashboard & Revenue',
                'Store Management',
                'Marketing Automation',
                'Retail Customer Loyalty Program',
                'Consistent Inventory',
                'Affiliates Partnerships',
                'Customer Surveys and Ratings',
                'Advanced Customer Behavior Analytics',
                'Centralized Analytics for Multi-Brand Retailers',
                'Products Management',
                'Customizable Brand Profile',
                'In Person Payments',
            ],
            'sport_entertainment' => [
                'Bookings Management',
                'Inventory Management',
                'Staff Management',
                'Marketing Strategy',
                'Loyalty and Retention',
                'Analytics & Reporting',
                'Payment Links',
                'Customers Management',
                'Marketing Automation',
                'Affiliate Partnerships',
                'Guest Surveys and Ratings',
                'Entertainment Membership Program',
                'Advanced Customer Behavior Analytics',
                'Items Management',
                'Customizable Brand Profile',
                'In Person Payment',
            ],
        ];

        // Insert new features
        foreach ($newFeatures as $category => $features) {
            foreach ($features as $featureName) {
                DB::table('features')->insert([
                    'name' => $featureName,
                    'link' => '',
                    'active' => 1,
                    'feature_category' => $category,
                    'identified_for_plan_name' => $featureName,
                    'plan_restriction' => 1
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
        //
    }
};
