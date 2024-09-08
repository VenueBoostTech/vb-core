<?php

namespace Database\Seeders;

use App\Models\Table;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TablesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        $tables = [
            ['number' => '001', 'name' => 'Table 1', 'dining_space_location_id' => null, 'seats' => 4, 'shape' => 'round', 'restaurant_id' => 1],
            ['number' => '002', 'name' => 'Table 2', 'dining_space_location_id' => null, 'seats' => 6, 'shape' => 'rectangular', 'restaurant_id' => 1],
            ['number' => '003', 'name' => 'Table 3', 'dining_space_location_id' => null, 'seats' => 8, 'shape' => 'square', 'restaurant_id' => 1],
            ['number' => '004', 'name' => 'Table 4', 'dining_space_location_id' => null, 'seats' => 2, 'shape' => 'round',  'restaurant_id' => 1],
            ['number' => '005', 'name' => 'Table 5', 'dining_space_location_id' => null, 'seats' => 4, 'shape' => 'round',  'restaurant_id' => 1],
            ['number' => '006', 'name' => 'Table 6', 'dining_space_location_id' => null, 'seats' => 6, 'shape' => 'round', 'restaurant_id' => 1],
        ];

        foreach ($tables as $table) {
            Table::create($table);
        }
    }
}
