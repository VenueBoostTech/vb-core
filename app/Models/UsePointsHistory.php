<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsePointsHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'guest_id',
        'reservation_id',
        'venue_id',
        'points_used'
    ];

    // Relationship with Guest
    public function guest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    // Relationship with Reservation
    public function reservation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    // Relationship with Restaurant
    public function restaurant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
