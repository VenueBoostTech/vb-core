<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class RentalUnit extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'about',
        'about_space',
        'about_guest_access',
        'country',
        'address',
        'latitude',
        'longitude',
        'venue_id',
        'unit_code',
        'commission_fee',
        'currency',
        'unit_status',
        'guest_interaction',
        'accommodation_type',
        'unit_floor',
        'year_built',
        'accommodation_venue_type',
        'vr_link',
        'ics_token',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function accommodation_detail(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AccommodationDetail::class);
    }

    public function breakfast_detail(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BreakfastDetail::class);
    }

    public function parking_detail(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ParkingDetail::class);
    }

    public function accommodation_rules(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AccommodationRule::class);
    }

    public function accommodation_host_profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AccommodationHostProfile::class);
    }

    public function languages(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'rental_unit_language', 'rental_unit_id', 'language_id');
    }

    public function gallery(): \Illuminate\Database\Eloquent\Relations\hasMany
    {
        return $this->hasMany(Gallery::class);
    }

    public function facilities(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'rental_unit_facility', 'rental_unit_id', 'facility_id');
    }

    public function accommodation_payment_capability(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AccommodationPaymentCapability::class);
    }

    public function card_preferences(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CardPreference::class);
    }

    public function pricing_and_calendar(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PricingAndCalendar::class);
    }

    public function rooms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function discounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Discount::class);
    }

    public function price_per_nights(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PricePerNight::class);
    }

    public function additional_fee_and_charges(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AdditionalFeeAndCharge::class);
    }

    public function rental_custom_rules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RentalCustomRule::class);
    }

    public function receipts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function priceBreakdowns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PriceBreakdown::class);
    }

    public function bookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function calendarConnections(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CalendarConnection::class);
    }

    public function generateIcsToken()
    {
        $this->ics_token = Str::random(32);
        $this->save();
    }

    public function getIcsUrl()
    {
        if (!$this->ics_token) {
            $this->generateIcsToken();
        }
        $obfuscatedId = $this->obfuscateId();
        return route('rental-unit.ics', [
            'obfuscatedId' => $obfuscatedId,
            'token' => $this->ics_token
        ]);
    }

    private function obfuscateId()
    {
        $timestamp = now()->timestamp;
        return base64_encode($this->id . '|' . $timestamp);
    }

    public static function deobfuscateId($obfuscatedId)
    {
        $decodedString = base64_decode($obfuscatedId);
        list($id, $timestamp) = explode('|', $decodedString);
        return $id;
    }

    public function connectionRefreshLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ConnectionRefreshLog::class);
    }

}
