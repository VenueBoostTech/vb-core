<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'status',
    ];

    public function rentalUnits(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(RentalUnit::class, 'rental_unit_language', 'language_id', 'rental_unit_id');
    }
}
