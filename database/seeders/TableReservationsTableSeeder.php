<?php

namespace Database\Seeders;

use App\Models\TableReservations;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TableReservationsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $tableReservations = [
            [
                'table_id' => 1,
                'reservation_id' => 1,
                'start_time' => '2022-01-01 12:00:00',
                'end_time' => '2022-01-01 14:00:00',
            ],
            [
                'table_id' => 2,
                'reservation_id' => 2,
                'start_time' => '2022-01-02 18:00:00',
                'end_time' => '2022-01-02 20:00:00',
            ],
            // ...
        ];
        foreach ($tableReservations as $tableReservation) {
            $newTableReservation = new TableReservations();
            $newTableReservation->table_id = $tableReservation['table_id'];
            $newTableReservation->reservation_id = $tableReservation['reservation_id'];
            $newTableReservation->start_time = $tableReservation['start_time'];
            $newTableReservation->end_time = $tableReservation['end_time'];
            $newTableReservation->save();
        }
    }
}
