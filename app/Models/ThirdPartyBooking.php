<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThirdPartyBooking extends Model
{
    use HasFactory;
    public $fillable = [
        'venue_id',
        'rental_unit_id',
        'title',
        'description',
        'event_description',
        'type',
        'summary',
        'start_date',
        'end_date',
    ];

    protected $dates = ['start_date', 'end_date'];

    public function venue()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function rentalUnit()
    {
        return $this->belongsTo(RentalUnit::class);
    }
}
