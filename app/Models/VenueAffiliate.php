<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueAffiliate extends Model
{
    use HasFactory;

    protected $table = 'venue_affiliate';

    protected $fillable = [
        'venue_id',
        'affiliate_id',
        'affiliate_code',
        'contact_id',
        'potential_venue_lead_id',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function affiliate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function potentialVenueLead(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PotentialVenueLead::class);
    }
}
