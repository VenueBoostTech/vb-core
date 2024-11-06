<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePreference extends Model
{
    protected $fillable = [
        'employee_id',
        'email_notifications',
        'sms_notifications',
        'push_notifications',
        'location_tracking_enabled',
        'background_tracking_enabled',
        'tracking_enabled_at',
        'tracking_disabled_at'
    ];

    protected $casts = [
        'email_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
        'push_notifications' => 'boolean',
        'location_tracking_enabled' => 'boolean',
        'background_tracking_enabled' => 'boolean',
        'tracking_enabled_at' => 'datetime',
        'tracking_disabled_at' => 'datetime'
    ];

    /**
     * Get the employee that owns these preferences.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Enable location tracking
     */
    public function enableTracking(): void
    {
        $this->update([
            'location_tracking_enabled' => true,
            'tracking_enabled_at' => now(),
            'tracking_disabled_at' => null
        ]);
    }

    /**
     * Disable location tracking
     */
    public function disableTracking(): void
    {
        $this->update([
            'location_tracking_enabled' => false,
            'tracking_disabled_at' => now()
        ]);
    }

    /**
     * Update communication preferences
     */
    public function updateCommunicationPreferences(array $preferences): void
    {
        $this->update(array_intersect_key($preferences, [
            'email_notifications' => true,
            'sms_notifications' => true,
            'push_notifications' => true
        ]));
    }
}
