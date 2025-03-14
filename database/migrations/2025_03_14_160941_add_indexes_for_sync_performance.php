<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddIndexesForSyncPerformance extends Migration
{
    /**
     * Run the migration.
     *
     * @return void
     */
    public function up()
    {
        // Helper function to check if index exists
        $indexExists = function ($table, $index) {
            return $this->hasIndex($table, $index);
        };

        // Product table indexes
        Schema::table('products', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('products', 'products_bybest_id_index')) {
                $table->index('bybest_id');
            }

            if (!$indexExists('products', 'products_restaurant_id_index')) {
                $table->index('restaurant_id');
            }

            if (!$indexExists('products', 'products_bybest_id_restaurant_id_index')) {
                $table->index(['bybest_id', 'restaurant_id']);
            }

            if (!$indexExists('products', 'products_product_type_index')) {
                $table->index('product_type');
            }

            if (!$indexExists('products', 'products_available_index')) {
                $table->index('available');
            }

            if (!$indexExists('products', 'products_is_for_retail_index')) {
                $table->index('is_for_retail');
            }

            if (!$indexExists('products', 'products_brand_id_index')) {
                $table->index('brand_id');
            }
        });

        // Brand table indexes
        Schema::table('brands', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('brands', 'brands_bybest_id_index')) {
                $table->index('bybest_id');
            }

            if (!$indexExists('brands', 'brands_venue_id_index')) {
                $table->index('venue_id');
            }

            if (!$indexExists('brands', 'brands_bybest_id_venue_id_index')) {
                $table->index(['bybest_id', 'venue_id']);
            }
        });

        // Category table indexes
        Schema::table('categories', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('categories', 'categories_bybest_id_index')) {
                $table->index('bybest_id');
            }

            if (!$indexExists('categories', 'categories_restaurant_id_index')) {
                $table->index('restaurant_id');
            }

            if (!$indexExists('categories', 'categories_bybest_id_restaurant_id_index')) {
                $table->index(['bybest_id', 'restaurant_id']);
            }

            if (!$indexExists('categories', 'categories_parent_id_index')) {
                $table->index('parent_id');
            }
        });

        // Collection table indexes
        Schema::table('collections', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('collections', 'collections_bybest_id_index')) {
                $table->index('bybest_id');
            }

            if (!$indexExists('collections', 'collections_venue_id_index')) {
                $table->index('venue_id');
            }

            if (!$indexExists('collections', 'collections_bybest_id_venue_id_index')) {
                $table->index(['bybest_id', 'venue_id']);
            }
        });

        // Group table indexes
        Schema::table('groups', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('groups', 'groups_bybest_id_index')) {
                $table->index('bybest_id');
            }

            if (!$indexExists('groups', 'groups_venue_id_index')) {
                $table->index('venue_id');
            }

            if (!$indexExists('groups', 'groups_bybest_id_venue_id_index')) {
                $table->index(['bybest_id', 'venue_id']);
            }
        });

        // Inventory retail indexes
        Schema::table('inventory_retail', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('inventory_retail', 'inventory_retail_sku_index')) {
                $table->index('sku');
            }

            if (!$indexExists('inventory_retail', 'inventory_retail_venue_id_index')) {
                $table->index('venue_id');
            }

            if (!$indexExists('inventory_retail', 'inventory_retail_product_id_index')) {
                $table->index('product_id');
            }

            if (!$indexExists('inventory_retail', 'inventory_retail_sku_venue_id_index')) {
                $table->index(['sku', 'venue_id']);
            }

            if (!$indexExists('inventory_retail', 'inventory_retail_article_no_index')) {
                $table->index('article_no');
            }
        });

        // Product variant indexes
        Schema::table('vb_store_products_variants', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('vb_store_products_variants', 'vb_store_products_variants_bybest_id_index')) {
                $table->index('bybest_id');
            }

            if (!$indexExists('vb_store_products_variants', 'vb_store_products_variants_product_id_index')) {
                $table->index('product_id');
            }

            if (!$indexExists('vb_store_products_variants', 'vb_store_products_variants_venue_id_index')) {
                $table->index('venue_id');
            }

            if (!$indexExists('vb_store_products_variants', 'vb_store_products_variants_bybest_id_venue_id_index')) {
                $table->index(['bybest_id', 'venue_id']);
            }

            if (!$indexExists('vb_store_products_variants', 'vb_store_products_variants_variation_sku_index')) {
                $table->index('variation_sku');
            }

            if (!$indexExists('vb_store_products_variants', 'vb_store_products_variants_article_no_index')) {
                $table->index('article_no');
            }
        });

        // Attribute indexes
        Schema::table('vb_store_attributes', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('vb_store_attributes', 'vb_store_attributes_bybest_id_index')) {
                $table->index('bybest_id');
            }

            if (!$indexExists('vb_store_attributes', 'vb_store_attributes_type_id_index')) {
                $table->index('type_id');
            }
        });

        // Attribute option indexes
        Schema::table('vb_store_attributes_options', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('vb_store_attributes_options', 'vb_store_attributes_options_bybest_id_index')) {
                $table->index('bybest_id');
            }

            if (!$indexExists('vb_store_attributes_options', 'vb_store_attributes_options_attribute_id_index')) {
                $table->index('attribute_id');
            }
        });

        // Product attribute indexes
        Schema::table('vb_store_product_attributes', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('vb_store_product_attributes', 'vb_store_product_attributes_bybest_id_index')) {
                $table->index('bybest_id');
            }

            if (!$indexExists('vb_store_product_attributes', 'vb_store_product_attributes_product_id_index')) {
                $table->index('product_id');
            }

            if (!$indexExists('vb_store_product_attributes', 'vb_store_product_attributes_attribute_id_index')) {
                $table->index('attribute_id');
            }

            if (!$indexExists('vb_store_product_attributes', 'vb_store_product_attributes_venue_id_index')) {
                $table->index('venue_id');
            }
        });

        // Product variant attribute indexes
        Schema::table('vb_store_product_variant_attributes', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('vb_store_product_variant_attributes', 'vb_store_product_variant_attributes_bybest_id_index')) {
                $table->index('bybest_id');
            }

            if (!$indexExists('vb_store_product_variant_attributes', 'vb_store_product_variant_attributes_variant_id_index')) {
                $table->index('variant_id');
            }

            if (!$indexExists('vb_store_product_variant_attributes', 'vb_store_product_variant_attributes_attribute_id_index')) {
                $table->index('attribute_id');
            }

            if (!$indexExists('vb_store_product_variant_attributes', 'vb_store_product_variant_attributes_venue_id_index')) {
                $table->index('venue_id');
            }
        });

        // Product category indexes
        Schema::table('product_category', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('product_category', 'product_category_bybest_id_index')) {
                $table->index('bybest_id');
            }

            if (!$indexExists('product_category', 'product_category_product_id_index')) {
                $table->index('product_id');
            }

            if (!$indexExists('product_category', 'product_category_category_id_index')) {
                $table->index('category_id');
            }
        });

        // Product collection indexes
        Schema::table('product_collections', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('product_collections', 'product_collections_bybest_id_index')) {
                $table->index('bybest_id');
            }

            if (!$indexExists('product_collections', 'product_collections_product_id_index')) {
                $table->index('product_id');
            }

            if (!$indexExists('product_collections', 'product_collections_collection_id_index')) {
                $table->index('collection_id');
            }
        });

        // Product group indexes
        Schema::table('product_groups', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('product_groups', 'product_groups_bybest_id_index')) {
                $table->index('bybest_id');
            }

            if (!$indexExists('product_groups', 'product_groups_product_id_index')) {
                $table->index('product_id');
            }

            if (!$indexExists('product_groups', 'product_groups_group_id_index')) {
                $table->index('group_id');
            }
        });

        // Product gallery indexes
        Schema::table('product_gallery', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('product_gallery', 'product_gallery_bybest_id_index')) {
                $table->index('bybest_id');
            }

            if (!$indexExists('product_gallery', 'product_gallery_product_id_index')) {
                $table->index('product_id');
            }
        });

        // Product stock indexes
        Schema::table('product_stock', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('product_stock', 'product_stock_bybest_id_index')) {
                $table->index('bybest_id');
            }

            if (!$indexExists('product_stock', 'product_stock_article_no_index')) {
                $table->index('article_no');
            }

            if (!$indexExists('product_stock', 'product_stock_venue_id_index')) {
                $table->index('venue_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove Product table indexes
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['bybest_id']);
            $table->dropIndex(['restaurant_id']);
            $table->dropIndex(['bybest_id', 'restaurant_id']);
            $table->dropIndex(['product_type']);
            $table->dropIndex(['available']);
            $table->dropIndex(['is_for_retail']);
            $table->dropIndex(['brand_id']);
        });

        // Remove Brand table indexes
        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex(['bybest_id']);
            $table->dropIndex(['venue_id']);
            $table->dropIndex(['bybest_id', 'venue_id']);
        });

        // Remove Category table indexes
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['bybest_id']);
            $table->dropIndex(['restaurant_id']);
            $table->dropIndex(['bybest_id', 'restaurant_id']);
            $table->dropIndex(['parent_id']);
        });

        // Remove Collection table indexes
        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex(['bybest_id']);
            $table->dropIndex(['venue_id']);
            $table->dropIndex(['bybest_id', 'venue_id']);
        });

        // Remove Group table indexes
        Schema::table('groups', function (Blueprint $table) {
            $table->dropIndex(['bybest_id']);
            $table->dropIndex(['venue_id']);
            $table->dropIndex(['bybest_id', 'venue_id']);
        });

        // Remove Inventory retail indexes
        Schema::table('inventory_retail', function (Blueprint $table) {
            $table->dropIndex(['sku']);
            $table->dropIndex(['venue_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['sku', 'venue_id']);
            $table->dropIndex(['article_no']);
        });

        // Remove Product variant indexes
        Schema::table('vb_store_products_variants', function (Blueprint $table) {
            $table->dropIndex(['bybest_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['venue_id']);
            $table->dropIndex(['bybest_id', 'venue_id']);
            $table->dropIndex(['variation_sku']);
            $table->dropIndex(['article_no']);
        });

        // Remove Attribute indexes
        Schema::table('vb_store_attributes', function (Blueprint $table) {
            $table->dropIndex(['bybest_id']);
            $table->dropIndex(['type_id']);
        });

        // Remove Attribute option indexes
        Schema::table('vb_store_attributes_options', function (Blueprint $table) {
            $table->dropIndex(['bybest_id']);
            $table->dropIndex(['attribute_id']);
        });

        // Remove Product attribute indexes
        Schema::table('vb_store_product_attributes', function (Blueprint $table) {
            $table->dropIndex(['bybest_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['attribute_id']);
            $table->dropIndex(['venue_id']);
        });

        // Remove Product variant attribute indexes
        Schema::table('vb_store_product_variant_attributes', function (Blueprint $table) {
            $table->dropIndex(['bybest_id']);
            $table->dropIndex(['variant_id']);
            $table->dropIndex(['attribute_id']);
            $table->dropIndex(['venue_id']);
        });

        // Remove Product category indexes
        Schema::table('product_category', function (Blueprint $table) {
            $table->dropIndex(['bybest_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['category_id']);
        });

        // Remove Product collection indexes
        Schema::table('product_collections', function (Blueprint $table) {
            $table->dropIndex(['bybest_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['collection_id']);
        });

        // Remove Product group indexes
        Schema::table('product_groups', function (Blueprint $table) {
            $table->dropIndex(['bybest_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['group_id']);
        });

        // Remove Product gallery indexes
        Schema::table('product_gallery', function (Blueprint $table) {
            $table->dropIndex(['bybest_id']);
            $table->dropIndex(['product_id']);
        });

        // Remove Product stock indexes
        Schema::table('product_stock', function (Blueprint $table) {
            $table->dropIndex(['bybest_id']);
            $table->dropIndex(['article_no']);
            $table->dropIndex(['venue_id']);
        });
    }

    /**
     * Check if index exists
     *
     * @param string $table
     * @param string $index
     * @return bool
     */
    private function hasIndex($table, $index)
    {
        $conn = Schema::getConnection()->getDoctrineSchemaManager();

        try {
            $indexes = $conn->listTableIndexes($table);
            return array_key_exists($index, $indexes);
        } catch (\Exception $e) {
            return false;
        }
    }
}
