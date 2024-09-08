<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantConfiguration extends Model
{
    use HasFactory;

    protected $table = 'restaurant_configurations';

    protected $fillable = [
        'allow_reservation_from', 'venue_id'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }


}
