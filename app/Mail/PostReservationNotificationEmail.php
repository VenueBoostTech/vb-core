<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PostReservationNotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $content;
    public $venue_name;

    public function __construct($venue_name, $content)
    {
        $this->content = $content;
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
            ->subject($this->venue_name . ' on VenueBoost - How was your reservation?')
            ->view('emails.post_reservation_notification')
            ->with(['content' => $this->content]);
    }
}

