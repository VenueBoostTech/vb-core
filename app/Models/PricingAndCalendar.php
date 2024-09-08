<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingAndCalendar extends Model
{
    use HasFactory;

    protected $table = 'pricing_and_calendar';

    protected $fillable = [
        'rental_unit_id',
        'venue_id',
        'price_per_night',
        'cancellation_days',
        'booking_acceptance_date',
        'cash_accepted',
        'prepayment_amount',
    ];

    public function rental_unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalUnit::class);
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
