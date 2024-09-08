<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AffiliateConfirmedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $affiliater;

    public function __construct($affiliater)
    {
        $this->affiliater = $affiliater;

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), 'VenueBoost Team')
            ->subject("Welcome Aboard! Your VenueBoost Affiliate Program Application is Approved")
            ->view('emails.affiliate_approved_email_web')
            ->with([
                'waitlister' => $this->affiliater,
                'subject' => "Welcome Aboard! Your VenueBoost Affiliate Program Application is Approved",
            ]);
    }
}

