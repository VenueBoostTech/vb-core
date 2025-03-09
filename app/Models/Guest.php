<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'phone', 'email', 'address', 'notes',
        'is_main', 'sn_platform_user', 'restaurant_id', 'is_for_retail',
        'is_from_restaurant_checkout',
        'created_manually',
        'bulk_imported',
        'is_for_accommodation',
        'is_for_food_and_beverage',
        'user_id',
        'allow_restaurant_msg',
        'allow_venueboost_msg',
        'allow_remind_msg',
        'external_ids'
    ];

    public function reservations()
    {
        return $this->belongsToMany('App\Models\Reservation');
    }

    public static function isUniqueEmail($email, $exceptId = null) {
        $query = static::where('email', $email);

        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->count() === 0;
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function waitlists()
    {
        return $this->hasMany(Waitlist::class);
    }

    public function loyaltyPrograms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LoyaltyProgram::class);
    }

    public function loyaltyProgramGuests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LoyaltyProgramGuest::class);
    }
    // Relationship with Wallet
    public function wallet(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    // Function to calculate points earned based on the reservation amount and percentage
    public function calculatePointsEarned($reservationAmount, $percentage): float
    {
        return round(($reservationAmount * $percentage) / 100);
    }

    // Function to convert points to a dollar discount based on the conversion rate
    public function convertPointsToDiscount($points, $conversionRate): float
    {
        return round(-($points / $conversionRate), 2);
    }

    // Relationship with EarnPointsHistory
    public function earnPointsHistory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EarnPointsHistory::class);
    }

    // Relationship with UsePointsHistory
    public function usePointsHistory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UsePointsHistory::class);
    }

    // Relationship with EndUserCard
    public function endUserCard(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EndUserCard::class);
    }

    public function beachBarBookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BeachBarBooking::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Method to get the loyalty tier of the guest through the wallet
    public function getLoyaltyTierAttribute()
    {
        return $this->wallet ? $this->wallet->loyaltyTier : null;
    }

    public function bookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function guestMarketingSettings(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(GuestMarketingSettings::class);
    }


}
