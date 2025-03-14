<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class HygieneCheckReminderEmail extends Mailable
{
    use Queueable, SerializesModels;


    public $hygiene_check;
    public $venue;

    public function __construct($hygiene_check, $venue)
    {
        $this->hygiene_check = $hygiene_check;
        $this->venue = $venue;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), $this->venue->name .' on VenueBoost')
            ->subject("Hygiene Check Reminder!")
            ->view('emails.hygiene_check_reminder')
            ->with(['hygiene_check' => $this->hygiene_check]);
    }
}

