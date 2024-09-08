<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EndUserCardCheckinEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $points_earned;
    public $venue_name;

    public function __construct($points_earned, $venue_name)
    {
        $this->points_earned = $points_earned;
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
            ->subject('Check-in at '. $this->venue_name .' on VenueBoost')
            ->view('emails.customer_card_checkin_confirm')
            ->with(['points_earned' => $this->points_earned]);
    }
}

