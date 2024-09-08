<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = ['guest_id', 'balance', 'venue_id', 'loyalty_tier_id'];


    // Relationship with Guest
    public function guest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    // Relationship with Venue
    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    // Relationship with EndUserCard

    public function endUserCard(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EndUserCard::class);
    }

    // Relationship with LoyaltyTier
    public function LoyaltyTier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'loyalty_tier_id');
    }
}
