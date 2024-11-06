<?php

namespace App\Services;

use App\Models\StaffActivity;
use App\Models\Employee;
use Carbon\Carbon;

class ActivityTrackingService
{
    /**
     * Main tracking method
     */
    public function track(
        Employee $employee,
        string $type,
                 $trackable = null,
        array $metadata = []
    ): StaffActivity {
        $commonMetadata = [
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'department' => $employee->department?->name,
            'team' => $employee->teams->first()?->name,
            'project_name' => $trackable?->project?->name ?? $trackable?->name ?? null,
            'project_id' => $trackable?->project?->id ?? $trackable?->id ?? null
        ];

        return StaffActivity::create([
            'employee_id' => $employee->id,
            'venue_id' => $employee->restaurant_id,
            'type' => $type,
            'trackable_type' => $trackable ? get_class($trackable) : null,
            'trackable_id' => $trackable ? $trackable->id : null,
            'metadata' => array_merge($commonMetadata, $metadata)
        ]);
    }

    /**
     * Media Activities
     */
    public function trackMediaView(Employee $employee, $project)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_MEDIA_VIEW,
            $project
        );
    }

    public function trackMediaUpload(Employee $employee, $media, $project)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_MEDIA_UPLOAD,
            $media,
            [
                'media_type' => $media->type,
                'media_name' => $media->name ?? null,
                'size' => $media->size ?? null
            ]
        );
    }

    public function trackMediaDelete(Employee $employee, $media, $project)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_MEDIA_DELETE,
            $media,
            [
                'media_type' => $media->type,
                'media_name' => $media->name ?? null
            ]
        );
    }

    /**
     * Supplies Request Activities
     */
    public function trackSuppliesView(Employee $employee, $project)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_SUPPLIES_VIEW,
            $project
        );
    }

    public function trackSuppliesCreate(Employee $employee, $request, $project)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_SUPPLIES_CREATE,
            $request,
            [
                'requested_date' => $request->requested_date,
                'required_date' => $request->required_date,
                'description' => $request->description
            ]
        );
    }

    /**
     * Quality Inspection Activities
     */
    public function trackQualityView(Employee $employee, $project)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_QUALITY_VIEW,
            $project
        );
    }

    public function trackQualityCreate(Employee $employee, $inspection, $project)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_QUALITY_CREATE,
            $inspection,
            [
                'rating' => $inspection->rating,
                'inspection_date' => $inspection->inspection_date,
                'remarks' => $inspection->remarks
            ]
        );
    }

    /**
     * Work Order Activities
     */
    public function trackWorkOrderView(Employee $employee, $project)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_WORK_ORDER_VIEW,
            $project
        );
    }

    public function trackWorkOrderCreate(Employee $employee, $order, $project)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_WORK_ORDER_CREATE,
            $order,
            [
                'priority' => $order->priority,
                'start_date' => $order->start_date,
                'end_date' => $order->end_date,
                'description' => $order->description
            ]
        );
    }

    /**
     * Project Issue Activities
     */
    public function trackIssueView(Employee $employee, $project)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_ISSUE_VIEW,
            $project
        );
    }

    public function trackIssueCreate(Employee $employee, $issue, $project)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_ISSUE_CREATE,
            $issue,
            [
                'priority' => $issue->priority,
                'issue_type' => $issue->type ?? 'general',
                'description' => $issue->description
            ]
        );
    }

    /**
     * Search Activities
     */
    public function trackSearch(Employee $employee, string $category, string $searchTerm)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_SEARCH,
            null,
            [
                'category' => $category,
                'search_term' => $searchTerm
            ]
        );
    }

    /**
     * Generic Activities
     */
    public function trackView(Employee $employee, $trackable)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_VIEW,
            $trackable
        );
    }

    public function trackCreate(Employee $employee, $trackable, array $additionalMetadata = [])
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_CREATE,
            $trackable,
            $additionalMetadata
        );
    }

    public function trackUpdate(Employee $employee, $trackable, array $changes = [])
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_UPDATE,
            $trackable,
            ['changes' => $changes]
        );
    }

    public function trackDelete(Employee $employee, $trackable)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_DELETE,
            $trackable
        );
    }

    /**
     * Comment Activities
     */
    public function trackCommentView(Employee $employee, $project)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_COMMENT_VIEW,
            $project
        );
    }

    public function trackCommentCreate(Employee $employee, $comment, $project)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_COMMENT_CREATE,
            $comment,
            [
                'has_image' => !empty($comment->image_path),
                'parent_id' => $comment->parent_id
            ]
        );
    }

    public function trackCommentDelete(Employee $employee, $comment, $project)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_COMMENT_DELETE,
            $comment
        );
    }

    /**
     * Timesheet Activities
     */
    public function trackTimesheetClockIn(Employee $employee, $timesheet)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_TIMESHEET_CLOCK_IN,
            $timesheet,
            [
                'task_id' => $timesheet->task_id,
                'clock_in_time' => $timesheet->clock_in_time,
                'location' => $timesheet->location_data
            ]
        );
    }

    public function trackTimesheetClockOut(Employee $employee, $timesheet)
    {
        $duration = number_format(
            Carbon::parse($timesheet->clock_in_time)
                ->diffInMinutes($timesheet->clock_out_time) / 60,
            2
        );

        return $this->track(
            $employee,
            StaffActivity::TYPE_TIMESHEET_CLOCK_OUT,
            $timesheet,
            [
                'duration' => $duration,
                'total_hours' => $timesheet->total_hours,
                'location' => $timesheet->location_data
            ]
        );
    }

    public function trackBreakStart(Employee $employee, $break)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_TIMESHEET_BREAK_START,
            $break,
            [
                'break_type' => $break->break_type,
                'is_paid' => $break->is_paid,
                'start_time' => $break->break_start
            ]
        );
    }

    public function trackBreakEnd(Employee $employee, $break)
    {
        $duration = Carbon::parse($break->break_start)
            ->diffInMinutes($break->break_end);

        return $this->track(
            $employee,
            StaffActivity::TYPE_TIMESHEET_BREAK_END,
            $break,
            [
                'duration' => $duration,
                'break_type' => $break->break_type,
                'is_paid' => $break->is_paid
            ]
        );
    }

    public function trackTimesheetView(Employee $employee, $timesheet)
    {
        return $this->track(
            $employee,
            StaffActivity::TYPE_TIMESHEET_VIEW,
            $timesheet,
            [
                //'status' => $timesheet->status,
                'total_hours' => $timesheet->regular_hours,
                'breaks_count' => $timesheet->breaks->count()
            ]
        );
    }
}
