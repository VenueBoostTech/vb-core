<?php

namespace App\Mail;

use App\Models\Restaurant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewStaffEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $venue;

    /**
     * Create a new message instance.
     *
     * @param Restaurant $venue
     */
    public function __construct(Restaurant $venue)
    {
        $this->venue = $venue;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.newstaff')
            ->from(env('MAIL_FROM_ADDRESS'), 'Staffluent')
            ->subject('Welcome to Staffluent!')
            ->with([
                'venue' => $this->venue,
            ]);
    }
}
