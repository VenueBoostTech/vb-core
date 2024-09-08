<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VenueDemoSuccessEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $restaurant_name;
    public $email;
    public $password;

    public function __construct($restaurant_name, $restaurant_email, $restaurant_password)
    {
        $this->restaurant_name = $restaurant_name;
        $this->email = $restaurant_email;
        $this->password = $restaurant_password;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->subject('Demo Account Successfully Created - VenueBoost')
            ->view('emails.demo_success')
            ->with(['venue_name' => $this->restaurant_name, 'user_email' => $this->email, 'user_password' => $this->password]);
    }
}

