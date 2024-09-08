<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
        $elements = [
            ['industry_id' => 4, 'industry' => 'retail', 'element_name' => 'Tags', 'default_name' => 'Tags', 'description' => 'Add tags for products'],
            ['industry_id' => 4, 'industry' => 'retail', 'element_name' => 'OutOfStock', 'default_name' => 'Out of Stock', 'description' => 'Indicates that a product is out of stock'],
            ['industry_id' => 4, 'industry' => 'retail', 'element_name' => 'SubscribeBtn', 'default_name' => 'Subscribe', 'description' => 'Subscribe to newsletter button'],
            ['industry_id' => 4, 'industry' => 'retail', 'element_name' => 'YourCart', 'default_name' => 'Your Cart', 'description' => 'Your Cart Button'],
            ['industry_id' => 4, 'industry' => 'retail', 'element_name' => 'AddToCart', 'default_name' => 'Add to Cart', 'description' => 'Add to Cart Button'],
        ];

        DB::table('industry_brand_customization_elements')->insert($elements);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customization', function (Blueprint $table) {
            //
        });
    }
};
