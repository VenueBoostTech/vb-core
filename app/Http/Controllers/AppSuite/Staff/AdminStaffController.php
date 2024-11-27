<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppProject;
use App\Models\AppProjectTimesheet;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\StaffActivity;
use App\Models\TimesheetBreak;
use App\Services\VenueService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminStaffController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function activity(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $query = StaffActivity::with([
            'employee:id,name,department_id,restaurant_id',
            'employee.teams:id,name',
            'trackable',
            'venue:id,name'
        ])
            ->where('venue_id', $venue->id)
            ->latest();

        // Apply filters
        if ($request->department) {
            $query->whereHas('employee', function($q) use ($request) {
                $q->where('department_id', $request->department);  // Adjusted to 'department_id'
            });
        }

        if ($request->team) {
            $query->whereHas('employee', function($q) use ($request) {
                $q->whereHas('teams', function($q2) use ($request) {
                    $q2->where('teams.id', $request->team);  // Filter by team ID instead of name
                });
            });
        }

        if ($request->search) {
            $query->whereHas('employee', function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            });
        }

        // Get paginated results
        $activities = $query->paginate($request->input('per_page', 50));

        // Format activities for frontend
        $formattedActivities = $activities->map(function($activity) {
            $metadata = $activity->metadata;

            // Format task name based on activity type
            $task = match($activity->type) {
                StaffActivity::TYPE_QUALITY_CREATE => "Quality Inspection: {$metadata['rating']} rating",
                StaffActivity::TYPE_WORK_ORDER_CREATE => "Work Order: {$metadata['priority']} priority",
                StaffActivity::TYPE_ISSUE_CREATE => "Issue: {$metadata['priority']} priority - {$metadata['issue_type']}",
                StaffActivity::TYPE_SUPPLIES_CREATE => "Supply Request for {$metadata['required_date']}",
                StaffActivity::TYPE_COMMENT_CREATE => $metadata['has_image'] ? "Comment with image" : "Comment",
                StaffActivity::TYPE_MEDIA_UPLOAD => "Uploaded {$metadata['media_type']}: {$metadata['media_name']}",
                default => $metadata['task_name'] ?? null
            };

            return [
                'id' => $activity->id,
                'type' => $activity->type,
                'user' => $activity->employee->name,
                'department' => $activity->employee->department,
                'team' => $activity->employee->teams->first()?->name ?? 'Unassigned',
                'timestamp' => $activity->created_at->format('Y-m-d H:i:s'),
                'task' => $task,
                'metadata' => $metadata,
                'trackable_type' => $activity->trackable_type,
                'trackable_id' => $activity->trackable_id
            ];
        });

        // Get summary data with proper grouping of activity types
        $activityBreakdown = StaffActivity::where('venue_id', $venue->id)
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->get()
            ->map(function($item) {
                // Group similar activities for cleaner chart display
                $type = match($item->type) {
                    StaffActivity::TYPE_MEDIA_VIEW,
                    StaffActivity::TYPE_SUPPLIES_VIEW,
                    StaffActivity::TYPE_QUALITY_VIEW,
                    StaffActivity::TYPE_WORK_ORDER_VIEW,
                    StaffActivity::TYPE_ISSUE_VIEW,
                    StaffActivity::TYPE_COMMENT_VIEW,
                    StaffActivity::TYPE_TIMESHEET_VIEW,
                    StaffActivity::TYPE_VIEW => 'Views',

                    StaffActivity::TYPE_MEDIA_UPLOAD,
                    StaffActivity::TYPE_SUPPLIES_CREATE,
                    StaffActivity::TYPE_QUALITY_CREATE,
                    StaffActivity::TYPE_WORK_ORDER_CREATE,
                    StaffActivity::TYPE_ISSUE_CREATE,
                    StaffActivity::TYPE_COMMENT_CREATE,
                    StaffActivity::TYPE_CREATE => 'Creates',

                    StaffActivity::TYPE_MEDIA_DELETE,
                    StaffActivity::TYPE_COMMENT_DELETE,
                    StaffActivity::TYPE_DELETE => 'Deletes',

                    StaffActivity::TYPE_TIMESHEET_CLOCK_IN => 'Clock Ins',
                    StaffActivity::TYPE_TIMESHEET_CLOCK_OUT => 'Clock Outs',
                    StaffActivity::TYPE_TIMESHEET_BREAK_START => 'Break Starts',
                    StaffActivity::TYPE_TIMESHEET_BREAK_END => 'Break Ends',

                    default => $item->type
                };

                return [
                    'type' => $type,
                    'count' => $item->count
                ];
            })
            ->groupBy('type')
            ->map(function($group) {
                return [
                    'type' => $group->first()['type'],
                    'count' => $group->sum('count')
                ];
            })
            ->values();

        // Enhanced team performance metrics
        $teamPerformance = StaffActivity::where('staff_activities.venue_id', $venue->id) // Specify the table for venue_id
        ->where('staff_activities.created_at', '>=', Carbon::now()->subDay())
            ->whereIn('staff_activities.type', [
                StaffActivity::TYPE_QUALITY_CREATE,
                StaffActivity::TYPE_WORK_ORDER_CREATE,
                StaffActivity::TYPE_ISSUE_CREATE,
                StaffActivity::TYPE_SUPPLIES_CREATE,
                StaffActivity::TYPE_COMMENT_CREATE
            ])
            ->whereHas('employee', function($q) {
                $q->whereHas('teams');
            })
            ->selectRaw('teams.name, count(*) as count')
            ->join('employee_team', 'staff_activities.employee_id', '=', 'employee_team.employee_id')
            ->join('teams', 'employee_team.team_id', '=', 'teams.id')
            ->groupBy('teams.name')
            ->get();


        return response()->json([
            'activities' => $formattedActivities,
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'total' => $activities->total(),
                'per_page' => $activities->perPage(),
                'total_pages' => $activities->lastPage(),
            ],
            'summary' => [
                'activity_breakdown' => $activityBreakdown,
                'team_performance' => $teamPerformance
            ],
            'filters' => [
                'departments' => Employee::where('restaurant_id', $venue->id)
                    ->whereNotNull('department_id')
                    ->join('departments', 'employees.department_id', '=', 'departments.id')
                    ->distinct()
                    ->pluck('departments.name', 'departments.id')
                    ->map(function($name, $id) {
                        return [
                            'id' => $id,
                            'name' => $name
                        ];
                    })
                    ->values(),
                'teams' => $venue->teams()
                    ->select('id', 'name')  // Get both ID and name for teams
                    ->get()
                    ->map(function($team) {
                        return [
                            'id' => $team->id,
                            'name' => $team->name
                        ];
                    })
            ]
        ]);
    }

    public function getPerformanceMetrics(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $timeFrame = $request->input('time_frame', 'monthly');
        $startDate = match($timeFrame) {
            'weekly' => now()->subWeek(),
            'monthly' => now()->subMonth(),
            'quarterly' => now()->subMonths(3),
            'yearly' => now()->subYear(),
            default => now()->subMonth()
        };

        // KPI Data - Activity trends over time
        $kpiData = StaffActivity::where('venue_id', $venue->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date,
            COUNT(*) as total_activities,
            COUNT(CASE WHEN type LIKE "%create%" THEN 1 END) as task_completions')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'activities' => $item->total_activities,
                    'completions' => $item->task_completions
                ];
            });

        // Team Performance Comparison
        $teamPerformance = DB::table('employee_team')
            ->join('staff_activities', 'employee_team.employee_id', '=', 'staff_activities.employee_id')
            ->join('teams', 'employee_team.team_id', '=', 'teams.id')
            ->where('staff_activities.created_at', '>=', $startDate)
            ->where('staff_activities.venue_id', $venue->id)
            ->groupBy('teams.id', 'teams.name')
            ->select(
                'teams.name',
                DB::raw('COUNT(*) as activity_count'),
                DB::raw('COUNT(CASE WHEN type LIKE "%create%" THEN 1 END) as task_completion_rate'),
                DB::raw('COUNT(DISTINCT staff_activities.employee_id) as active_members')
            )
            ->get()
            ->map(function($team) {
                return [
                    'name' => $team->name,
                    'efficiency' => round(($team->task_completion_rate / $team->activity_count) * 100),
                    'activity' => $team->activity_count,
                    'engagement' => $team->active_members
                ];
            });

        // Staff Performance Benchmarking
        $staffPerformance = StaffActivity::where('staff_activities.venue_id', $venue->id)  // Specify table
        ->where('staff_activities.created_at', '>=', $startDate)  // Specify table
        ->join('employees', 'staff_activities.employee_id', '=', 'employees.id')
            ->groupBy('employees.id', 'employees.name')
            ->select(
                'employees.name',
                DB::raw('COUNT(*) as total_activities'),
                DB::raw('COUNT(DISTINCT DATE(staff_activities.created_at)) as active_days'),
                DB::raw('COUNT(CASE WHEN type LIKE "%create%" THEN 1 END) as tasks_completed')
            )
            ->orderBy('total_activities', 'desc')
            ->limit(10)
            ->get()
            ->map(function($employee) use ($startDate) {
                $totalPossibleDays = now()->diffInDays($startDate);
                return [
                    'name' => $employee->name,
                    'tasks' => $employee->tasks_completed,
                    'activity_score' => round(($employee->total_activities / $totalPossibleDays) * 100),
                    'engagement_rate' => round(($employee->active_days / $totalPossibleDays) * 100)
                ];
            });

        // Performance Insights
        $insights = [
            'total_activities' => StaffActivity::where('venue_id', $venue->id)
                ->where('created_at', '>=', $startDate)
                ->count(),
            'active_employees' => StaffActivity::where('venue_id', $venue->id)
                ->where('created_at', '>=', $startDate)
                ->distinct('employee_id')
                ->count('employee_id'),
            'task_completion_rate' => StaffActivity::where('venue_id', $venue->id)
                ->where('created_at', '>=', $startDate)
                ->whereRaw('type LIKE "%create%"')
                ->count(),
            'most_active_time' => StaffActivity::where('venue_id', $venue->id)
                    ->where('created_at', '>=', $startDate)
                    ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                    ->groupBy('hour')
                    ->orderByDesc('count')
                    ->first()?->hour ?? null
        ];

        return response()->json([
            'time_frame' => $timeFrame,
            'kpi_data' => $kpiData,
            'team_performance' => $teamPerformance,
            'staff_performance' => $staffPerformance,
            'insights' => $insights
        ]);
    }

    /**
     * Get employee activities
     */
    public function getEmployeeActivities(Request $request, $employeeId): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $employee = Employee::where('restaurant_id', $venue->id)
                ->findOrFail($employeeId);

            $query = StaffActivity::with([
                'trackable',
                'venue:id,name'
            ])
                ->where('venue_id', $venue->id)
                ->where('employee_id', $employeeId)
                ->latest();

            // Add date range filter if provided
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [
                    $request->start_date,
                    $request->end_date
                ]);
            }

            // Get paginated results
            $activities = $query->paginate($request->input('per_page', 15));

            // Format activities
            $formattedActivities = $activities->map(function($activity) {
                $metadata = $activity->metadata;

                return [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'timestamp' => $activity->created_at->format('Y-m-d H:i:s'),
                    'description' => $activity->getActivityDescription(),
                    'icon' => $activity->getIconClass(),
                    'metadata' => [
                        'project_name' => $metadata['project_name'] ?? null,
                        'duration' => $metadata['duration'] ?? null,
                        'rating' => $metadata['rating'] ?? null,
                        'priority' => $metadata['priority'] ?? null,
                        'media_type' => $metadata['media_type'] ?? null,
                        'location' => $metadata['location'] ?? null,
                    ],
                    'priority_class' => $activity->getPriorityClass()
                ];
            });

            // Get activity summary
            $summary = [
                'total_activities' => $activities->total(),
                'activity_types' => StaffActivity::where('employee_id', $employeeId)
                    ->selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->get(),
                'most_active_time' => StaffActivity::where('employee_id', $employeeId)
                    ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                    ->groupBy('hour')
                    ->orderByDesc('count')
                    ->first()
            ];

            return response()->json([
                'activities' => $formattedActivities,
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $activities->currentPage(),
                    'total' => $activities->total(),
                    'per_page' => $activities->perPage(),
                    'total_pages' => $activities->lastPage(),
                ]
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Employee not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update employee status
     */
    public function updateEmployeeStatus(Request $request, $employeeId): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in([
                'active',
                'inactive',
                'on-break',
                'off-duty',
                'on-leave',      // For employees on vacation or other types of leave
                'suspended',     // For temporary suspension of employment
                'probation',     // For employees under probationary period
                'terminated'
            ])]
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $employee = Employee::where('restaurant_id', $venue->id)
                ->findOrFail($employeeId);

            $oldStatus = $employee->status;
            $newStatus = $request->status;

            // Update the status
            $employee->status = $newStatus;
            $employee->save();

            // Track the status change
            $activity = StaffActivity::create([
                'employee_id' => $employee->id,
                'venue_id' => $venue->id,
                'type' => 'status_change',
                'metadata' => [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'changed_at' => now()->format('Y-m-d H:i:s'),
                    'department' => $employee->department?->name,
                    'timestamp' => now()->format('Y-m-d H:i:s')
                ]
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Status updated successfully',
                'status' => $newStatus
            ]);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Employee not found'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get comprehensive time data for an employee
     */
    public function getEmployeeTimeData(Request $request, $employeeId): JsonResponse
    {
        // Admin check
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            // Verify employee belongs to venue
            $employee = Employee::where('restaurant_id', $venue->id)
                ->findOrFail($employeeId);

            // Get paginated time entries with project and task relations
            $timeEntries = $employee->timeEntries()
                ->with(['project', 'task'])
                ->latest()
                ->paginate($request->input('per_page', 15));

            // Get active timesheet session if any
            $activeTimesheet = AppProjectTimesheet::where('employee_id', $employeeId)
                ->where('status', 'active')
                ->with(['project:id,name', 'task:id,name'])
                ->first();

            // Get attendance data
            $attendanceStatus = $this->getAttendanceData($employee);

            // Get shifts data
            $shiftsData = $this->getShiftsData($employee);

            // Get current month's timesheets
            $currentMonthTimesheets = AppProjectTimesheet::where('employee_id', $employeeId)
                ->where('venue_id', $venue->id)
                ->whereMonth('created_at', now()->month)
                ->with(['project:id,name', 'task:id,name'])
                ->latest()
                ->get();

            // Calculate breaks data from active timesheet
            $breaksData = null;
            if ($activeTimesheet) {
                $breaks = TimesheetBreak::where('timesheet_id', $activeTimesheet->id)
                    ->orderBy('created_at', 'desc')
                    ->get();

                $totalBreakMinutes = $breaks->sum(function($break) {
                    if (!$break->break_end) return 0;
                    return Carbon::parse($break->break_start)
                        ->diffInMinutes($break->break_end);
                });

                $breaksData = [
                    'breaks' => $breaks,
                    'summary' => [
                        'total_breaks' => $breaks->count(),
                        'total_break_minutes' => $totalBreakMinutes,
                        'active_break' => $breaks->where('break_end', null)->first(),
                        'meal_breaks_taken' => $breaks->where('break_type', 'meal')->count(),
                        'rest_breaks_taken' => $breaks->where('break_type', 'rest')->count(),
                    ]
                ];
            }

            // Calculate time-related statistics
            $stats = [
                'total_hours_this_month' => $currentMonthTimesheets->sum(function($timesheet) {
                    return floatval($timesheet->total_hours);
                }),
                'average_hours_per_day' => $currentMonthTimesheets->average(function($timesheet) {
                        return floatval($timesheet->total_hours);
                    }) ?? 0,
                'total_breaks_taken' => $currentMonthTimesheets->flatMap(function ($timesheet) {
                    return $timesheet->breaks;
                })->count(),
                'punctuality_rate' => $this->calculatePunctualityRate($employee),
                'overtime_hours' => $this->calculateOvertimeHours($employee),
            ];

            // Get time off data
            $timeOffData = $this->getTimeOffData($employee);

            return response()->json([
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'status' => $employee->status,
                ],
                'current_session' => [
                    'has_active_session' => !!$activeTimesheet,
                    'active_session' => $activeTimesheet,
                    'current_break' => $breaksData?->get('active_break'),
                ],
                'attendance' => [
                    'current_status' => $attendanceStatus,
                    'shifts' => $shiftsData,
                ],
                'time_entries' => [
                    'data' => $timeEntries->items(),
                    'current_page' => $timeEntries->currentPage(),
                    'per_page' => $timeEntries->perPage(),
                    'total' => $timeEntries->total(),
                    'total_pages' => $timeEntries->lastPage(),
                ],
                'timesheets' => [
                    'current_month' => $currentMonthTimesheets,
                    'breaks_data' => $breaksData,
                ],
                'time_off' => $timeOffData,
                'stats' => $stats,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Employee not found'], 404);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function calculatePunctualityRate($employee): float
    {
        $totalShifts = Schedule::where('employee_id', $employee->id)
            ->whereMonth('date', now()->month)
            ->count();

        if ($totalShifts === 0) return 0;

        $onTimeShifts = Schedule::where('employee_id', $employee->id)
            ->whereMonth('date', now()->month)
            ->whereHas('attendanceRecords', function($query) {
                $query->where('scan_type', AttendanceRecord::SCAN_TYPE_CHECK_IN)
                    ->whereRaw('TIME(scanned_at) <= TIME(schedules.start_time)');
            })
            ->count();

        return round(($onTimeShifts / $totalShifts) * 100, 2);
    }

    private function calculateOvertimeHours($employee): float
    {
        return Schedule::where('employee_id', $employee->id)
            ->whereMonth('date', now()->month)
            ->whereHas('attendanceRecords', function($query) {
                $query->where('scan_type', AttendanceRecord::SCAN_TYPE_CHECK_OUT)
                    ->whereRaw('TIME(scanned_at) > TIME(schedules.end_time)');
            })
            ->get()
            ->sum(function($schedule) {
                $checkOut = $schedule->attendanceRecords()
                    ->where('scan_type', AttendanceRecord::SCAN_TYPE_CHECK_OUT)
                    ->latest('scanned_at')
                    ->first();

                if (!$checkOut) return 0;

                $endTime = Carbon::parse($schedule->end_time);
                $checkOutTime = Carbon::parse($checkOut->scanned_at);
                return max(0, $checkOutTime->diffInMinutes($endTime) / 60);
            });
    }

    private function getAttendanceData($employee): array
    {
        $currentDate = Carbon::now();
        $lastRecord = AttendanceRecord::where('employee_id', $employee->id)
            ->latest()
            ->first();

        $currentShift = Schedule::where('employee_id', $employee->id)
            ->where('date', $currentDate->toDateString())
            ->first();

        return [
            'is_checked_in' => $lastRecord && !$lastRecord->check_out,
            'last_record' => $lastRecord,
            'current_shift' => $currentShift,
            'can_check_in' => !$lastRecord || ($lastRecord && $lastRecord->check_out),
            'can_check_out' => $lastRecord && !$lastRecord->check_out
        ];
    }

    private function getShiftsData($employee): array
    {
        $currentDate = Carbon::now();

        // Get upcoming shift
        $upcomingShift = Schedule::with('venue')
            ->where('employee_id', $employee->id)
            ->where('date', '>=', $currentDate->toDateString())
            ->where('status', '!=', 'time_off')
            ->orderBy('date')
            ->orderBy('start_time')
            ->first();

        // Get current month's shifts
        return [
            'upcoming_shift' => $upcomingShift ? [
                'date' => Carbon::parse($upcomingShift->date)->format('d/m/Y'),
                'start_time' => Carbon::parse($upcomingShift->start_time)->format('H:i'),
                'end_time' => Carbon::parse($upcomingShift->end_time)->format('H:i'),
                'venue' => $upcomingShift->venue->name
            ] : null,
            'monthly_shifts' => Schedule::with(['venue', 'leaveType'])
                ->where('employee_id', $employee->id)
                ->whereMonth('date', $currentDate->month)
                ->whereYear('date', $currentDate->year)
                ->orderBy('date', 'desc')
                ->get()
                ->map(function ($schedule) {
                    return [
                        'id' => $schedule->id,
                        'date' => Carbon::parse($schedule->date)->format('d/m/Y'),
                        'start_time' => Carbon::parse($schedule->start_time)->format('H:i'),
                        'end_time' => Carbon::parse($schedule->end_time)->format('H:i'),
                        'status' => $schedule->status,
                        'venue' => $schedule->venue->name,
                        'leave_type' => $schedule->leaveType?->name,
                        'is_time_off' => $schedule->status === 'time_off'
                    ];
                })
        ];
    }

    private function getTimeOffData($employee): array
    {
        // Get pending and upcoming time off requests
        $timeOffRequests = Schedule::where('employee_id', $employee->id)
            ->where('status', 'time_off')
            ->with('leaveType')  // Include leave type details
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($request) {
                $startDate = Carbon::parse($request->date);
                $endDate = Carbon::parse($request->end_date);

                return [
                    'id' => $request->id,
                    'type' => $request->leaveType?->name ?? 'Time Off',
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'total_days' => $request->total_days,
                    'reason' => $request->reason,
                    'status' => $request->status,
                    'created_at' => $request->created_at->format('Y-m-d H:i:s'),
                    'is_upcoming' => $startDate->isFuture(),
                    'is_past' => $endDate->isPast(),
                    'is_current' => $startDate->isPast() && $endDate->isFuture(),
                    'leave_type_id' => $request->leave_type_id
                ];
            });

        // Calculate some statistics
        $stats = [
            'total_requests' => $timeOffRequests->count(),
            'upcoming_requests' => $timeOffRequests->where('is_upcoming', true)->count(),
            'past_requests' => $timeOffRequests->where('is_past', true)->count(),
            'current_requests' => $timeOffRequests->where('is_current', true)->count(),
            'days_taken_this_year' => $timeOffRequests
                ->where('is_past', true)
                ->sum('total_days'),
            'requests_by_type' => $timeOffRequests
                ->groupBy('leave_type_id')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total_days' => $group->sum('total_days')
                    ];
                })
        ];

        return [
            'requests' => [
                'upcoming' => $timeOffRequests->where('is_upcoming', true)->values(),
                'past' => $timeOffRequests->where('is_past', true)->values(),
                'current' => $timeOffRequests->where('is_current', true)->values(),
            ],
            'stats' => $stats
        ];
    }

    // In AdminStaffController or a new AdminComplianceController

    public function getCompanyComplianceStatus(): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        // Get all employees' timesheets for the current month
        $timesheets = AppProjectTimesheet::where('venue_id', $venue->id)
            ->whereMonth('created_at', now()->month)
            ->with(['employee.department', 'breaks'])
            ->get();

        $compliance = [
            'overview' => [
                'total_employees' => $timesheets->unique('employee_id')->count(),
                'active_employees' => $timesheets->where('status', 'active')->unique('employee_id')->count(),
                'total_hours' => $timesheets->sum('total_hours'),
                'total_overtime' => $timesheets->sum('overtime_hours')
            ],
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
                'excessive_hours' => $timesheets->where('total_hours', '>', 12)->count(),
                'pending_approval' => $timesheets->where('overtime_approved', false)->count()
            ]
        ];

        // Calculate break violations
        foreach ($timesheets as $timesheet) {
            $hours = floatval($timesheet->total_hours);
            if ($hours >= 5) {
                $compliance['break_compliance']['total_required_breaks']++;

                if (!$timesheet->breaks->where('break_type', 'meal')->count()) {
                    $compliance['break_compliance']['missing_meal_breaks']++;
                    $compliance['break_compliance']['violations'][] = [
                        'id' => $timesheet->id,
                        'employee_name' => $timesheet->employee->name,
                        'type' => 'Missed Required Break',
                        'date' => $timesheet->clock_in_time,
                        'status' => 'Pending Review',
                        'severity' => 'High',
                        'hours_worked' => $hours,
                        'department' => $timesheet->employee->department?->name
                    ];
                }
            }
        }

        $compliance['violations_by_department'] = DB::table('app_project_timesheets as t')
            ->join('employees as e', 't.employee_id', '=', 'e.id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.id')
            ->where('t.venue_id', $venue->id)
            ->whereMonth('t.created_at', now()->month)
            ->select(
                'd.id as department_id',
                'd.name as department_name',
                DB::raw('COUNT(DISTINCT e.id) as total_employees'),
                DB::raw('SUM(CASE WHEN t.overtime_hours > 0 AND t.overtime_approved = 0 THEN 1 ELSE 0 END) as overtime_violations'),
                // Fix the breaks check using the proper table
                DB::raw('SUM(CASE WHEN t.total_hours >= 5 AND NOT EXISTS (
            SELECT 1 FROM app_timesheet_breaks tb
            WHERE tb.timesheet_id = t.id
            AND tb.break_type = "meal"
        ) THEN 1 ELSE 0 END) as break_violations'),
                DB::raw('SUM(t.total_hours) as total_hours'),
                DB::raw('SUM(t.overtime_hours) as overtime_hours'),
                DB::raw('COUNT(CASE WHEN t.status = "active" THEN 1 END) as active_employees')
            )
            ->groupBy('d.id', 'd.name')
            ->get();

        $compliance['recent_violations'] = DB::table('app_project_timesheets as t')
            ->join('employees as e', 't.employee_id', '=', 'e.id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.id')
            ->where('t.venue_id', $venue->id)
            ->where(function($query) {
                $query->where('t.total_hours', '>', 12)
                    ->orWhere(function($q) {
                        $q->where('t.overtime_hours', '>', 0)
                            ->where('t.overtime_approved', false);
                    });
            })
            ->where('t.created_at', '>=', now()->subDays(7))
            ->select([
                't.id',
                'e.id as employee_id',
                'e.name as employee_name',
                'd.id as department_id',
                'd.name as department_name',
                't.clock_in_time as date',
                't.total_hours',
                't.overtime_hours',
                DB::raw("CASE
               WHEN t.total_hours > 12 THEN 'Excessive Hours'
               ELSE 'Unapproved Overtime'
           END as type"),
                DB::raw("CASE
               WHEN t.total_hours > 12 THEN 'High'
               ELSE 'Medium'
           END as severity"),
                't.clock_in_time',
                't.clock_out_time',
                't.work_description',
                't.status',
                't.overtime_approved'
            ])
            ->orderBy('t.created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($violation) {
                return [
                    'id' => $violation->id,
                    'employee_id' => $violation->employee_id,
                    'employee_name' => $violation->employee_name,
                    'department_id' => $violation->department_id,
                    'department_name' => $violation->department_name,
                    'date' => $violation->date,
                    'total_hours' => $violation->total_hours,
                    'overtime_hours' => $violation->overtime_hours,
                    'type' => $violation->type,
                    'severity' => $violation->severity,
                    'clock_in' => $violation->clock_in_time,
                    'clock_out' => $violation->clock_out_time,
                    'description' => $violation->work_description,
                    'status' => $violation->status,
                    'overtime_approved' => $violation->overtime_approved,
                ];
            });

        return response()->json($compliance);
    }

    public function generateCompanyComplianceReport(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'department_id' => 'nullable|exists:departments,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        // Start with base query
        $query = AppProjectTimesheet::where('venue_id', $venue->id)
            ->whereBetween('clock_in_time', [$request->start_date, $request->end_date])
            ->with([
                'employee.department',
                'breaks',
                'employee.workClassification',
                'employee.complianceLogs'
            ]);

        // Apply department filter if provided
        if ($request->department_id) {
            $query->whereHas('employee', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        $timesheets = $query->get();
        $departments = Department::where('venue_id', $venue->id)
            ->with(['employees' => function($q) use ($request) {
                $q->whereBetween('created_at', [$request->start_date, $request->end_date]);
            }])
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
                'total_breaks_taken' => $timesheets->pluck('breaks')->flatten()->count(),
                'departments_count' => $departments->count(),
                'active_projects' => AppProject::where('venue_id', $venue->id)
                    ->where('status', 'active')
                    ->count()
            ],
            'compliance_issues' => [
                'break_violations' => [],
                'overtime_violations' => [],
                'excessive_hours_violations' => []
            ],
            'by_employee' => [],
            'by_department' => []
        ];

        // Process employee-wise data
        foreach ($timesheets->groupBy('employee_id') as $employeeId => $employeeTimesheets) {
            $employee = $employeeTimesheets->first()->employee;

            $employeeStats = [
                'id' => $employeeId,
                'name' => $employee->name,
                'department' => [
                    'id' => $employee->department?->id,
                    'name' => $employee->department?->name ?? 'Unassigned'
                ],
                'total_hours' => $employeeTimesheets->sum('total_hours'),
                'overtime_hours' => $employeeTimesheets->sum('overtime_hours'),
                'breaks_taken' => $employeeTimesheets->pluck('breaks')->flatten()->count(),
                'violations_count' => 0,
                'compliance_logs' => $employee->complianceLogs()
                    ->whereBetween('created_at', [$request->start_date, $request->end_date])
                    ->count()
            ];

            // Process violations
            foreach ($employeeTimesheets as $timesheet) {
                // Break violations
                if ($timesheet->total_hours >= 5 && !$timesheet->breaks->where('break_type', 'meal')->count()) {
                    $report['compliance_issues']['break_violations'][] = [
                        'employee_id' => $employeeId,
                        'employee_name' => $employee->name,
                        'department_id' => $employee->department?->id,
                        'department_name' => $employee->department?->name,
                        'date' => $timesheet->clock_in_time,
                        'duration' => $timesheet->total_hours,
                        'type' => 'missing_meal_break',
                        'severity' => 'high'
                    ];
                    $employeeStats['violations_count']++;
                }

                // Overtime violations
                if ($timesheet->overtime_hours > 0 && !$timesheet->overtime_approved) {
                    $report['compliance_issues']['overtime_violations'][] = [
                        'employee_id' => $employeeId,
                        'employee_name' => $employee->name,
                        'department_id' => $employee->department?->id,
                        'department_name' => $employee->department?->name,
                        'date' => $timesheet->clock_in_time,
                        'overtime_hours' => $timesheet->overtime_hours,
                        'approval_status' => 'pending',
                        'severity' => 'medium'
                    ];
                    $employeeStats['violations_count']++;
                }

                // Excessive hours
                if ($timesheet->total_hours > 12) {
                    $report['compliance_issues']['excessive_hours_violations'][] = [
                        'employee_id' => $employeeId,
                        'employee_name' => $employee->name,
                        'department_id' => $employee->department?->id,
                        'department_name' => $employee->department?->name,
                        'date' => $timesheet->clock_in_time,
                        'hours' => $timesheet->total_hours,
                        'severity' => 'high'
                    ];
                    $employeeStats['violations_count']++;
                }
            }

            $report['by_employee'][] = $employeeStats;
        }

        // Process department-wise data
        foreach ($departments as $department) {
            $departmentEmployees = collect($report['by_employee'])
                ->where('department.id', $department->id);

            $report['by_department'][] = [
                'id' => $department->id,
                'name' => $department->name,
                'employee_count' => $department->employees->count(),
                'active_employees' => $departmentEmployees->count(),
                'total_hours' => $departmentEmployees->sum('total_hours'),
                'overtime_hours' => $departmentEmployees->sum('overtime_hours'),
                'total_violations' => $departmentEmployees->sum('violations_count'),
                'break_violations' => collect($report['compliance_issues']['break_violations'])
                    ->where('department_id', $department->id)
                    ->count(),
                'overtime_violations' => collect($report['compliance_issues']['overtime_violations'])
                    ->where('department_id', $department->id)
                    ->count(),
                'excessive_hours_violations' => collect($report['compliance_issues']['excessive_hours_violations'])
                    ->where('department_id', $department->id)
                    ->count()
            ];
        }

        return response()->json($report);
    }
}
