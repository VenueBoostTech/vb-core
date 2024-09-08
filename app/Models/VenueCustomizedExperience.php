<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueCustomizedExperience extends Model
{
    use HasFactory;


    protected $table = 'venue_customized_experience';

    protected $fillable = [
        'venue_id',
        'potential_venue_lead_id',
        'number_of_employees',
        'annual_revenue',
        'website',
        'social_media',
        'business_challenge',
        'other_business_challenge',
        'contact_reason',
        'how_did_you_hear_about_us',
        'how_did_you_hear_about_us_other',
        'biggest_additional_change',
        'post_onboarding_survey_email_sent_at',
        'post_onboarding_welcome_email_sent_at',
        'upgrade_from_trial_modal_seen'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(Restaurant::class);
    }

    public function potentialVenueLead(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(PotentialVenueLead::class);
    }
}
