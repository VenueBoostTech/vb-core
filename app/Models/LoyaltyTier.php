<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoyaltyTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'min_stays',
        'max_stays',
        'period_up',
        'period_down',
        'discount',
        'free_breakfast',
        'free_room_upgrade',
        'priority_support',
        'points_per_booking'
    ];

    // Relationship with Wallet
    public function wallets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Wallet::class);
    }
}
