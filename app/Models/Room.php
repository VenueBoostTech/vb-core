<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $table = 'rooms';

    protected $fillable = [
        'rental_unit_id', 'venue_id', 'type', 'shared_space', 'shared_space_with', 'has_private_bathroom'
    ];

    public function rental_unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalUnit::class);
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function beds(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Bed::class, 'room_beds')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function gallery(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Gallery::class);
    }

    protected $casts = [
        'shared_space_with' => 'array',
    ];

}
