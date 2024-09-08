<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelEventsHallAvailability extends Model
{
    use HasFactory;

    protected $table = 'hotel_events_hall_availability';
    protected $fillable = [
        'events_hall_id',
        'day_of_week',
        'open_time',
        'close_time',
    ];

    public function events_hall(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HotelEventsHall::class);
    }
}
