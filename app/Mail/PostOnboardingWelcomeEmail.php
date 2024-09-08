<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PostOnboardingWelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user_name;
    public $email_verify_link;

    public function __construct($user_name, $email_verify_link)
    {
        $this->user_name = $user_name;
        $this->email_verify_link = $email_verify_link;

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), 'VenueBoost Customer Experience Team')
            ->subject("Welcome to the VenueBoost Family – Your New Journey Awaits!")
            ->view('emails.welcome_post_onboarding_email')
            ->with([
                'user_name' => $this->user_name,
                'subject' => "Welcome to the VenueBoost Family – Your New Journey Awaits!",
                'email_verify_link' => $this->email_verify_link,
            ]);
    }
}

