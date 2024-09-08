<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorVerifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $restaurant_name;
    public $link;

    public function __construct($restaurant_name, $link)
    {
        $this->restaurant_name = $restaurant_name;
        $this->link = $link;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->subject('Welcome to VenueBoost! Verify your Email Address')
            ->view('emails.vendor_verify')
            ->with(['venue_name' => $this->restaurant_name, 'link' => $this->link]);
    }
}

