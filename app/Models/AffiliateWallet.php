<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateWallet extends Model
{
    use HasFactory;

    protected $table = 'affiliate_wallets';

    protected $fillable = [
        'balance',
        'affiliate_id',
    ];

    public function affiliate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function walletHistory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AffiliateWalletHistory::class, 'affiliate_wallet_id');
    }
}
