<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrdersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $orders = [
            [
                'customer_id' => 1,
                'total_amount' => 12.5,
                'reservation_id' => 1,
                'restaurant_id' => 1
            ],
            [
                'customer_id' => 2,
                'total_amount' => 15,
                'reservation_id' => 1,
                'restaurant_id' => 1
            ],
            // ...
        ];
        foreach ($orders as $order) {
            $newOrder = new Order();
            $newOrder->customer_id = $order['customer_id'];
            $newOrder->total_amount = $order['total_amount'];
            $newOrder->reservation_id = $order['reservation_id'];
            $newOrder->restaurant_id = $order['restaurant_id'];
            $newOrder->save();
        }
    }
}
