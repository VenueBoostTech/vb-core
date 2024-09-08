<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceBreakdown extends Model
{
    use HasFactory;
    protected $fillable = [
        'type',
        'price_difference',
        'total_adjustment',
        'previous_total',
        'new_total',
        'booking_id',
        'rental_unit_id',
        'venue_id',
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
