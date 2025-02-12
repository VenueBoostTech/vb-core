<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ByBestShopUserVerifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $link;

    public function __construct($name, $link)
    {
        $this->name = $name;
        $this->link = $link;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this ->from('noreply@venueboost.io', 'ByBest Shop')
            ->subject('Mirë se vini në ByBest Shop! Verifikoni Adresën tuaj të Email-it')
            ->view('emails.whitelabel.user_verify_email')
            ->with(['name' => $this->name, 'link' => $this->link]);
    }
}

