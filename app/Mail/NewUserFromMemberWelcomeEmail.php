<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewUserFromMemberWelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $password;

    /**
     * Create a new message instance.
     *
     * @param  \App\Models\User  $user
     * @param  string  $password
     * @return void
     */
    public function __construct($user, $password)
    {
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.whitelabel.new_user_from_member_welcome')
            ->from('noreply@venueboost.io', 'ByBest Shop')
        ->with([
                'userName' => $this->user->name,
                'userEmail' => $this->user->email,
                'password' => $this->password,
            ])
            ->subject("{$this->user->name}, Llogaria juaj në ByBest Shop është gati!");
    }
}
