<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VenueDemoApprovedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $restaurant_name;
    public $link;

    public function __construct($restaurant_name)
    {
        $this->restaurant_name = $restaurant_name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->subject('VenueBoost Demo Approval and Request for Additional Information')
            ->view('emails.venue_demo_approved')
            ->with(['venue_name' => $this->restaurant_name]);
    }
}

