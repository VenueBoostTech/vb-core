<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccommodationDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_unit_id',
        'venue_id',
        'guest_limit',
        'bathroom_count',
        'allow_children',
        'offer_cots',
        'square_metres',
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
