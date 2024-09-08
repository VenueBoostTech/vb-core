<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantReferral extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'referral_code',
        'is_used',
        'register_id',
        'used_time',
        'potential_venue_lead_id'
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'used_time' => 'datetime',
    ];

    public function restaurant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'restaurant_id');
    }

    public function walletHistory(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WalletHistory::class);
    }

    public function featureUsageCreditHistory(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(FeatureUsageCreditHistory::class);
    }

    public function potentialVenueLead(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PotentialVenueLead::class);
    }
}
