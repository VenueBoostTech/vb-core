<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OccupancyRateForecast extends Model
{
    use HasFactory;

    protected $table = 'occupancy_rate_forecasts';

    protected $fillable = [
        'model_evaluation_id',
        'date',
        'occupancy_rate',
    ];
}
