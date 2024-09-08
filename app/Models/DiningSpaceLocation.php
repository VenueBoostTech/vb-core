<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiningSpaceLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'restaurant_id'
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function tables()
    {
        return $this->hasMany(Table::class);
    }
}
