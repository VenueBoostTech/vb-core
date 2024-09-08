<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerOrderConfirmationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $venue_name;

    public function __construct($venue_name, $order)
    {
        $this->order = $order;
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
            ->subject('Your ' .$this->venue_name .' order has been received!')
            ->view('emails.customer_order_confirmation')
            ->with(['order' => $this->order]);
    }
}

