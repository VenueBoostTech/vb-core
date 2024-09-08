<?php

namespace Database\Seeders;

use App\Models\DeliveryProvider;
use App\Models\DeliveryProviderRestaurant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliveryProviderRestaurantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DeliveryProviderRestaurant::create([
            'delivery_provider_id' => 2,
            'restaurant_id' => 1,
        ]);
    }
}
