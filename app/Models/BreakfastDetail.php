<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BreakfastDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_unit_id',
        'venue_id',
        'offers_breakfast'
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
