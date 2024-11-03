<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppProjectTimesheet extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'app_project_id',
        'task_id',
        'employee_id',
        'venue_id',
        'clock_in_time',
        'clock_out_time',
        'work_description',
        'location_data',
        'total_hours',
        'status',
        'regular_hours',
        'overtime_hours',
        'double_time_hours',
        'overtime_approved',
        'overtime_approved_by',
        'overtime_approved_at'
    ];

    protected $casts = [
        'clock_in_time' => 'datetime',
        'clock_out_time' => 'datetime',
        'location_data' => 'array',
        'total_hours' => 'decimal:2',
        'regular_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'double_time_hours' => 'decimal:2',
        'overtime_approved' => 'boolean',
        'overtime_approved_at' => 'datetime'
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(AppProject::class, 'app_project_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    // Add new relationships
    public function breaks()
    {
        return $this->hasMany(TimesheetBreak::class, 'timesheet_id');
    }

    public function overtimeApprover(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'overtime_approved_by');
    }

    public function complianceLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LaborComplianceLog::class, 'timesheet_id');
    }
}
