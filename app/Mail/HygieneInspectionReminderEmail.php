<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class HygieneInspectionReminderEmail extends Mailable
{
    use Queueable, SerializesModels;


    public $hygiene_inspection;
    public $venue;

    public function __construct($hygiene_inspection, $venue)
    {
        $this->hygiene_inspection = $hygiene_inspection;
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
            ->subject("Hygiene Inspection Reminder!")
            ->view('emails.hygiene_inspection_reminder')
            ->with(['hygiene_inspection' => $this->hygiene_inspection]);
    }
}

