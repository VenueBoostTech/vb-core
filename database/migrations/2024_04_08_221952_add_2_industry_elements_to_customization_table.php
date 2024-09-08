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
           ['industry_id' => 2, 'industry' => 'sport_entertainment', 'element_name' => 'SubscribeBtn', 'default_name' => 'Subscribe', 'description' => 'Subscribe to newsletter button'],
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
