<?php

namespace App\Console\Commands;

use App\Jobs\SendInPlaceNotificationJob;
use App\Jobs\SendPreArrivalReminderJob;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendInPlaceNotification extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'communication-preferences:send-in-place-notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send in-place notifications to guests';

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

        $reservations = Reservation::with(['guests', 'table'])
            ->where('start_time', '<=', Carbon::now()) // Reservation has started
            ->where('end_time', '>', Carbon::now()) // Reservation has not finished yet
            ->where('confirmed', "1")
            ->get();

        // Send email/sms notification to each guest
        foreach ($reservations as $reservation) {
            dispatch(new SendInPlaceNotificationJob($reservation));
        }
    }
}
