<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccommodationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_unit_id',
        'venue_id',
        'smoking_allowed',
        'pets_allowed',
        'parties_allowed',
        'check_in_from',
        'check_in_until',
        'checkout_from',
        'checkout_until',
        'key_pick_up',
        'check_in_method',
        'check_out_method',
        'wifi_detail',
        'guest_requirements',
        'guest_phone',
        'guest_identification',
        'guest_identification_type'
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
