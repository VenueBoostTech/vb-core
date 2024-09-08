<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'definition', 'active', 'short_name', 'industry_id'
    ];

    public function restaurants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Restaurant::class);
    }
}
