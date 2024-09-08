<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GymClasses extends Model
{
    use HasFactory;

    protected $table = 'gym_classes';
    protected $fillable = [
        'name', 'price_method', 'price', 'start_time', 'end_time', 'max_allowed', 'venue_id'
    ];
}
