<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CampaignEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $content;
    public $subject;
    public $venue_name;

    public function __construct($subject, $content, $venue_name)
    {
        $this->content = $content;
        $this->subject = $subject;
        $this->venue_name = $venue_name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), $this->venue_name .' on VenueBoost')
            ->subject($this->subject)
            ->view('emails.campaign')
            ->with(['content' => $this->content, 'subject' => $this->subject]);
    }
}

