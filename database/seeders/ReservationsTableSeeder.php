<?php

namespace Database\Seeders;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReservationsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $reservations = [
            [
                'table_id' => 1,
                'start_time' => Carbon::now(),
                'end_time' => Carbon::now()->addHour(),
                'seating_arrangement' => '2-top',
                'guest_count' => 2,
                'notes' => 'Special requests',
                'confirmed' => 1,
                'insertion_type' => 'manually_entered',
                'source' => 'call',
                'restaurant_id' => 1
            ],
            [
                'table_id' => 2,
                'start_time' => Carbon::now()->addHour(),
                'end_time' => Carbon::now()->addHours(2),
                'seating_arrangement' => '4-top',
                'guest_count' => 4,
                'notes' => null,
                'confirmed' => 2,
                'insertion_type' => 'manually_entered',
                'source' => 'google',
                'restaurant_id' => 1

            ],
        ];

        foreach ($reservations as $reservation) {
            $newReservation = new Reservation();
            $newReservation->table_id = $reservation['table_id'];
            $newReservation->start_time = $reservation['start_time'];
            $newReservation->end_time = $reservation['end_time'];
            $newReservation->seating_arrangement = $reservation['seating_arrangement'];
            $newReservation->guest_count = $reservation['guest_count'];
            $newReservation->notes = $reservation['notes'];
            $newReservation->confirmed = $reservation['confirmed'];
            $newReservation->insertion_type = $reservation['insertion_type'];
            $newReservation->source = $reservation['source'];
            $newReservation->restaurant_id = $reservation['restaurant_id'];
            $newReservation->save();
        }
    }
}
