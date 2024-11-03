<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppProject extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PLANNING = 'planning';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PLANNING => 'Planning',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_ON_HOLD => 'On Hold',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    protected $fillable = [
        'name',
        'description',
        'start_date',
        'end_date',
        'status',
        'department_id',
        'venue_id',
        'estimated_hours',
        'estimated_budget',
        'team_id',
        'project_manager_id',
        'project_type',
        'address_id',
        'project_category',
        'deal_status',
        'client_id'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'estimated_hours' => 'float',
        'estimated_budget' => 'decimal:2',
        'project_category' => 'string',
        'deal_status' => 'string'
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'project_assignments', 'project_id', 'employee_id')
            ->withPivot('assigned_at', 'unassigned_at')
            ->withTimestamps();
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'project_manager_id');
    }

    public function timeEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TimeEntry::class, 'project_id');
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    public function teamLeaders()
    {
        return $this->belongsToMany(Employee::class, 'project_team_leader', 'project_id', 'employee_id');
    }

    public function operationsManagers()
    {
        return $this->belongsToMany(Employee::class, 'project_operations_manager', 'project_id', 'employee_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(AppClient::class);
    }

    // Relation to AppGallery
    public function appGalleries(): HasMany
    {
        return $this->hasMany(AppGallery::class, 'app_project_id');
    }

    public function suppliesRequests(): HasMany
    {
        return $this->hasMany(SuppliesRequest::class, 'app_project_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ProjectIssue::class, 'app_project_id');
    }

    public function qualityInspections(): HasMany
    {
        return $this->hasMany(QualityInspection::class, 'app_project_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'app_project_id');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(AppProjectTimesheet::class, 'app_project_id');
    }

    public function activeTimesheets(): HasMany
    {
        return $this->hasMany(AppProjectTimesheet::class, 'app_project_id')
            ->where('status', 'active');
    }
}
