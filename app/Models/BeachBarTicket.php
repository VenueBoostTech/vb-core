<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BeachBarTicket extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'unique_code',
        'venue_id',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function beachBarBooking(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BeachBarBooking::class, 'ticket_id');
    }
}
