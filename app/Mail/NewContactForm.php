<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewContactForm extends Mailable
{
    use Queueable, SerializesModels;

    public $venueName;
    public $fullName;
    public $email;
    public $phone;
    public $subject;
    public $content;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($venueName, $fullName, $email, $phone, $email_subject, $content)
    {
        $this->venueName = $venueName;
        $this->fullName = $fullName;
        $this->email = $email;
        $this->phone = $phone;
        $this->email_subject = $email_subject;
        $this->content = $content;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // add from

        return $this->view('emails.whitelabel.contact')
            ->from(env('MAIL_FROM_ADDRESS'), 'VenueBoost')
            ->subject('New Contact Form Submission')
            ->with([
                'venueName' => $this->venueName,
                'fullName' => $this->fullName,
                'email' => $this->email,
                'phone' => $this->phone,
                'subject_i' => $this->email_subject,
                'content' => $this->content
            ]);
    }
}

