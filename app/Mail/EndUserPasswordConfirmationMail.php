<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EndUserPasswordConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $email;
    public $name;
    public function __construct($email,$name, $venue_name, $venue_logo)
    {
        $this->email = $email;
        $this->name = $name;
        $this->venue_name = $venue_name;
        $this->venue_logo = $venue_logo;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), $this->venue_name .' on VenueBoost')
            ->subject('Your Password has been reset')
            ->view('emails.enduser_reset_password_confirmation')
            ->with([
                'email' => $this->email,
                'name' => $this->name,
                'venue_name' => $this->venue_name,
                'venue_logo' => $this->venue_logo
            ]);
    }
}
