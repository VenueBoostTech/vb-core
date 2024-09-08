<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    public $fillable = [
        'venue_id',
        'rental_unit_id',
        'guest_id',
        'guest_nr',
        'check_in_date',
        'check_out_date',
        'total_amount',
        'subtotal',
        'discount_price',
        'status',
        'paid_with',
        'prepayment_amount',
        'confirmation_code',
    ];

    public static function getBookingDatesForRentalUnit($rentalUnitId)
    {
        $bookings = Booking::where('rental_unit_id', $rentalUnitId)
            ->select('check_in_date as start_date', 'check_out_date as end_date')
            ->get();

        $thirdPartyBookings = ThirdPartyBooking::where('rental_unit_id', $rentalUnitId)
            ->select('start_date', 'end_date')
            ->get();

        return $bookings->concat($thirdPartyBookings)
            ->sortBy('start_date')
            ->values()
            ->all();
    }

    public function receipt(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function priceBreakdowns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PriceBreakdown::class);
    }

    public function guest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function earnPointsHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EarnPointsHistory::class);
    }

    // relationship with rental unit
    public function rentalUnit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalUnit::class);
    }

}
