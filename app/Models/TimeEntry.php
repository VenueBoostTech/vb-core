<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeEntry extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'employee_id', 'project_id', 'task_id', 'start_time', 'end_time',
        'duration', 'description', 'is_manually_entered', 'venue_id'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_manually_entered' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(AppProject::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($timeEntry) {
            $project = $timeEntry->project;

            $allTeamMembers = collect()
                ->concat($project->assignedEmployees->pluck('id'))
                ->concat($project->teamLeaders->pluck('id'))
                ->concat($project->operationsManagers->pluck('id'))
                ->when($project->projectManager, fn($collection) => $collection->push($project->projectManager->id))
                ->unique()
                ->values();

            if (!$allTeamMembers->contains($timeEntry->employee_id)) {
                throw new \Exception('Employee is not assigned to this project');
            }
        });
    }

    public function scopeForAssignedProjects($query, Employee $employee)
    {
        return $query->whereIn('project_id', $employee->assignedProjects->pluck('id'));
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
