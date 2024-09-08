<?php

namespace App\Console\Commands;

use App\Jobs\SendCampaignNotificationJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendCampaignNotification extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaign:send-notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send campaign notification (SMS, or Email) to guests on their scheduled date';

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

        // TODO: after v1 testing check why is beeing sent multiple times
        $campaigns = \App\Models\Campaign::where('sent', 0)
        //where('scheduled_date', $currentHourMinute)
        ->get();

        // Send email/sms notification
        foreach ($campaigns as $campaign) {
            $currentDate = now()->format('Y-m-d H:i');
            $scheduledDate = Carbon::parse($campaign->scheduled_date)->format('Y-m-d H:i');

            if ($currentDate !== $scheduledDate) {
               continue;
            } else {
                dispatch(new SendCampaignNotificationJob($campaign));
            }
        }
    }
}
