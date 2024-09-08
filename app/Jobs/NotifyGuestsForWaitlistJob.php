<?php
namespace App\Jobs;
use App\Http\Controllers\v1\WaitlistController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyGuestsForWaitlistJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
    protected $waitlist;
    public function __construct($waitlist)
    {
        $this->waitlist = $waitlist;
    }
    public function handle()
    {
        WaitlistController::notifyBySMS($this->waitlist);
    }
}
