<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Welcome12HEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $venue_type;

    public function __construct($venue_type)
    {
        $this->venue_type = $venue_type;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        if ($this->venue_type === 'food') {
            return $this->from(env('MAIL_FROM_ADDRESS'), 'VenueBoost Inc.')
                ->subject('VenueBoost - Unlock the Power of Venue Management!')
                ->view('emails.food.welcome_12_h');
        }

        // sport_entertainment
        if ($this->venue_type === 'sport_entertainment') {
            return $this->from(env('MAIL_FROM_ADDRESS'), 'VenueBoost Inc.')
                ->subject('VenueBoost - Unlock the Power of Venue Management!')
                ->view('emails.sports_and_entertainment.welcome_12_h');
        }

        // retail
        if ($this->venue_type === 'retail') {
            return $this->from(env('MAIL_FROM_ADDRESS'), 'VenueBoost Inc.')
                ->subject('VenueBoost - Unlock the Power of Venue Management!')
                ->view('emails.retail.welcome_12_h');
        }

        // accommodation
        if ($this->venue_type === 'accommodation') {
            return $this->from(env('MAIL_FROM_ADDRESS'), 'VenueBoost Inc.')
                ->subject('VenueBoost - Unlock the Power of Venue Management!')
                ->view('emails.accommodation.welcome_12_h');
        }
    }
}

