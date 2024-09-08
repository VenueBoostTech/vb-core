<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PotentialVenueLead extends Model
{
    use HasFactory;

    protected $table = 'potential_venue_leads';

    protected $fillable = [
        'email',
        'representative_first_name',
        'representative_last_name',
        'source',
        'email_verified',
        'started_onboarding',
        'completed_onboarding',
        'current_onboarding_step',
        'onboarded_completed_at',
        'venue_id',
        'affiliate_code',
        'affiliate_id',
        'affiliate_status',
        'referral_code',
        'referer_id',
        'referral_status',
        'promo_code_id',
        'promo_code',
        'from_chatbot',
        'from_september_new'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(Restaurant::class);
    }

    public function venueCustomizedExperience(): \Illuminate\Database\Eloquent\Relations\HasOne {
        return $this->hasOne(VenueCustomizedExperience::class);
    }

    public function venueLeadInfo(): \Illuminate\Database\Eloquent\Relations\HasOne {
        return $this->hasOne(VenueLeadInfo::class);
    }

    public function venueAffiliate(): \Illuminate\Database\Eloquent\Relations\HasOne {
        return $this->hasOne(VenueAffiliate::class);
    }

    public function restaurantReferral(): \Illuminate\Database\Eloquent\Relations\HasOne {
        return $this->hasOne(RestaurantReferral::class);
    }

    public function affiliate(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(Affiliate::class);
    }

    public function promoCode(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(PromotionalCode::class);
    }

    public function referrer(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(Restaurant::class, 'referer_id');
    }

    public function subscribedEmails(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SubscribedEmail::class);
    }

}
