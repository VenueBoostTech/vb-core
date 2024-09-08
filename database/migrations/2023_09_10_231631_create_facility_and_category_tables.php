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
        Schema::create('facility_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('name');
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('facility_categories')->onDelete('cascade');
        });

        // Inserting facility categories and their corresponding facilities.
        DB::table('facility_categories')->insert([
            ['name' => 'General'],
            ['name' => 'Cooking and cleaning'],
            ['name' => 'Entertainment'],
            ['name' => 'Outside and view']
        ]);

        DB::table('facilities')->insert([
            ['category_id' => 1, 'name' => 'Air conditioning'],
            ['category_id' => 1, 'name' => 'Heating'],
            ['category_id' => 1, 'name' => 'Free Wifi'],
            ['category_id' => 1, 'name' => 'Electric vehicle charging station'],
            ['category_id' => 2, 'name' => 'Kitchen'],
            ['category_id' => 2, 'name' => 'Kitchenette'],
            ['category_id' => 2, 'name' => 'Washing machine'],
            ['category_id' => 3, 'name' => 'Flat-Screen TV'],
            ['category_id' => 3, 'name' => 'Swimming pool'],
            ['category_id' => 3, 'name' => 'Hot tub'],
            ['category_id' => 3, 'name' => 'Minibar'],
            ['category_id' => 3, 'name' => 'Sauna'],
            ['category_id' => 4, 'name' => 'Balcony'],
            ['category_id' => 4, 'name' => 'Garden view'],
            ['category_id' => 4, 'name' => 'Terrace'],
            ['category_id' => 4, 'name' => 'View']
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('facility_categories');
    }
};
