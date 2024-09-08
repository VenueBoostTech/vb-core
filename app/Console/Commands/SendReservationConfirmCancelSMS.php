<?php

namespace App\Console\Commands;

use App\Jobs\SendReservationConfirmCancelSMSJob;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendReservationConfirmCancelSMS extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservation:set-confirm-cancel-sms';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send confirm cancel sms to user for reservation';

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

        // Get all upcoming reservations that have not been confirmed
        $reservations = Reservation::with(['guests', 'table'])
            ->where('start_time', '>', Carbon::now())
            ->where('confirmed', '0')
            ->get();

        // Send SMS reminder
        foreach ($reservations as $reservation) {
            dispatch(new SendReservationConfirmCancelSMSJob($reservation));
        }
    }
}
