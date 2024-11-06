<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLocation extends Model
{
    protected $fillable = [
        'employee_id',
        'latitude',
        'longitude',
        'recorded_at',
        'accuracy',
        'provider',
        'device_platform',
        'device_os_version',
        'is_within_geofence'
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'accuracy' => 'float',
        'is_within_geofence' => 'boolean',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    /**
     * Get the employee that owns the location record.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope a query to get locations within a specific date range.
     */
    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to get only the latest location for each employee.
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('recorded_at', 'desc');
    }

    /**
     * Scope a query to get locations by provider type.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope a query to get locations by device platform.
     */
    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('device_platform', $platform);
    }

    /**
     * Get formatted coordinates.
     */
    public function getCoordinatesAttribute(): array
    {
        return [
            'lat' => (float) $this->latitude,
            'lng' => (float) $this->longitude
        ];
    }

    /**
     * Get device info.
     */
    public function getDeviceInfoAttribute(): array
    {
        return [
            'platform' => $this->device_platform,
            'os_version' => $this->device_os_version
        ];
    }
}
