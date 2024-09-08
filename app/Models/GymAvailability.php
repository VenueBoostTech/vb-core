<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GymAvailability extends Model
{
    use HasFactory;

    protected $table = 'gym_availability';
    protected $fillable = [
        'gym_id',
        'day_of_week',
        'open_time',
        'close_time',
    ];

    public function gym(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
