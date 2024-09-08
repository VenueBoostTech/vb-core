<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PrivacyRightsRequestsVerifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $privacy_rights_request_contact_name;
    public $email_verify_link;

    public function __construct($privacy_rights_request_contact_name, $email_verify_link)
    {
        $this->privacy_rights_request_contact_name = $privacy_rights_request_contact_name;
        $this->email_verify_link = $email_verify_link;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), 'VenueBoost Legal Team')
            ->subject("Confirm Your Privacy Request Submission")
            ->view('emails.privacy_rights_requests_verify_email')
            ->with([
                'privacy_rights_request_contact_name' => $this->privacy_rights_request_contact_name,
                'email_verify_link' => $this->email_verify_link,
            ]);
    }
}

