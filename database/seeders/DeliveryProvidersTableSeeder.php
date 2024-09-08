<?php

namespace Database\Seeders;

use App\Models\DeliveryProvider;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliveryProvidersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DeliveryProvider::create([
            'name' => 'Snapfood',
            'code' => 'VB-DP-SNAPFD',
        ]);

        DeliveryProvider::create([
            'name' => 'Doordash',
            'code' => 'VB-DP-DOORDS',
        ]);
    }
}
