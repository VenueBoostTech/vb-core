<?php
namespace App\Jobs;
use App\Http\Controllers\v2\EmailsController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class Welcome12HJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
    protected $venue;
    public function __construct($venue)
    {
        $this->venue = $venue;
    }
    public function handle()
    {
        EmailsController::sendWelcome12HEmail($this->venue);
    }
}
