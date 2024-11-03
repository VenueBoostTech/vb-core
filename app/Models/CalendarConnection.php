<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'rental_unit_id',
        'connection_name',
        'ics_link',
        'type',
        'status',
        'last_synced'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function rentalUnit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalUnit::class);
    }
}
