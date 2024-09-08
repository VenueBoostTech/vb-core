<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'receipt_id',
        'status',
        'total_amount',
        'rental_unit_id',
        'venue_id'
    ];

    public function booking(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function rentalUnit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalUnit::class);
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
