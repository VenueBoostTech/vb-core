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
}
