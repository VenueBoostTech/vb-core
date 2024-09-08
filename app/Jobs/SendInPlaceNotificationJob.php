<?php
namespace App\Jobs;
use App\Http\Controllers\v1\ReservationController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInPlaceNotificationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
    protected $reservation;
    public function __construct($reservation)
    {
        $this->reservation = $reservation;
    }
    public function handle()
    {
        ReservationController::sendInPlaceNotification($this->reservation);
    }
}
