<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletHistory extends Model
{
    use HasFactory;

    protected $table = 'wallet_history';

    protected $fillable = [
        'wallet_id',
        'transaction_type',
        'amount',
        'subscription_id',
        'reason',
        'restaurant_referral_id',
    ];

    public function wallet(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VenueWallet::class);
    }

    public function restaurantReferral(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RestaurantReferral::class);
    }
}
