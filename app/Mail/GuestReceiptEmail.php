<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GuestReceiptEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $venue_name;
    public $guest_name;
    public $rental_unit;
    public $checkout_date;
    public $checkin_date;

    public function __construct($venue_name, $guest_receipt_data )
    {
        $this->venue_name = $venue_name;
        $this->guest_receipt_data = $guest_receipt_data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), $this->venue_name .' on VenueBoost')
            ->subject('Your receipt from '. $this->venue_name)
            ->view('emails.accommodation.guest_receipt')
            ->with([
                'guest_receipt_data' => $this->guest_receipt_data,
            ]);
    }
}

