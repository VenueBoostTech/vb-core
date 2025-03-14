<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\v3\Accommodation\CalendarConnectionController;
use App\Models\CalendarConnection;
use App\Models\RentalUnit;
use Illuminate\Http\Request;

class RefreshCalendarConnections extends Command
{
    protected $signature = 'bb-calendar:refresh-connections';
    protected $description = 'Refresh specific calendar connections for rental units';

    protected $calendarConnectionController;

    public function __construct(CalendarConnectionController $calendarConnectionController)
    {
        parent::__construct();
        $this->calendarConnectionController = $calendarConnectionController;
    }

    public function handle()
    {
        $connections = [
            // Rental unit 2
            ['rentalUnitId' => 2, 'connectionId' => 11], // Airbnb connection
            ['rentalUnitId' => 2, 'connectionId' => 15], // Booking.com connection

            // Rental unit 4
            ['rentalUnitId' => 4, 'connectionId' => 10], // Airbnb connection
            ['rentalUnitId' => 4, 'connectionId' => 16], // Booking.com connection

            // Rental unit 5
            ['rentalUnitId' => 5, 'connectionId' => 5], // Airbnb connection
            ['rentalUnitId' => 5, 'connectionId' => 12], // Booking.com connection

            // Rental unit 7
            ['rentalUnitId' => 7, 'connectionId' => 6], // Airbnb connection
            ['rentalUnitId' => 7, 'connectionId' => 14], // Booking.com connection

            // Rental unit 8
            ['rentalUnitId' => 8, 'connectionId' => 7], // Airbnb connection
            ['rentalUnitId' => 8, 'connectionId' => 13], // Booking.com connection
        ];

        foreach ($connections as $connection) {
            $calendarConnection = CalendarConnection::find($connection['connectionId']);

            if (!$calendarConnection) {
                $this->error("Calendar connection {$connection['connectionId']} not found for rental unit {$connection['rentalUnitId']}");
                continue;
            }

            $rentalUnit = RentalUnit::find($connection['rentalUnitId']);

            if (!$rentalUnit) {
                $this->error("Rental unit {$connection['rentalUnitId']} not found");
                continue;
            }

            $request = new Request();
            $request->merge(['venue_short_code' => $rentalUnit->venue->short_code]);

            try {
                $response = $this->calendarConnectionController->refreshCron($request, $connection['rentalUnitId'], $connection['connectionId']);

                if ($response instanceof \Illuminate\Http\JsonResponse) {
                    $data = $response->getData();
                    if (isset($data->message) && $data->message === 'Connection refreshed successfully') {
                        $this->info("Successfully refreshed connection {$connection['connectionId']} for rental unit {$connection['rentalUnitId']}");
                        $this->info("Sync results: " . json_encode($data->sync_results));
                    } else {
                        $this->error("Failed to refresh connection {$connection['connectionId']} for rental unit {$connection['rentalUnitId']}");
                        $this->error("Error: " . ($data->error ?? 'Unknown error'));
                    }
                } else {
                    $this->error("Unexpected response type for connection {$connection['connectionId']} for rental unit {$connection['rentalUnitId']}");
                }
            } catch (\Exception $e) {
                $this->error("Failed to refresh connection {$connection['connectionId']} for rental unit {$connection['rentalUnitId']}");
                $this->error("Error: " . $e->getMessage());
            }
        }
    }
}
