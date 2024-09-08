<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BowlingAvailability extends Model
{
    use HasFactory;

    protected $table = 'bowling_availability';
    protected $fillable = [
        'bowling_id',
        'day_of_week',
        'open_time',
        'close_time',
    ];

    public function bowling(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
