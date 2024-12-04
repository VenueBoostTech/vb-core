<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConstructionSite extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'venue_id',
        'name',
        'description',
        'status',
        'address_id',
        'specifications',
        'weather_config',
        'site_manager',
        'access_requirements',
        'safety_requirements'
    ];

    protected $casts = [
        'specifications' => 'array',
        'weather_config' => 'array',
        'access_requirements' => 'array',
        'safety_requirements' => 'array'
    ];

    public function ownable(): MorphTo
    {
        return $this->morphTo();
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(SiteReport::class);
    }
}
