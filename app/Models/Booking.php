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
        'discount_id',
        'discount_price',
        'status',
        'paid_with',
        'prepayment_amount',
        'confirmation_code',
        'external_ids',
    ];

    protected $casts = [
        'check_in_date' => 'datetime',
        'check_out_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'prepayment_amount' => 'decimal:2',
        'external_ids' => 'json',
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

    public function discount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    /**
     * Get all bookings for OmniStack integration
     *
     * @param int $venueId
     * @return array
     */
    public static function getBookingsForOmniStack($venueId)
    {
        $bookings = self::with(['rentalUnit', 'guest'])
            ->where('venue_id', $venueId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $bookings->map(function ($booking) {
            return [
                'id' => $booking->id,
                'rental_unit_id' => $booking->rental_unit_id,
                'guest_id' => $booking->guest_id,
                'guest_nr' => $booking->guest_nr,
                'check_in_date' => $booking->check_in_date->format('Y-m-d'),
                'check_out_date' => $booking->check_out_date->format('Y-m-d'),
                'status' => $booking->status,
                'total_amount' => (float) $booking->total_amount,
                'discount_price' => (float) $booking->discount_price,
                'subtotal' => (float) $booking->subtotal,
                'paid_with' => $booking->paid_with,
                'prepayment_amount' => (float) $booking->prepayment_amount,
                'stripe_payment_id' => $booking->stripe_payment_id,
                'confirmation_code' => $booking->confirmation_code,
                'created_at' => $booking->created_at->format('Y-m-d H:i:s'),
                'rental_unit' => [
                    'name' => $booking->rentalUnit->name ?? null,
                    'address' => $booking->rentalUnit->address ?? null,
                ],
                'guest' => [
                    'name' => $booking->guest->name ?? null,
                    'email' => $booking->guest->email ?? null,
                    'phone' => $booking->guest->phone ?? null,
                ]
            ];
        })->toArray();
    }

}
