<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bed extends Model
{
    use HasFactory;

    protected $table = 'beds';

    protected $fillable = [
      'name', 'size_from', 'size_to'
    ];

    public function rooms(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'room_beds')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
