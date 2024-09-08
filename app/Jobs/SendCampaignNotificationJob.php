<?php
namespace App\Jobs;
use App\Http\Controllers\v1\CampaignController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCampaignNotificationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
    protected $campaign;
    public function __construct($campaign)
    {
        $this->campaign = $campaign;
    }
    public function handle()
    {
        CampaignController::sendNotification($this->campaign);
    }
}
