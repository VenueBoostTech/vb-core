<?php

namespace App\Console\Commands;

use App\Jobs\SendHygieneCheckReminderJob;
use App\Jobs\SendHygieneInspectionReminderJob;
use App\Models\HygieneCheck;
use App\Models\HygieneInspection;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendHygieneInspectionReminder extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hygiene-standard:send-hygiene-inspection-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send hygiene inspection reminders';

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
        $hygieneInspections = HygieneInspection::whereNotNull('remind_me_before_log_date_hours')
            ->where('reminder_sent', false)
            ->get();


        foreach ($hygieneInspections as $inspection) {
            $reminderTime = Carbon::parse($inspection->inspection_date)->subHours($inspection->remind_me_before_log_date_hours);
            if (Carbon::now()->gte($reminderTime) && Carbon::now()->lt(Carbon::parse($inspection->inspection_date))) {
                dispatch(new SendHygieneInspectionReminderJob($inspection));
                $inspection->update(['reminder_sent' => true]); // Set the flag to true
            }
        }
    }

}
