<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StaffActivity extends Model
{
    protected $fillable = [
        'employee_id',
        'venue_id',
        'type',
        'trackable_type',
        'trackable_id',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Activity Types Constants for Media
    const TYPE_MEDIA_VIEW = 'media-view';
    const TYPE_MEDIA_UPLOAD = 'media-upload';
    const TYPE_MEDIA_DELETE = 'media-delete';

    // Activity Types Constants for Supplies
    const TYPE_SUPPLIES_VIEW = 'supplies-view';
    const TYPE_SUPPLIES_CREATE = 'supplies-create';

    // Activity Types Constants for Quality
    const TYPE_QUALITY_VIEW = 'quality-view';
    const TYPE_QUALITY_CREATE = 'quality-create';

    // Activity Types Constants for Work Orders
    const TYPE_WORK_ORDER_VIEW = 'work-order-view';
    const TYPE_WORK_ORDER_CREATE = 'work-order-create';

    // Activity Types Constants for Issues
    const TYPE_ISSUE_VIEW = 'issue-view';
    const TYPE_ISSUE_CREATE = 'issue-create';

    // Activity Types Constants for General Actions
    const TYPE_SEARCH = 'search';
    const TYPE_VIEW = 'view';
    const TYPE_CREATE = 'create';
    const TYPE_UPDATE = 'update';
    const TYPE_DELETE = 'delete';

    const TYPE_COMMENT_VIEW = 'comment-view';
    const TYPE_COMMENT_CREATE = 'comment-create';
    const TYPE_COMMENT_DELETE = 'comment-delete';

    const TYPE_TIMESHEET_CLOCK_IN = 'timesheet-clock-in';
    const TYPE_TIMESHEET_CLOCK_OUT = 'timesheet-clock-out';
    const TYPE_TIMESHEET_BREAK_START = 'timesheet-break-start';
    const TYPE_TIMESHEET_BREAK_END = 'timesheet-break-end';
    const TYPE_TIMESHEET_VIEW = 'timesheet-view';

    const TYPE_BREAKS_VIEW = 'breaks-view';

    const TYPES = [
        self::TYPE_MEDIA_VIEW,
        self::TYPE_MEDIA_UPLOAD,
        self::TYPE_MEDIA_DELETE,
        self::TYPE_SUPPLIES_VIEW,
        self::TYPE_SUPPLIES_CREATE,
        self::TYPE_QUALITY_VIEW,
        self::TYPE_QUALITY_CREATE,
        self::TYPE_WORK_ORDER_VIEW,
        self::TYPE_WORK_ORDER_CREATE,
        self::TYPE_ISSUE_VIEW,
        self::TYPE_ISSUE_CREATE,
        self::TYPE_SEARCH,
        self::TYPE_VIEW,
        self::TYPE_CREATE,
        self::TYPE_UPDATE,
        self::TYPE_DELETE,
        self::TYPE_COMMENT_VIEW,
        self::TYPE_COMMENT_CREATE,
        self::TYPE_COMMENT_DELETE,
        self::TYPE_TIMESHEET_CLOCK_IN,
        self::TYPE_TIMESHEET_CLOCK_OUT,
        self::TYPE_TIMESHEET_BREAK_START,
        self::TYPE_TIMESHEET_BREAK_END,
        self::TYPE_TIMESHEET_VIEW,
        self::TYPE_BREAKS_VIEW,
    ];

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    // Query Scopes
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByVenue($query, $venueId)
    {
        return $query->where('venue_id', $venueId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeByProject($query, $projectId)
    {
        return $query->where(function($q) use ($projectId) {
            $q->where('trackable_type', AppProject::class)
                ->where('trackable_id', $projectId)
                ->orWhereJsonContains('metadata->project_id', $projectId);
        });
    }

    // Helper Methods
    public function getActivityDescription(): string
    {
        $employeeName = $this->employee->name;
        $projectName = $this->metadata['project_name'] ?? 'Unknown Project';

        return match($this->type) {
            // Media Activities
            self::TYPE_MEDIA_UPLOAD => "{$employeeName} uploaded {$this->metadata['media_type']} to {$projectName}",
            self::TYPE_MEDIA_DELETE => "{$employeeName} deleted {$this->metadata['media_type']} from {$projectName}",
            self::TYPE_MEDIA_VIEW => "{$employeeName} viewed media in {$projectName}",

            // Supplies Activities
            self::TYPE_SUPPLIES_CREATE => "{$employeeName} created supplies request for {$projectName}",
            self::TYPE_SUPPLIES_VIEW => "{$employeeName} viewed supplies requests in {$projectName}",

            // Quality Activities
            self::TYPE_QUALITY_CREATE => "{$employeeName} created quality inspection for {$projectName} with rating {$this->metadata['rating']}",
            self::TYPE_QUALITY_VIEW => "{$employeeName} viewed quality inspections in {$projectName}",

            // Work Order Activities
            self::TYPE_WORK_ORDER_CREATE => "{$employeeName} created {$this->metadata['priority']} priority work order for {$projectName}",
            self::TYPE_WORK_ORDER_VIEW => "{$employeeName} viewed work orders in {$projectName}",

            // Issue Activities
            self::TYPE_ISSUE_CREATE => "{$employeeName} reported {$this->metadata['priority']} priority issue in {$projectName}",
            self::TYPE_ISSUE_VIEW => "{$employeeName} viewed issues in {$projectName}",

            // Search Activities
            self::TYPE_SEARCH => "{$employeeName} searched for '{$this->metadata['search_term']}' in {$this->metadata['category']}",

            // Comment Activities
            self::TYPE_COMMENT_VIEW => "{$employeeName} viewed comments in {$projectName}",
            self::TYPE_COMMENT_CREATE => "{$employeeName} commented on {$projectName}",
            self::TYPE_COMMENT_DELETE => "{$employeeName} deleted a comment from {$projectName}",

            // Timesheet Activities
            self::TYPE_TIMESHEET_CLOCK_IN => "{$employeeName} clocked in to {$projectName}",
            self::TYPE_TIMESHEET_CLOCK_OUT => "{$employeeName} clocked out from {$projectName} after {$this->metadata['duration']} hours",
            self::TYPE_TIMESHEET_BREAK_START => "{$employeeName} started a {$this->metadata['break_type']} break",
            self::TYPE_TIMESHEET_BREAK_END => "{$employeeName} ended their break after {$this->metadata['duration']} minutes",
            self::TYPE_TIMESHEET_VIEW => "{$employeeName} viewed timesheet for {$projectName}",
            self::TYPE_BREAKS_VIEW => "{$employeeName} viewed breaks summary with {$this->metadata['total_breaks']} total breaks",
            // Default Activities
            default => "{$employeeName} performed {$this->type} action in {$projectName}"
        };
    }

    public function getIconClass(): string
    {
        return match($this->type) {
            self::TYPE_MEDIA_UPLOAD,
            self::TYPE_MEDIA_DELETE,
            self::TYPE_MEDIA_VIEW => 'image',

            self::TYPE_SUPPLIES_CREATE,
            self::TYPE_SUPPLIES_VIEW => 'package',

            self::TYPE_QUALITY_CREATE,
            self::TYPE_QUALITY_VIEW => 'clipboard-check',

            self::TYPE_WORK_ORDER_CREATE,
            self::TYPE_WORK_ORDER_VIEW => 'file-text',

            self::TYPE_ISSUE_CREATE,
            self::TYPE_ISSUE_VIEW => 'alert-triangle',

            self::TYPE_SEARCH => 'search',

            default => 'activity'
        };
    }

    public function getPriorityClass(): string
    {
        return match($this->metadata['priority'] ?? 'normal') {
            'high' => 'bg-red-100 text-red-800',
            'medium' => 'bg-yellow-100 text-yellow-800',
            'low' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getFormattedDate(): string
    {
        return $this->created_at->format('Y-m-d H:i:s');
    }
}
