<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewReservationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $venue_name;

    public function __construct($venue_name)
    {
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
            ->subject('New reservation received')
            ->view('emails.new_reservation');
    }
}

