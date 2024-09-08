<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueLeadInfo extends Model
{
    use HasFactory;

    protected $table = 'venue_lead_info';

    protected $fillable = [
        'venue_id',
        'gpt_plan_suggested',
        'venue_signed_plan',
        'venue_signed_plan_recurring_cycle',
        'date_of_suggestion',
        'date_of_signed_venue_plan',
        'pricing_plan_id',
        'industry',
        'assistant_reply',
        'potential_venue_lead_id'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(Restaurant::class);
    }

    public function pricingPlan(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(PricingPlan::class);
    }

    public function potentialVenueLead(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(PotentialVenueLead::class);
    }
}
