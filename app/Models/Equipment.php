<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'venue_id',
        'name',
        'type',
        'model',
        'serial_number',
        'purchase_date',
        'purchase_cost',
        'status',
        'last_maintenance_date',
        'next_maintenance_due',
        'maintenance_interval_days',
        'specifications',
        'assigned_to',
        'location'
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'last_maintenance_date' => 'date',
        'next_maintenance_due' => 'date',
        'maintenance_interval_days' => 'integer',
        'specifications' => 'array'
    ];

    const STATUS_AVAILABLE = 'available';
    const STATUS_IN_USE = 'in_use';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_RETIRED = 'retired';

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(EquipmentMaintenanceRecord::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EquipmentAssignment::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(EquipmentUsageLog::class);
    }

    // Helper method to check if maintenance is due
    public function isMaintenanceDue(): bool
    {
        if (!$this->next_maintenance_due) {
            return false;
        }
        return now()->startOfDay()->gte($this->next_maintenance_due);
    }

    // Helper method to check if equipment is currently assigned
    public function isCurrentlyAssigned(): bool
    {
        return $this->assignments()
            ->where('status', 'active')
            ->whereNull('returned_at')
            ->exists();
    }

    public function activeAssignment()
    {
        return $this->assignments()
            ->where('status', EquipmentAssignment::STATUS_ACTIVE)
            ->whereNull('returned_at')
            ->first();
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE
            && !$this->activeAssignment();
    }

    public function recordMaintenance(array $data)
    {
        $maintenance = $this->maintenanceRecords()->create($data);

        // Update equipment maintenance dates
        $this->update([
            'last_maintenance_date' => $maintenance->maintenance_date,
            'next_maintenance_due' => $maintenance->next_maintenance_due
        ]);

        return $maintenance;
    }

    public function startUsage(array $data)
    {
        if ($this->usageLogs()->active()->exists()) {
            throw new \Exception('Equipment is already in use');
        }

        return $this->usageLogs()->create(array_merge($data, [
            'started_at' => now(),
            'venue_id' => $this->venue_id
        ]));
    }
}
