<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VtVenueDetectionActivity extends Model
{
    use HasFactory;

    protected $fillable = ['venue_id', 'detection_activity_id', 'is_enabled', 'config'];

    protected $casts = [
        'config' => 'array',
        'is_enabled' => 'boolean'
    ];

    public function activity(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VtDetectionActivity::class, 'detection_activity_id');
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function deviceActivities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VtDeviceDetectionActivity::class, 'venue_detection_activity_id');
    }
}
