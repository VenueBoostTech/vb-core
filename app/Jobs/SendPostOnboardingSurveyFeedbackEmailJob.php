<?php
namespace App\Jobs;
use App\Enums\OrderStatus;
use App\Http\Controllers\v1\ReservationController;
use App\Http\Controllers\v2\OnboardingController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPostOnboardingSurveyFeedbackEmailJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
    protected $venue;
    public function __construct($venue)
    {
        $this->venue = $venue;
    }
    public function handle()
    {
        OnboardingController::sendPostOnboardingSurveyEmail($this->venue);
    }
}
