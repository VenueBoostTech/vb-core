<?php

namespace App\Console\Commands;

use App\Jobs\SendReservationReminderSMSJob;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendReservationReminderSMS extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservation:set-reminder-sms';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder sms to user for reservation';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function handle()
    {

        // TODO: after v1 testing fix why for confirmed reservations this is not working and guests are null
        // Get all upcoming reservations that have not been confirmed
        $reservations = Reservation::with(['guests', 'table'])
            ->where('start_time', '>', Carbon::now())
            ->where('confirmed', 1)
            ->get();

        // Send SMS reminder
        foreach ($reservations as $reservation) {
            dispatch(new SendReservationReminderSMSJob($reservation));
        }
    }
}
