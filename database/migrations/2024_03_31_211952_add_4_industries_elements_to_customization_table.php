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
        $elements = [
            ['industry_id' => 1, 'industry' => 'food', 'element_name' => 'ContactFormLeftBlock', 'default_name' => 'ContactFormLeftBlock', 'description' => 'Contact Form Left Block'],
            ['industry_id' => 1, 'industry' => 'food', 'element_name' => 'ContactFormBtn', 'default_name' => 'ContactFormBtn', 'description' => 'Contact Form Button'],
            ['industry_id' => 1, 'industry' => 'food', 'element_name' => 'ContactFormTopLabel', 'default_name' => 'ContactFormTopLabel', 'description' => 'Contact Form Top Label'],
            ['industry_id' => 2, 'industry' => 'sport_entertainment', 'element_name' => 'ContactFormLeftBlock', 'default_name' => 'ContactFormLeftBlock', 'description' => 'Contact Form Left Block'],
            ['industry_id' => 2, 'industry' => 'sport_entertainment', 'element_name' => 'ContactFormBtn', 'default_name' => 'ContactFormBtn', 'description' => 'Contact Form Button'],
            ['industry_id' => 2, 'industry' => 'sport_entertainment', 'element_name' => 'ContactFormTopLabel', 'default_name' => 'ContactFormTopLabel', 'description' => 'Contact Form Top Label'],
            ['industry_id' => 3, 'industry' => 'accommodation', 'element_name' => 'ContactFormLeftBlock', 'default_name' => 'ContactFormLeftBlock', 'description' => 'Contact Form Left Block'],
            ['industry_id' => 3, 'industry' => 'accommodation', 'element_name' => 'ContactFormBtn', 'default_name' => 'ContactFormBtn', 'description' => 'Contact Form Button'],
            ['industry_id' => 3, 'industry' => 'accommodation', 'element_name' => 'ContactFormTopLabel', 'default_name' => 'ContactFormTopLabel', 'description' => 'Contact Form Top Label'],
            ['industry_id' => 4, 'industry' => 'retail', 'element_name' => 'ContactFormLeftBlock', 'default_name' => 'ContactFormLeftBlock', 'description' => 'Contact Form Left Block'],
            ['industry_id' => 4, 'industry' => 'retail', 'element_name' => 'ContactFormBtn', 'default_name' => 'ContactFormBtn', 'description' => 'Contact Form Button'],
            ['industry_id' => 4, 'industry' => 'retail', 'element_name' => 'ContactFormTopLabel', 'default_name' => 'ContactFormTopLabel', 'description' => 'Contact Form Top Label'],
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
    }
};
