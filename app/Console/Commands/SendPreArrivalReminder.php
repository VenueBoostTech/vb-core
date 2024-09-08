<?php

namespace App\Console\Commands;

use App\Jobs\SendPreArrivalReminderJob;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendPreArrivalReminder extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'communication-preferences:send-pre-arrival-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send pre-arrival reminders to guests';

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

        // Get all upcoming reservations that have been confirmed
        $reservations = Reservation::with(['guests', 'table'])
            ->where('start_time', '>', Carbon::now())
            ->where('confirmed', "1")
            ->get();


        // Send reminder
        foreach ($reservations as $reservation) {
            dispatch(new SendPreArrivalReminderJob($reservation));
        }
    }
}
