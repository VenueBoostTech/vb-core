<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('restaurant_role', function (Blueprint $table) {
            // Add the is_custom column if it doesn't exist
            if (!Schema::hasColumn('restaurant_role', 'is_custom')) {
                $table->boolean('is_custom')->default(false)->after('role_id');
            }

            // Add a new unique constraint including is_custom
            $table->unique(['restaurant_id', 'role_id', 'is_custom'], 'restaurant_role_unique');
        });

        // Now that the new index is in place, we can safely drop the old one
        DB::statement('ALTER TABLE restaurant_role DROP INDEX restaurant_role_restaurant_id_role_id_unique');
    }

    public function down()
    {
        Schema::table('restaurant_role', function (Blueprint $table) {
            // Recreate the original unique constraint
            $table->unique(['restaurant_id', 'role_id']);

            // Drop the new unique constraint
            $table->dropUnique('restaurant_role_unique');

            // Remove the is_custom column if you want to completely revert
            // $table->dropColumn('is_custom');
        });
    }
};
