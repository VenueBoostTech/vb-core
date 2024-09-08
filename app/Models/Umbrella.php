<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Umbrella extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'venue_id',
        'uuid',
        'area_id',
        'nr_of_seats',
        'status',
        'price_rate',
        'price_rate_type',
        'description',
        'details_url',
        'condition',
        'check_in',
        'photo_url',
        'number',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function area(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VenueBeachArea::class);
    }

    public function beachBarBooking(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BeachBarBooking::class, 'umbrella_id');
    }
}
