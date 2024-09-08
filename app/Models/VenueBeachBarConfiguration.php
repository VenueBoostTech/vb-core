<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueBeachBarConfiguration extends Model
{
    use HasFactory;

    protected $table = 'venue_beach_bar_configurations';

    protected $fillable = [
        'has_restaurant_menu',
        'has_beach_menu',
        'default_umbrellas_check_in',
        'currency',
        'venue_id',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
