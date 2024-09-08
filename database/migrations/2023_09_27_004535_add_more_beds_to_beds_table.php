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
        Schema::table('beds', function (Blueprint $table) {
            $beds = [
                ['name' => 'Queen Bed', 'size_from' => null, 'size_to' => null],
                ['name' => 'Small double', 'size_from' => null, 'size_to' => null],
                ['name' => 'Sofa', 'size_from' => null, 'size_to' => null],
                ['name' => 'Airbed', 'size_from' => null, 'size_to' => null],
                ['name' => 'Cot', 'size_from' => null, 'size_to' => null],
                ['name' => 'Floor mattress', 'size_from' => null, 'size_to' => null],
                ['name' => 'Toddler bed', 'size_from' => null, 'size_to' => null],
                ['name' => 'Hammock', 'size_from' => null, 'size_to' => null],
                ['name' => 'Water bed', 'size_from' => null, 'size_to' => null],
            ];

            foreach ($beds as $bed) {
                DB::table('beds')->insert([
                    'name' => $bed['name'],
                    'size_from' => $bed['size_from'],
                    'size_to' => $bed['size_to']
                ]);
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
        Schema::table('beds', function (Blueprint $table) {
            $bedNames = [
                'Queen Bed',
                'Small double',
                'Sofa',
                'Airbed',
                'Cot',
                'Floor mattress',
                'Toddler bed',
                'Hammock',
                'Water bed',
            ];

            DB::table('beds')->whereIn('name', $bedNames)->delete();
        });
    }
};
