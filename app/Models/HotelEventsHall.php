<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelEventsHall extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'venue_id',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function availability(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HotelEventsHallAvailability::class);
    }
}
