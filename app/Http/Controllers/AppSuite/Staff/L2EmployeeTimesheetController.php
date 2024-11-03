<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppProject;
use App\Models\AppProjectTimesheet;
use App\Models\Restaurant;
use App\Models\TimesheetBreak;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class L2EmployeeTimesheetController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function getProjectTimesheets(Request $request, $projectId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        // Check if employee has admin/manager role
        if (!$authEmployee->hasAnyRoleUpdated(['Operations Manager', 'Team Leader'])) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $perPage = $request->input('per_page', 15);
        $query = AppProjectTimesheet::where('app_project_id', $projectId)
            ->where('venue_id', $venue->id)
            ->with(['employee:id,name', 'task:id,name'])
            ->latest();

        // Add filters
        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->date_from) {
            $query->whereDate('clock_in_time', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('clock_in_time', '<=', $request->date_to);
        }

        $timesheets = $query->paginate($perPage);

        return response()->json([
            'timesheets' => $timesheets->items(),
            'current_page' => $timesheets->currentPage(),
            'per_page' => $timesheets->perPage(),
            'total' => $timesheets->total(),
            'total_pages' => $timesheets->lastPage(),
        ]);
    }

    public function getActiveEmployees(Request $request, $projectId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        if (!$authEmployee->hasAnyRoleUpdated(['Operations Manager', 'Team Leader'])) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $activeTimesheets = AppProjectTimesheet::where('app_project_id', $projectId)
            ->where('venue_id', $venue->id)
            ->where('status', 'active')
            ->with(['employee:id,name', 'task:id,name'])
            ->get();

        return response()->json([
            'active_employees' => $activeTimesheets
        ]);
    }

    public function updateTimesheet(Request $request, $timesheetId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        if (!$authEmployee->hasAnyRoleUpdated(['Operations Manager'])) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $timesheet = AppProjectTimesheet::find($timesheetId);
        if (!$timesheet) {
            return response()->json(['error' => 'Timesheet not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'clock_in_time' => 'sometimes|required|date',
            'clock_out_time' => 'sometimes|required|date|after:clock_in_time',
            'work_description' => 'sometimes|required|string',
            'total_hours' => 'sometimes|required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $timesheet->update($request->only([
            'clock_in_time',
            'clock_out_time',
            'work_description',
            'total_hours'
        ]));

        return response()->json([
            'message' => 'Timesheet updated successfully',
            'timesheet' => $timesheet
        ]);
    }

    public function generateReport(Request $request, $projectId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        if (!$authEmployee->hasAnyRoleUpdated(['Operations Manager', 'Team Leader'])) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $validator = Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after:date_from',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $timesheets = AppProjectTimesheet::where('app_project_id', $projectId)
            ->whereBetween('clock_in_time', [$request->date_from, $request->date_to])
            ->with(['employee:id,name', 'task:id,name'])
            ->get();

        // Employee grouping
        $byEmployee = $timesheets->groupBy('employee_id')->map(function ($employeeTimesheets) {
            return [
                'employee_name' => $employeeTimesheets->first()->employee->name,
                'total_hours' => round($employeeTimesheets->sum('total_hours'), 2),
                'sessions_count' => $employeeTimesheets->count(),
                'completed_sessions' => $employeeTimesheets->where('status', 'completed')->count(),
                'active_sessions' => $employeeTimesheets->where('status', 'active')->count()
            ];
        })->values();

        // Task grouping (handling null tasks)
        $byTask = $timesheets->groupBy('task_id')->map(function ($taskTimesheets, $taskId) {
            return [
                'task_id' => $taskId,
                'task_name' => $taskTimesheets->first()->task?->name ?? 'No Task Assigned',
                'total_hours' => round($taskTimesheets->sum('total_hours'), 2),
                'employees_count' => $taskTimesheets->unique('employee_id')->count(),
                'completed_sessions' => $taskTimesheets->where('status', 'completed')->count(),
                'active_sessions' => $taskTimesheets->where('status', 'active')->count()
            ];
        })->values();

        // Calculate active sessions
        $activeSessions = $timesheets->where('status', 'active')->count();
        $completedSessions = $timesheets->where('status', 'completed')->count();

        $report = [
            'summary' => [
                'total_hours' => round($timesheets->sum('total_hours'), 2),
                'total_employees' => $timesheets->unique('employee_id')->count(),
                'total_sessions' => $timesheets->count(),
                'active_sessions' => $activeSessions,
                'completed_sessions' => $completedSessions,
                'date_range' => [
                    'from' => $request->date_from,
                    'to' => $request->date_to
                ]
            ],
            'by_employee' => $byEmployee,
            'by_task' => $byTask
        ];

        return response()->json($report);
    }

    public function getAllBreaks(Request $request, $projectId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        if (!$authEmployee->hasAnyRoleUpdated(['Operations Manager', 'Team Leader'])) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $query = TimesheetBreak::whereHas('timesheet', function ($q) use ($projectId, $venue) {
            $q->where('app_project_id', $projectId)
                ->where('venue_id', $venue->id);
        })->with(['timesheet.employee:id,name']);

        // Add filters
        if ($request->employee_id) {
            $query->whereHas('timesheet', function ($q) use ($request) {
                $q->where('employee_id', $request->employee_id);
            });
        }

        if ($request->date) {
            $query->whereDate('break_start', $request->date);
        }

        if ($request->break_type) {
            $query->where('break_type', $request->break_type);
        }

        $breaks = $query->orderBy('break_start', 'desc')->get();

        return response()->json([
            'breaks' => $breaks,
            'summary' => [
                'total_breaks' => $breaks->count(),
                'active_breaks' => $breaks->whereNull('break_end')->count(),
                'meal_breaks' => $breaks->where('break_type', 'meal')->count(),
                'rest_breaks' => $breaks->where('break_type', 'rest')->count(),
                'other_breaks' => $breaks->where('break_type', 'other')->count(),
                'by_employee' => $breaks->groupBy('timesheet.employee.name')
                    ->map(function ($employeeBreaks) {
                        return [
                            'count' => $employeeBreaks->count(),
                            'total_minutes' => $employeeBreaks->sum(function ($break) {
                                return $break->break_end
                                    ? Carbon::parse($break->break_start)
                                        ->diffInMinutes($break->break_end)
                                    : 0;
                            })
                        ];
                    })
            ]
        ]);
    }

    public function updateBreak(Request $request, $projectId, $breakId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        if (!$authEmployee->hasAnyRoleUpdated(['Operations Manager'])) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $break = TimesheetBreak::findOrFail($breakId);

        $validator = Validator::make($request->all(), [
            'break_start' => 'sometimes|required|date',
            'break_end' => 'sometimes|required|date|after:break_start',
            'break_type' => 'sometimes|required|in:meal,rest,other',
            'is_paid' => 'sometimes|required|boolean',
            'notes' => 'sometimes|required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Add audit note
        $adminNote = "\n\nModified by admin ({$authEmployee->name}) on " . now()->format('Y-m-d H:i:s');
        if ($request->admin_notes) {
            $adminNote .= "\nReason: " . $request->admin_notes;
        }

        $break->notes = $break->notes . $adminNote;
        $break->update($request->only([
            'break_start',
            'break_end',
            'break_type',
            'is_paid'
        ]));

        return response()->json([
            'message' => 'Break updated successfully',
            'break' => $break
        ]);
    }

    public function getOvertimeSummary(Request $request, $projectId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        if (!$authEmployee->hasAnyRoleUpdated(['Operations Manager', 'Team Leader'])) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $project = AppProject::where('venue_id', $venue->id)->find($projectId);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        // Get timesheets for the project
        $timesheets = AppProjectTimesheet::where('app_project_id', $projectId)
            ->where('venue_id', $venue->id)
            ->with(['employee:id,name', 'breaks'])
            ->get();

        $summary = [
            'total_regular_hours' => $timesheets->sum('regular_hours'),
            'total_overtime_hours' => $timesheets->sum('overtime_hours'),
            'total_double_time_hours' => $timesheets->sum('double_time_hours'),
            'pending_approval' => $timesheets->where('overtime_approved', false)
                ->where('overtime_hours', '>', 0)
                ->count(),
            'by_employee' => $timesheets->groupBy('employee_id')
                ->map(function ($employeeTimesheets) {
                    $employee = $employeeTimesheets->first()->employee;
                    return [
                        'employee_id' => $employee->id,
                        'name' => $employee->name,
                        'regular_hours' => $employeeTimesheets->sum('regular_hours'),
                        'overtime_hours' => $employeeTimesheets->sum('overtime_hours'),
                        'double_time_hours' => $employeeTimesheets->sum('double_time_hours'),
                        'pending_overtime' => $employeeTimesheets
                            ->where('overtime_approved', false)
                            ->where('overtime_hours', '>', 0)
                            ->count()
                    ];
                })->values()
        ];

        return response()->json($summary);
    }

    public function approveOvertime(Request $request, $projectId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        if (!$authEmployee->hasAnyRoleUpdated(['Operations Manager'])) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'timesheet_ids' => 'required|array',
            'timesheet_ids.*' => 'required|exists:app_project_timesheets,id',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $timesheets = AppProjectTimesheet::whereIn('id', $request->timesheet_ids)
            ->where('venue_id', $venue->id)
            ->where('app_project_id', $projectId)
            ->get();

        foreach ($timesheets as $timesheet) {
            $timesheet->overtime_approved = true;
            $timesheet->overtime_approved_by = $authEmployee->id;
            $timesheet->overtime_approved_at = now();
            $timesheet->save();
        }

        return response()->json([
            'message' => 'Overtime approved successfully',
            'approved_count' => $timesheets->count()
        ]);
    }

    public function getComplianceStatus(Request $request, $projectId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        if (!$authEmployee->hasAnyRoleUpdated(['Operations Manager', 'Team Leader'])) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $timesheets = AppProjectTimesheet::where('app_project_id', $projectId)
            ->where('venue_id', $venue->id)
            ->with(['employee', 'breaks'])
            ->get();

        $compliance = [
            'total_employees' => $timesheets->unique('employee_id')->count(),
            'break_compliance' => [
                'total_required_breaks' => 0,
                'total_taken_breaks' => $timesheets->pluck('breaks')->flatten()->count(),
                'missing_meal_breaks' => 0,
                'violations' => []
            ],
            'overtime_compliance' => [
                'unapproved_overtime' => $timesheets->where('overtime_hours', '>', 0)
                    ->where('overtime_approved', false)
                    ->count(),
                'excessive_hours' => $timesheets->where('total_hours', '>', 12)->count()
            ]
        ];

        foreach ($timesheets as $timesheet) {
            // Check for missing required breaks
            $hours = $timesheet->total_hours;
            if ($hours >= 5) {
                $compliance['break_compliance']['total_required_breaks']++;
                if (!$timesheet->breaks->where('break_type', 'meal')->count()) {
                    $compliance['break_compliance']['missing_meal_breaks']++;
                    $compliance['break_compliance']['violations'][] = [
                        'employee_name' => $timesheet->employee->name,
                        'date' => $timesheet->clock_in_time->format('Y-m-d'),
                        'violation' => 'Missing meal break for shift over 5 hours'
                    ];
                }
            }
        }

        return response()->json($compliance);
    }

    public function generateComplianceReport(Request $request): JsonResponse
    {

        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        if (!$authEmployee->hasAnyRoleUpdated(['Operations Manager'])) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'project_id' => 'required|exists:app_projects,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $timesheets = AppProjectTimesheet::where('app_project_id', $request->project_id)
            ->where('venue_id', $venue->id)
            ->whereBetween('clock_in_time', [$request->start_date, $request->end_date])
            ->with(['employee', 'breaks'])
            ->get();

        $report = [
            'period' => [
                'start' => $request->start_date,
                'end' => $request->end_date
            ],
            'summary' => [
                'total_hours' => $timesheets->sum('total_hours'),
                'total_employees' => $timesheets->unique('employee_id')->count(),
                'total_overtime_hours' => $timesheets->sum('overtime_hours'),
                'total_breaks_taken' => $timesheets->pluck('breaks')->flatten()->count()
            ],
            'compliance_issues' => [
                'break_violations' => [],
                'overtime_violations' => [],
                'excessive_hours_violations' => []
            ],
            'by_employee' => []
        ];

        foreach ($timesheets as $timesheet) {
            // Check various compliance issues
            if ($timesheet->total_hours > 12) {
                $report['compliance_issues']['excessive_hours_violations'][] = [
                    'employee' => $timesheet->employee->name,
                    'date' => $timesheet->clock_in_time->format('Y-m-d'),
                    'hours' => $timesheet->total_hours
                ];
            }

            // Collect employee stats
            if (!isset($report['by_employee'][$timesheet->employee_id])) {
                $report['by_employee'][$timesheet->employee_id] = [
                    'name' => $timesheet->employee->name,
                    'total_hours' => 0,
                    'overtime_hours' => 0,
                    'breaks_taken' => 0,
                    'violations' => 0
                ];
            }

            $report['by_employee'][$timesheet->employee_id]['total_hours'] += $timesheet->total_hours;
            $report['by_employee'][$timesheet->employee_id]['overtime_hours'] += $timesheet->overtime_hours;
            $report['by_employee'][$timesheet->employee_id]['breaks_taken'] += $timesheet->breaks->count();
        }

        $report['by_employee'] = array_values($report['by_employee']);

        return response()->json($report);
    }
}
