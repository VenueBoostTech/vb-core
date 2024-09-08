<?php

namespace App\Console\Commands;

use App\Jobs\NotifyGuestsForWaitlistJob;
use App\Models\Reservation;
use App\Models\Waitlist;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyGuestsForWaitlist extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'waitlist:notify-guests';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send sms to guests for waitlist';

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
        $waitlists = Waitlist::where('estimated_wait_time', '<', Carbon::now())
            ->where('notified', false)
            ->orWhere(function($query) {
                $query->whereNull('guest_notified_at')
                    ->orWhere('guest_notified_at', '<', Carbon::now()->subMinutes(30));
            })->get();

        //dd($waitlists);
        // Notify Guests
        foreach ($waitlists as $waitlist) {
            dispatch(new NotifyGuestsForWaitlistJob($waitlist));
        }
    }
}
