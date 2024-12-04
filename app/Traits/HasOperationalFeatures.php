<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasOperationalFeatures
{
    public function milestones(): MorphMany
    {
        return $this->morphMany(Milestone::class, 'trackable');
    }

    public function workflows(): MorphMany
    {
        return $this->morphMany(Workflow::class, 'processable');
    }

    public function resourceAllocations(): MorphMany
    {
        return $this->morphMany(ResourceAllocation::class, 'assignable');
    }

    public function delays(): MorphMany
    {
        return $this->morphMany(OperationDelay::class, 'impactable');
    }

    // Helper method to get all resources allocated to this entity
    public function getAllocatedResources()
    {
        return Resource::whereHas('allocations', function ($query) {
            $query->where('assignable_type', $this->getMorphClass())
                ->where('assignable_id', $this->id)
                ->where('status', 'active');
        })->get();
    }
}
