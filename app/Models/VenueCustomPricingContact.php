<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueCustomPricingContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'how_can_help_you',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class); // Assuming you have a Venue model
    }
}
