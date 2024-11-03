<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('products', function (Blueprint $table) {
            $table->integer('bybest_id')->nullable();
            $table->tinyInteger('featured')->nullable();
            $table->tinyInteger('is_best_seller')->default(0)->nullable(false);
            $table->text('product_tags')->nullable();
            $table->string('product_sku')->nullable();
            $table->string('sku_alpha')->nullable();
            $table->string('currency_alpha')->nullable();
            $table->string('tax_code_alpha')->nullable();
            $table->string('price_without_tax_alpha')->nullable();
            $table->string('unit_code_alpha')->nullable();
            $table->string('warehouse_alpha')->nullable();
            $table->integer('bb_points')->nullable();
            $table->integer('product_status')->default(1)->nullable(false);
            $table->tinyInteger('enable_stock')->default(0)->nullable();
            $table->integer('product_stock_status')->nullable();
            $table->tinyInteger('sold_invidually')->default(0)->nullable();
            $table->integer('stock_quantity')->nullable();
            $table->integer('low_quantity')->nullable();
            $table->integer('shipping_class')->nullable();
            $table->string('purchase_note')->nullable();
            $table->integer('menu_order')->nullable();
            $table->tinyInteger('allow_back_order')->default(0)->nullable();
            $table->tinyInteger('allow_customer_review')->default(1)->nullable();
            $table->dateTime('syncronize_at')->nullable(); 
            $table->string('title_al')->nullable()->after('title');
            $table->text('short_description_al')->nullable()->after('short_description');
            $table->text('description_al')->nullable()->after('description');

            $table->softDeletes();
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->integer('bybest_id')->nullable();
            $table->string('group_name')->nullable(false);
            $table->string('group_name_al')->nullable();
            $table->text('description')->nullable();
            $table->text('description_al')->nullable();
            $table->foreignId('venue_id')->constrained('restaurants')->nullable();
            $table->timestamps();
        });

        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->integer('bybest_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('group_id');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->integer('bybest_id')->nullable();
            $table->string('sidebar_logo_path')->nullable();
            $table->text('short_description')->nullable();
            $table->text('short_description_al')->nullable();
            $table->text('description_al')->nullable()->after('description');
            $table->text('keywords')->nullable();
            $table->text('more_info')->nullable();
            $table->integer('brand_order_no')->nullable();
            $table->integer('status_no')->default(1)->nullable();
        });

        Schema::table('vb_store_attributes_types', function (Blueprint $table) {
            $table->string('type_al')->nullable()->after('type');
            $table->text('description_al')->nullable()->after('description');
            $table->integer('bybest_id')->nullable();
        });

        Schema::table('vb_store_attributes', function (Blueprint $table) {
            $table->string('attr_name_al')->nullable()->after('attr_name');
            $table->text('attr_description_al')->nullable()->after('attr_description');
            $table->integer('bybest_id')->nullable();
            $table->integer('order_id')->nullable();
        });

        Schema::table('vb_store_attributes_options', function (Blueprint $table) {
            $table->string('option_name_al')->nullable()->after('option_name');
            $table->text('option_description_al')->nullable()->after('option_description');
            $table->integer('bybest_id')->nullable();
            $table->integer('order_id')->nullable();
        });

        Schema::table('vb_store_products_variants', function (Blueprint $table) {
            $table->text('product_long_description_al')->nullable()->after('product_long_description');
            $table->integer('bybest_id')->nullable();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->integer('bybest_id')->nullable();
            $table->string('category')->nullable();
            $table->string('category_al')->nullable();
            $table->string('category_url')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('subtitle_al')->nullable();
            $table->string('title_al')->nullable()->after('title');
            $table->string('description_al')->nullable()->after('description');
            $table->string('photo')->nullable();
            $table->integer('order_no')->nullable();
            $table->tinyInteger('visible')->default(1)->nullable(false);
        });
 
        Schema::create('product_collections', function (Blueprint $table) {
            $table->id();
            $table->integer('bybest_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('collection_id');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('collection_id')->references('id')->on('collections')->onDelete('cascade');
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->integer('bybest_id')->nullable();
            $table->string('description')->nullable();
        });
        
    }

    

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'bybest_id',
                'featured',
                'is_best_seller',
                'product_tags',
                'product_sku',
                'sku_alpha',
                'currency_alpha',
                'tax_code_alpha',
                'price_without_tax_alpha',
                'unit_code_alpha',
                'warehouse_alpha',
                'bb_points',
                'product_status',
                'enable_stock',
                'product_stock_status',
                'sold_invidually',
                'stock_quantity',
                'low_quantity',
                'shipping_class',
                'purchase_note',
                'menu_order',
                'allow_back_order',
                'allow_customer_review',
                'syncronize_at',
                'title_al',
                'short_description_al',
                'description_al'
            ]);
            $table->dropSoftDeletes();
        });
        
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn([
                'bybest_id',
                'sidebar_logo_path',
                'short_description',
                'short_description_al',
                'description_al',
                'keywords',
                'more_info',
                'brand_order_no',
                'status_no',
            ]);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'bybest_id',
                'category',
                'category_al',
                'category_url',
                'subtitle',
                'subtitle_al',
                'title_al',
                'description_al',
                'photo',
                'order_no',
                'visible',
            ]);
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn([
                'bybest_id',
                'description',
            ]);
        });

        Schema::dropIfExists('product_groups');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('product_collections');

        Schema::table('vb_store_attributes_types', function (Blueprint $table) {
            $table->dropColumn([
                'type_al',
                'description_al',
                'bybest_id',
            ]);
        });

        Schema::table('vb_store_attributes', function (Blueprint $table) {
            $table->dropColumn([
                'attr_name_al',
                'attr_description_al',
                'bybest_id',
                'order_id'
            ]);
        });

        Schema::table('vb_store_attributes_options', function (Blueprint $table) {
            $table->dropColumn([
                'option_name_al',
                'option_description_al',
                'bybest_id',
                'order_id'
            ]);
        });

        Schema::table('vb_store_products_variants', function (Blueprint $table) {
            $table->dropColumn([
                'product_long_description_al',
                'bybest_id',
            ]);
        });
    }
};
