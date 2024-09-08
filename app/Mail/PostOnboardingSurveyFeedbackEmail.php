<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PostOnboardingSurveyFeedbackEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $senderName;

    public function __construct($senderName)
    {
        $this->senderName = $senderName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), 'VenueBoost Customer Success Team')
            ->subject("We Value Your Feedback – Share Your VenueBoost Onboarding Experience")
            ->view('emails.post_onboarding_survey_email')
            ->with(['senderName' => $this->senderName, 'subject' => "We Value Your Feedback – Share Your VenueBoost Onboarding Experience"]);
    }
}

