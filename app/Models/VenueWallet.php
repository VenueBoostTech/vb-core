<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'balance',
        'venue_id',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function history(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WalletHistory::class);
    }
}
