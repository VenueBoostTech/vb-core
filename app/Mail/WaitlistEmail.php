<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WaitlistEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $waitlister;

    public function __construct($waitlister)
    {
        $this->waitlister = $waitlister;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), 'VenueBoost Team')
            ->subject("Welcome to VenueBoost Waitlist – Let's Get Ready!")
            ->view('emails.waitlistweb')
            ->with(['waitlister' => $this->waitlister, 'subject' => "Welcome to VenueBoost Waitlist – Let's Get Ready!"]);
    }
}

