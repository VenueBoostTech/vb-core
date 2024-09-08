<?php

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
        // Add the 'healthcare' category to the enum values
        DB::statement("ALTER TABLE addons MODIFY COLUMN category ENUM('accommodation', 'retail', 'food_drinks', 'sport_entertainment', 'hotel_resort', 'healthcare', 'food')");

        // Update existing addons with the old category to the new category
        $oldCategoryMapping = [
            'food_drinks' => 'food',
            // Add other mappings as needed
        ];

        foreach ($oldCategoryMapping as $oldCategory => $newCategory) {
            DB::table('addons')
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
