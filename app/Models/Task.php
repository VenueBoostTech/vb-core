<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name',
        'description', 'project_id', 'status', 'venue_id', 'priority', 'labels',
        'due_date',
        'start_date'
    ];

    public const STATUS_BACKLOG = 'backlog';
    public const STATUS_TODO = 'todo';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DRAFT = 'draft';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_BACKLOG => 'Backlog',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_TODO => 'Todo',
            self::STATUS_DONE => 'Done',
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }
    public function project(): BelongsTo
    {
        return $this->belongsTo(AppProject::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }


    public function assignedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'task_assignments', 'task_id', 'employee_id')
            ->withPivot('assigned_at', 'unassigned_at')
            ->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(AppProjectTimesheet::class, 'task_id');
    }
}
