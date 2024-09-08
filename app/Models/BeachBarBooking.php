<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BeachBarBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'umbrella_id',
        'total_amount',
        'ticket_id',
        'paid_with',
        'start_time',
        'end_time',
        'status',
        'main_guest_id',
        'main_guest_name'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function umbrella(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Umbrella::class, 'umbrella_id');
    }

    public function ticket(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BeachBarTicket::class, 'ticket_id');
    }

    public function mainGuest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Guest::class, 'main_guest_id');
    }
}
