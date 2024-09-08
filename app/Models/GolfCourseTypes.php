<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GolfCourseTypes extends Model
{
    use HasFactory;
    protected $table = 'golf_course_types';
    protected $fillable = [
        'name', 'price_method', 'price', 'start_time', 'end_time', 'max_allowed', 'venue_id'
    ];
}
