<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DeleteVenue58Data extends Migration
{
    /**
     * Run the migration.
     *
     * @return void
     */
    public function up()
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        echo "Foreign key checks disabled\n";

        try {
            DB::beginTransaction();
            echo "Starting transaction...\n";

            $deletionCounts = [];

            // Basic table deletions for venue_id 58
            $tables = [
                'collections',
                'brands',
                'groups',
            ];

            foreach ($tables as $table) {
                $deletionCounts[$table] = DB::table($table)
                    ->where('venue_id', 58)
                    ->delete();
                echo "- {$table}: {$deletionCounts[$table]} records deleted\n";
            }

            // Orders with restaurant_id
            $deletionCounts['orders'] = DB::table('orders')
                ->where('restaurant_id', 58)
                ->delete();
            echo "- orders: {$deletionCounts['orders']} records deleted\n";

            // Members with venue_id
            $deletionCounts['members'] = DB::table('members')
                ->where('venue_id', 58)
                ->delete();
            echo "- members: {$deletionCounts['members']} records deleted\n";

            // Attributes table (from bybest-attributes route)
            $deletionCounts['attributes'] = DB::table('vb_store_attributes')->delete();
            echo "- attributes: {$deletionCounts['attributes']} records deleted\n";

            // Products with restaurant_id and bybest_id (with soft delete)
            $deletionCounts['products'] = DB::table('products')
                ->where('restaurant_id', 58)
                ->whereNotNull('bybest_id')
                ->update([
                    'deleted_at' => now()
                ]);
            echo "- products: {$deletionCounts['products']} records soft deleted\n";

            // Product Stock with venue_id condition (with soft delete)
            $deletionCounts['product_stock'] = DB::table('product_stock')
                ->where('venue_id', 58)
                ->whereNotNull('bybest_id')
                ->update([
                    'deleted_at' => now()
                ]);
            echo "- product_stock: {$deletionCounts['product_stock']} records soft deleted\n";

            // Blog Categories with venue_id and bybest_id
            $deletionCounts['blog_categories'] = DB::table('blog_categories')
                ->where('venue_id', 58)
                ->delete();
            echo "- blog_categories: {$deletionCounts['blog_categories']} records deleted\n";

            // Blog entries with restaurant_id and bybest_id
            $deletionCounts['blogs'] = DB::table('blogs')
                ->where('restaurant_id', 58)
                ->delete();
            echo "- blogs: {$deletionCounts['blogs']} records deleted\n";

            // Categories with restaurant_id
            $deletionCounts['categories'] = DB::table('categories')
                ->where('restaurant_id', 58)
                ->delete();
            echo "- categories: {$deletionCounts['categories']} records deleted\n";

            // vb_store_attributes_options with bybest_id not null
            $deletionCounts['vb_store_attributes_options'] = DB::table('vb_store_attributes_options')
                ->whereNotNull('bybest_id')
                ->delete();
            echo "- attribute_options: {$deletionCounts['vb_store_attributes_options']} records deleted\n";

            // Product Categories with bybest_id not null
            $deletionCounts['product_category'] = DB::table('product_category')
                ->whereNotNull('bybest_id')
                ->delete();
            echo "- product_category: {$deletionCounts['product_category']} records deleted\n";

            // Product Collections with bybest_id not null
            $deletionCounts['product_collections'] = DB::table('product_collections')
                ->whereNotNull('bybest_id')
                ->delete();
            echo "- product_collections: {$deletionCounts['product_collections']} records deleted\n";

            // Product Variants and Related Tables
            $variantTables = [
                'vb_store_products_variants',
                'vb_store_product_variant_attributes',
                'product_groups',
                'product_category',
                'product_collections',
                'product_gallery'
            ];

            foreach ($variantTables as $table) {
                $deletionCounts[$table.'_variants'] = DB::table($table)
                    ->whereNotNull('bybest_id')
                    ->delete();
                echo "- {$table}: {$deletionCounts[$table.'_variants']} records deleted\n";
            }

            // Total deletion summary
            $totalDeleted = array_sum($deletionCounts);
            echo "\nTotal records affected: {$totalDeleted}\n";

            DB::commit();
            echo "Transaction committed successfully\n";
        } catch (\Exception $e) {
            DB::rollBack();
            echo "Error during migration: " . $e->getMessage() . "\n";
            throw $e;
        } finally {
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            echo "Foreign key checks re-enabled\n";
        }
    }

    /**
     * Reverse the migration.
     *
     * @return void
     */
    public function down()
    {
        echo "Down migration cannot restore deleted records. Please restore from backup if needed.\n";
    }
}
