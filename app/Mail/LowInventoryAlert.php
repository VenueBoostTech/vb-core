<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LowInventoryAlert extends Mailable
{
    use Queueable, SerializesModels;

    public $alertData;
    public $venueName;

    public function __construct($alertData, $venueName)
    {
        $this->alertData = $alertData;
        $this->venueName = $venueName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), $this->venueName .' on VenueBoost')
            ->subject("Important: Low Stock Alert for {$this->alertData['product_name']}")
            ->view('emails.lowInventoryAlert')
            ->with(['alertData' => $this->alertData, 'subject' => "Important: Low Stock Alert for {$this->alertData['product_name']}"]);
    }
}

