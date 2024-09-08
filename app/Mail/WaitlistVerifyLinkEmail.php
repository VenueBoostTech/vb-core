<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WaitlistVerifyLinkEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $waitlister;
    public $email_verify_link;
    public $generate;

    public function __construct($waitlister, $email_verify_link, $generate = false)
    {
        $this->waitlister = $waitlister;
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
            ->subject("VenueBoost Waitlist – Confirm your email address!")
            ->view('emails.waitlist_verify_email_web')
            ->with([
                'waitlister' => $this->waitlister,
                'subject' => "VenueBoost Waitlist – Confirm your email address!",
                'email_verify_link' => $this->email_verify_link,
                'generate' => $this->generate,
            ]);
    }
}

