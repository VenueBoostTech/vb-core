<?php
namespace App\Jobs;
use App\Http\Controllers\v1\ReservationController;
use App\Mail\HygieneCheckReminderEmail;
use App\Mail\HygieneInspectionReminderEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendHygieneInspectionReminderJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
    protected $hygieneInspection;
    public function __construct($hygieneInspection)
    {
        $this->hygieneInspection = $hygieneInspection;
    }

    public function handle()
    {
        // send to the venue email
        $venue = $this->hygieneInspection->venue;
        Mail::to($venue->email)->send(new HygieneInspectionReminderEmail($this->hygieneInspection, $venue));

    }
}
