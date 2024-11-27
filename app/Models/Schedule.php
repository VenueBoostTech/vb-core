<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    // Schedule Types
    public const TYPE_SHIFT = 'shift';
    public const TYPE_TASK = 'task';
    public const TYPE_JOB = 'job';
    public const TYPE_LEAVE = 'leave';

    // Statuses
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'restaurant_id',
        'date',
        'end_date',
        'start_time',
        'end_time',
        'schedule_type',    // shift, task, job, leave
        'status',          // scheduled, in_progress, completed, overdue, cancelled
        'leave_type_id',
        'project_id',      // For project-related tasks/jobs
        'task_id',         // Link to specific task
        'priority',        // high, medium, low
        'description',     // Task/Job description
        'delivery_date',   // Expected completion/delivery date
        'completed_at',    // When the task/job was completed
        'notes',
        'total_days'
    ];

    protected $casts = [
        'date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'delivery_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Existing relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'restaurant_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    // New relationships
    public function project()
    {
        return $this->belongsTo(AppProject::class, 'project_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    // Helper methods
    public function isOverdue(): bool
    {
        if (!$this->delivery_date) return false;
        return !$this->completed_at && now()->isAfter($this->delivery_date);
    }

    public function markAsCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->save();
    }

    public function attendanceRecords(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'employee_id', 'employee_id')
            ->whereDate('scanned_at', $this->date);
    }
}
