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
        // Alter the 'category' enum values
        DB::statement("ALTER TABLE pricing_plans MODIFY COLUMN category ENUM('accommodation', 'retail', 'food_drinks', 'sport_entertainment', 'hotel_resort')");

        // Update existing pricing plans with the old category to the new category
        $oldCategoryMapping = [
            'hotel_resort' => 'accommodation',
            // Add other mappings as needed
        ];

        foreach ($oldCategoryMapping as $oldCategory => $newCategory) {
            DB::table('pricing_plans')
                ->where('category', $oldCategory)
                ->update(['category' => $newCategory]);
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
