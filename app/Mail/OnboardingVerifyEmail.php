<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OnboardingVerifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $venue;
    public $email_verify_link;
    public $generate;

    public function __construct($venue, $email_verify_link, $generate = false)
    {
        $this->venue = $venue;
        $this->email_verify_link = $email_verify_link;
        $this->generate = $generate;

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), 'VenueBoost Team')
            ->subject("Welcome to VenueBoost – Let's Get Ready!")
            ->view('emails.onboarding_verify_email')
            ->with([
                'venue' => $this->venue,
                'subject' => "Welcome to VenueBoost – Let's Get Ready!",
                'email_verify_link' => $this->email_verify_link,
            ]);
    }
}

