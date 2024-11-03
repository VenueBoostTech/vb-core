<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\StaffActivity;
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
}
