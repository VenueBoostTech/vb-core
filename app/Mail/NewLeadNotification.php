<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewLeadNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $email;
    public $sender;

    public function __construct($name, $email, $sender)
    {
        $this->name = $name;
        $this->email = $email;
        $this->sender = $sender;
    }

    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->subject('🚀 New Lead on the Horizon! 🚀')
            ->view('emails.crm.new_lead_on_horizon')
            ->with([
                'sender' => $this->sender,
                'name' => $this->name,
                'email' => $this->email,
            ]);
    }
}
