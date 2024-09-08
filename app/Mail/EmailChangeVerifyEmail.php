<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailChangeVerifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user_name;
    public $code;

    public function __construct($user_name, $code)
    {
        $this->user_name = $user_name;
        $this->code = $code;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        return $this->from(env('MAIL_FROM_ADDRESS'), 'VenueBoost Support Team')
            ->subject("Confirm Your Email Address Change")
            ->view('emails.verify_code')
            ->with(['user_name' => $this->user_name, 'code' => $this->code, 'subject' => "Confirm Your Email Address Change"]);
    }
}

