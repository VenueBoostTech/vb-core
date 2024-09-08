<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\Restaurant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewMemberEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $member;
    public $venue;
    public $source;
    public $preferredBrand;

    /**
     * Create a new message instance.
     *
     * @param Member $member
     * @param Restaurant $venue
     * @param string $source
     * @param string $preferredBrand
     */
    public function __construct(Member $member, Restaurant $venue, string $source, string $preferredBrand = null)
    {
        $this->member = $member;
        $this->venue = $venue;
        $this->source = $source;
        $this->preferredBrand = $preferredBrand;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.whitelabel.newmember')
            ->from(env('MAIL_FROM_ADDRESS'), 'VenueBoost')
            ->subject('Regjistrim i një anëtari të ri')
            ->with([
                'member' => $this->member,
                'venue' => $this->venue,
                'source' => $this->source,
                'preferredBrand' => $this->preferredBrand,
            ]);
    }
}
