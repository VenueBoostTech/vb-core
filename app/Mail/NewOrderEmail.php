<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewOrderEmail extends Mailable
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
            ->subject('New order received')
            ->view('emails.new_order');
    }
}

