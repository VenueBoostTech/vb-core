<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VenueBeachArea extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'venue_id',
        'unique_code',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function umbrellas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Umbrella::class, 'area_id');
    }
}
