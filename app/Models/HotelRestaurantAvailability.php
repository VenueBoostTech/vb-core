<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelRestaurantAvailability extends Model
{
    use HasFactory;

    protected $table = 'hotel_restaurant_availability';
    protected $fillable = [
        'restaurant_id',
        'day_of_week',
        'open_time',
        'close_time',
    ];

    public function restaurants(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HotelRestaurant::class);
    }
}
