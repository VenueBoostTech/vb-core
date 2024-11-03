<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VtDeviceDetectionActivity extends Model
{
    use HasFactory;
    protected $fillable = ['device_id', 'venue_detection_activity_id', 'is_active', 'config'];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean'
    ];

    public function venueActivity(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VtVenueDetectionActivity::class, 'venue_detection_activity_id');
    }

    public function device(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VtDevice::class, 'device_id');
    }
}
