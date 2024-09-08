<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Waitlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'guest_name', 'party_size', 'estimated_wait_time', 'guest_phone',
        'guest_email', 'reservation_id', 'notified', 'added_at', 'guest_notified_at',
        'is_vip', 'is_regular', 'arrival_time', 'seat_time', 'left_time', 'guest_id',
        'restaurant_id'
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
