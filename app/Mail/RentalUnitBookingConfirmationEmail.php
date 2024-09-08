<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RentalUnitBookingConfirmationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $venue_name;
    public $rental_unit_booking_confirmation_data;

    public function __construct($venue_name, $rental_unit_booking_confirmation_data )
    {
        $this->venue_name = $venue_name;
        $this->rental_unit_booking_confirmation_data = $rental_unit_booking_confirmation_data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), $this->venue_name .' on VenueBoost')
            ->subject('Reservation confirmed for '. $this->venue_name)
            ->view('emails.accommodation.rental_unit_booking_confirmation_email')
            ->with([
                'rental_unit_booking_confirmation_data' => $this->rental_unit_booking_confirmation_data,
            ]);
    }
}

