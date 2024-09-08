<?php

namespace Database\Seeders;

use App\Models\SeatingArrangement;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SeatingArrangementsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $seating_arrangements = [
            [
                'table_id' => 1,
                'guest_ids' => [1,2,3],
                'start_time' => '2022-01-01 10:00:00',
                'end_time' => '2022-01-01 12:00:00'
            ],
            [
                'table_id' => 2,
                'guest_ids' => [4,5,6],
                'start_time' => '2022-01-01 11:00:00',
                'end_time' => '2022-01-01 14:00:00'
            ],
            // ...
        ];
        foreach ($seating_arrangements as $seating_arrangement) {
            $newSeatingArrangement = new SeatingArrangement();
            $newSeatingArrangement->table_id = $seating_arrangement['table_id'];
            $newSeatingArrangement->guest_ids = json_encode($seating_arrangement['guest_ids']);
            $newSeatingArrangement->start_time = $seating_arrangement['start_time'];
            $newSeatingArrangement->end_time = $seating_arrangement['end_time'];
            $newSeatingArrangement->save();
        }
    }
}
