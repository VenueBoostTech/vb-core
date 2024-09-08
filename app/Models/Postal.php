<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Postal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'status',
        'title',
        'name',
        'logo',
        'description',
        'venue_id',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function pricing(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PostalPricing::class);
    }
}
