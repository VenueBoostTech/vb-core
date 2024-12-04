<?php

namespace App\Traits;

use App\Models\EquipmentAssignment;
use App\Models\EquipmentUsageLog;

trait HasEquipment
{
    public function equipmentAssignments()
    {
        return $this->morphMany(EquipmentAssignment::class, 'assignable');
    }

    public function equipmentUsageLogs()
    {
        return $this->morphMany(EquipmentUsageLog::class, 'usageable');
    }

    public function getActiveEquipmentAssignments()
    {
        return $this->equipmentAssignments()
            ->where('status', 'active')
            ->whereNull('returned_at')
            ->with('equipment')
            ->get();
    }
}
