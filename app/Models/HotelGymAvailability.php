<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelGymAvailability extends Model
{
    use HasFactory;

    protected $table = 'hotel_gym_availability';

    protected $fillable = [
        'gym_id',
        'day_of_week',
        'open_time',
        'close_time',
    ];

    public function gyms(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HotelGym::class);
    }
}
