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
        Schema::create('beds', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->integer('size_from')->nullable(); // e.g., 90 for a Single bed
            $table->integer('size_to')->nullable();   // e.g., 130 for a Single bed
            $table->timestamps();
        });

        // Seeding the beds' initial data
        DB::table('beds')->insert([
            ['name' => 'Single bed', 'size_from' => 90, 'size_to' => 130],
            ['name' => 'Double bed', 'size_from' => 131, 'size_to' => 150],
            ['name' => 'King bed', 'size_from' => 151, 'size_to' => 180],
            ['name' => 'Super King bed', 'size_from' => 181, 'size_to' => 210],
            ['name' => 'Bunk bed', 'size_from' => null, 'size_to' => null],
            ['name' => 'Sofa bed', 'size_from' => null, 'size_to' => null],
            ['name' => 'Futon mat', 'size_from' => null, 'size_to' => null]
        ]);

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('beds');
    }
};
