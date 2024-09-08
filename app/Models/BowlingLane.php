<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BowlingLane extends Model
{
    use HasFactory;

    protected $table = 'bowling_lane';
    protected $fillable = [
        'name', 'price_method', 'price', 'max_allowed', 'venue_id'
    ];
}
