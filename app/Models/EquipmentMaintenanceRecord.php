<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentMaintenanceRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'equipment_id',
        'venue_id',
        'maintenance_type',
        'maintenance_date',
        'work_performed',
        'cost',
        'performed_by',
        'next_maintenance_due',
        'parts_replaced'
    ];

    protected $casts = [
        'maintenance_date' => 'date',
        'next_maintenance_due' => 'date',
        'cost' => 'decimal:2',
        'parts_replaced' => 'array'
    ];

    const TYPE_ROUTINE = 'routine';
    const TYPE_REPAIR = 'repair';
    const TYPE_INSPECTION = 'inspection';

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function maintainer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'performed_by');
    }

    public function scopeRoutine($query)
    {
        return $query->where('maintenance_type', self::TYPE_ROUTINE);
    }

    public function scopeRepairs($query)
    {
        return $query->where('maintenance_type', self::TYPE_REPAIR);
    }

    public function scopeInspections($query)
    {
        return $query->where('maintenance_type', self::TYPE_INSPECTION);
    }
}
