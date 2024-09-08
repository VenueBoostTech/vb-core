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
        Schema::create('industry_brand_customization_elements', function (Blueprint $table) {
            $table->id();
            $table->enum('industry', ['food', 'accommodation', 'retail', 'sport_entertainment'])->default('food');
            $table->string('element_name');
            $table->timestamps();
        });

        // Insert initial data
        $elements = [
            ['industry' => 'retail', 'element_name' => 'CartContinueButton'],
            ['industry' => 'retail', 'element_name' => 'CartPlusButton'],
            ['industry' => 'retail', 'element_name' => 'CartOrderButton'],
            ['industry' => 'retail', 'element_name' => 'AllButtons'],
            ['industry' => 'food', 'element_name' => 'FindATimeButton'],
            ['industry' => 'food', 'element_name' => 'AllButtons'],
            ['industry' => 'sport_entertainment', 'element_name' => 'BookNowButton'],
            ['industry' => 'sport_entertainment', 'element_name' => 'AllButtons'],
            ['industry' => 'accommodation', 'element_name' => 'AllButtons'],
            ['industry' => 'accommodation', 'element_name' => 'BookNowButton'],
            ['industry' => 'accommodation', 'element_name' => 'CheckAvailabilityButton'],
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
        Schema::dropIfExists('industry_brand_customization_elements');
    }
};
