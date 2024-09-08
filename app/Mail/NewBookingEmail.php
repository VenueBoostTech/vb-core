<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewBookingEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $venue_name;
    public $guest_name;
    public $rental_unit;
    public $checkout_date;
    public $checkin_date;

    public function __construct($venue_name, $guest_name, $rental_unit, $checkin_date, $checkout_date )
    {
        $this->venue_name = $venue_name;
        $this->guest_name = $guest_name;
        $this->rental_unit = $rental_unit;
        $this->checkout_date = $checkout_date;
        $this->checkin_date = $checkin_date;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), $this->venue_name .' on VenueBoost')
            ->subject('New booking received')
            ->view('emails.accommodation.new_booking')
            ->with([
                'guest_name' => $this->guest_name,
                'rental_unit' => $this->rental_unit,
                'checkout_date' => $this->checkout_date,
                'checkin_date' => $this->checkin_date,
            ]);
    }
}

