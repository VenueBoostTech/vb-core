<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CompletedPreOnboardingEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user_name;

    public function __construct($user_name)
    {
        $this->user_name = $user_name;

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
            ->view('emails.completed_pre_onboarding_email')
            ->with([
                'user_name' => $this->user_name,
                'subject' => "Welcome to the VenueBoost Family – Your New Journey Awaits!",
            ]);
    }
}

