<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateWalletHistory extends Model
{
    use HasFactory;

    protected $table = 'affiliate_wallet_history';

    protected $fillable = [
        'affiliate_wallet_id',
        'transaction_type',
        'amount',
        'registered_venue_id',
        'affiliate_plan_id',
    ];

    public function affiliateWallet(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AffiliateWallet::class, 'affiliate_wallet_id');
    }

    public function registeredVenue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'registered_venue_id');
    }

    public function affiliatePlan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AffiliatePlan::class, 'affiliate_plan_id');
    }

}
