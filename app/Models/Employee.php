<?php

namespace App\Models;

use App\Traits\HasLeaveManagement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use hasFactory;
    use SoftDeletes;
    use HasLeaveManagement;

    protected $table = 'employees';

    protected $fillable = [
        'name',
        'email',
        'role_id',
        'manager_id',
        'owner_id',
        'salary',
        'salary_frequency',
        'user_id',
        'hire_date',
        'restaurant_id',
        'frequency_number',
        'frequency_unit',
        'custom_role',
        'address_id',
        'department_id',
        'status',
        'personal_phone',
        'personal_email',
        'company_email',
        'company_phone',
        'profile_picture',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function owner()
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }

    public function ownerEmployees()
    {
        return $this->hasMany(Employee::class, 'owner_id', 'id');
    }

    public function hasPermission(Permission $permission)
    {
        return $this->hasAnyRole($permission->roles);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function performances()
    {
        return $this->hasMany(Performance::class);
    }

    public function hasAnyRole($roles)
    {
        if (is_array($roles) || is_object($roles)) {
            return !! $roles->intersect($this->roles)->count();
        }
        return $this->roles->contains('name', $roles);
    }


    public function hasAnyRoleUpdated($roles)
    {
        if (!$this->role) {
            return false;
        }

        if (is_string($roles)) {
            return $this->role->name === $roles;
        }

        if ($roles instanceof Role) {
            return $this->role->id === $roles->id;
        }

        if (is_array($roles)) {
            return in_array($this->role->name, $roles) || in_array($this->role->id, $roles);
        }

        return false;
    }

    public function salaryHistories()
    {
        return $this->hasMany(EmployeeSalaryHistory::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function isManager()
    {
        return $this->role_id == 1;
    }

    public function isOwner()
    {
        return $this->role_id == 2;
    }

    public function notifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function assignedProjects(): BelongsToMany
    {
        return $this->belongsToMany(AppProject::class, 'project_assignments', 'employee_id', 'project_id')  // Changed from app_project_id to project_id
        ->withPivot('assigned_at', 'unassigned_at')
            ->withTimestamps();
    }
    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignments')
            ->withPivot('assigned_at', 'unassigned_at')
            ->withTimestamps();
    }

    // belong to department
    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function managedProjects(): HasMany
    {
        return $this->hasMany(AppProject::class, 'project_manager_id');
    }

    public function leadProjects(): BelongsToMany
    {
        return $this->belongsToMany(AppProject::class, 'project_team_leader', 'employee_id', 'project_id')
            ->withTimestamps();
    }



    // Relation to AppGallery
    public function appGalleries(): HasMany
    {
        return $this->hasMany(AppGallery::class, 'uploader_id');
    }

    public function suppliesRequests(): HasMany
    {
        return $this->hasMany(SuppliesRequest::class);
    }

    public function reportedIssues(): HasMany
    {
        return $this->hasMany(ProjectIssue::class);
    }

    public function qualityInspections(): HasMany
    {
        return $this->hasMany(QualityInspection::class, 'team_leader_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'operation_manager_id');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(AppProjectTimesheet::class);
    }

    public function activeTimesheet(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AppProjectTimesheet::class)
            ->where('status', 'active');
    }

    public function workClassification(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmployeeWorkClassification::class);
    }

    public function complianceLogs(): HasMany
    {
        return $this->hasMany(LaborComplianceLog::class);
    }

    public function approvedTimesheets(): HasMany
    {
        return $this->hasMany(AppProjectTimesheet::class, 'overtime_approved_by');
    }


    /**
     * Get the employee's location history
     */
    public function locations(): HasMany
    {
        return $this->hasMany(EmployeeLocation::class);
    }

    /**
     * Get the employee's latest recorded location
     */
    public function latestLocation(): HasOne
    {
        return $this->hasOne(EmployeeLocation::class)
            ->latest('recorded_at');
    }

    /**
     * Get the employee's preferences including communication and tracking settings
     */
    public function preferences(): HasOne
    {
        return $this->hasOne(EmployeePreference::class);
    }

    /**
     * Helper method to check if location tracking is enabled
     */
    public function isLocationTrackingEnabled(): bool
    {
        return $this->preferences?->location_tracking_enabled ?? false;
    }

    /**
     * Helper method to check if background tracking is enabled
     */
    public function isBackgroundTrackingEnabled(): bool
    {
        return $this->preferences?->background_tracking_enabled ?? false;
    }

    /**
     * Helper method to check if employee accepts email notifications
     */
    public function acceptsEmailNotifications(): bool
    {
        return $this->preferences?->email_notifications ?? false;
    }

    /**
     * Helper method to check if employee accepts SMS notifications
     */
    public function acceptsSmsNotifications(): bool
    {
        return $this->preferences?->sms_notifications ?? false;
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(Schedule::class)->where('status', 'time_off');
    }

    public function getCurrentShiftAttribute()
    {
        return $this->schedules()
            ->where('date', now()->toDateString())
            ->where('status', '!=', 'time_off')
            ->first();
    }

    public function shifts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Shift::class, 'employee_id');
    }

    public function getLeaveBalanceAttribute()
    {
        $currentYear = now()->year;
        $totalLeaveDays = config('leave.annual_days', 30);

        $usedDays = $this->leaveRequests()
            ->whereYear('date', $currentYear)
            ->sum('total_days');

        return $totalLeaveDays - $usedDays;
    }

}
