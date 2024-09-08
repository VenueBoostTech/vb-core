<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RetrieveOrdersFromUberEats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integrations:retrieve-orders-from-uber-eats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retrieves new orders from Uber Eats and saves them to the database.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $uberEatsReservations = [
            [
                'table_id' => 1,
                'start_time' => Carbon::now(),
                'end_time' => Carbon::now()->addHour(),
                'seating_arrangement' => '2-top',
                'guest_count' => 2,
                'notes' => 'Special requests',
                'confirmed' => 1,
                'insertion_type' => 'from_integration',
                'source' => 'ubereats',
                'restaurant_id' => 1
            ]
        ];

        foreach ($uberEatsReservations as $reservation) {
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
