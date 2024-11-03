<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomRole extends Model
{
    protected $fillable = [
        'name',
        'description',
        'created_by_venue_id'
    ];

    public function createdByVenue()
    {
        return $this->belongsTo(Restaurant::class, 'created_by_venue_id');
    }

    public function restaurants()
    {
        return $this->belongsToMany(Restaurant::class, 'restaurant_role', 'role_id', 'restaurant_id');
    }
}
