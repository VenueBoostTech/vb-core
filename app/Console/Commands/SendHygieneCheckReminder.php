<?php

namespace App\Console\Commands;

use App\Jobs\SendHygieneCheckReminderJob;
use App\Models\HygieneCheck;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendHygieneCheckReminder extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hygiene-standard:send-hygiene-check-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send hygiene check reminders';

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
        $hygieneChecks = HygieneCheck::whereNotNull('remind_hours_before')
            ->where('reminder_sent', false)
            ->get();


        foreach ($hygieneChecks as $check) {
            $reminderTime = Carbon::parse($check->check_date)->subHours($check->remind_hours_before);
            if (Carbon::now()->gte($reminderTime) && Carbon::now()->lt(Carbon::parse($check->check_date))) {
                dispatch(new SendHygieneCheckReminderJob($check));
                $check->update(['reminder_sent' => true]); // Set the flag to true
            }
        }
    }

}
