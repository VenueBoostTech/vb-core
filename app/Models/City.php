<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $table = 'cities';

    protected $fillable = [
        'name',
        'name_translations',
        'active',
        'states_id',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'active' => 'boolean',
    ];

    public function state(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(State::class, 'states_id');
    }

    public function addresses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Address::class, 'city_id');
    }

    public function postalPricings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PostalPricing::class);
    }
}
