<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Waitlist;
use Faker\Factory as Faker;

class WaitlistTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create();

        for($i = 0; $i < 50; $i++) {
            Waitlist::create([
                'guest_name' => $faker->name,
                'party_size' => $faker->numberBetween(1, 10),
                'estimated_wait_time' => $faker->numberBetween(15, 120),
                'guest_phone' => $faker->phoneNumber,
                'guest_email' => $faker->email,
                'added_at' => $faker->dateTimeThisDecade(),
                'notified' => $faker->boolean,
                'is_vip' => $faker->boolean,
                'is_regular' => $faker->boolean,
                'arrival_time' => $faker->dateTimeThisDecade(),
                'seat_time' => $faker->dateTimeThisDecade(),
                'left_time' => $faker->dateTimeThisDecade(),
                'guest_id' => $faker->numberBetween(1, 3),
                'restaurant_id' => 1,
            ]);
        }
    }
}
