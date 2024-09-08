<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueIndustry extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'short_name'
    ];

    public function restaurants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Restaurant::class);
    }
}
