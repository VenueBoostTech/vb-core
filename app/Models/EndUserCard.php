<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EndUserCard extends Model
{
    use HasFactory;

    protected $table = 'end_user_cards';

    protected $fillable = [
        'venue_id',
        'guest_id',
        'wallet_id',
        'earn_points_history_id',
        'card_type',
        'uuid',
        'status',
        'is_verified',
        'issued_at',
        'expiration_date',
        'last_scanned_at',
        'notes',
        'url',
        's3_path',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function guest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function wallet(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function earn_points_history(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EarnPointsHistory::class, 'id', 'earn_points_history_id');
    }

    public function earnPointsHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EarnPointsHistory::class);
    }


}
