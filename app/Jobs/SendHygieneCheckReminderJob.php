<?php
namespace App\Jobs;
use App\Http\Controllers\v1\ReservationController;
use App\Mail\HygieneCheckReminderEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendHygieneCheckReminderJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
    protected $hygieneCheck;
    public function __construct($hygieneCheck)
    {
        $this->hygieneCheck = $hygieneCheck;
    }

    public function handle()
    {
        $sendMail = $this->hygieneCheck->venue->email;
        if ($this->hygieneCheck->assigned_to !== null) {
            $sendMail = $this->hygieneCheck->assigned_to;
        }

        // send to the venue email

        $venue = $this->hygieneCheck->venue;
        Mail::to($sendMail)->send(new HygieneCheckReminderEmail($this->hygieneCheck, $venue));

    }
}
