<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    use HasFactory;

    protected $table = 'states';

    protected $fillable = [
        'name',
        'active',
        'country_id',
    ];

    // Add the relationships
    public function country(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function cities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(City::class, 'states_id');
    }

    public function addresses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Address::class, 'state_id');
    }
}
