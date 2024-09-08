<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GuestsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $guests = [
            [
                'name' => 'John Smith',
                'email' => 'johnsmith@example.com',
                'phone' => '555-555-5555',
                'address' => '123 Main St, Anytown USA',
                'restaurant_id' => 1,
            ],
            [
                'name' => 'Jane Doe',
                'email' => 'janedoe@example.com',
                'phone' => '555-555-5556',
                'address' => '456 Park Ave, Anytown USA',
                'restaurant_id' => 1,
            ],
            [
                'name' => 'Bob Johnson',
                'email' => 'bobjohnson@example.com',
                'phone' => '555-555-5557',
                'address' => '789 Elm St, Anytown USA',
                'restaurant_id' => 1,
            ]
        ];

        foreach ($guests as $guest) {
            DB::table('guests')->insert($guest);
        }
    }
}
