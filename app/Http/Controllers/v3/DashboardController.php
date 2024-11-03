<?php

namespace App\Http\Controllers\v3;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Task;
use App\Models\AppProject;
use App\Models\StaffActivity;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function index(Request $request): JsonResponse
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

        return response()->json([
            'overview' => $this->getOverview($venue->id, $startDate),
            'performance' => $this->getPerformance($venue->id, $startDate),
            'tasks' => $this->getTasks($venue->id, $startDate),
            'projects' => $this->getProjects($venue->id, $startDate),
        ]);
    }

    private function getOverview(int $venueId, Carbon $startDate): array
    {
        // Staff Count and Change
        $currentStaffCount = Employee::where('restaurant_id', $venueId)->count();
        $previousStaffCount = Employee::where('restaurant_id', $venueId)
            ->where('created_at', '<', $startDate)
            ->count();
        $staffChange = $previousStaffCount > 0
            ? round((($currentStaffCount - $previousStaffCount) / $previousStaffCount) * 100, 1)
            : 0;

        // Task Completion Stats
        $totalTasks = Task::where('venue_id', $venueId)
            ->where('created_at', '>=', $startDate)
            ->count();
        $completedTasks = Task::where('venue_id', $venueId)
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->count();
        $previousCompletedTasks = Task::where('venue_id', $venueId)
            ->whereBetween('created_at', [$startDate->copy()->subMonth(), $startDate])
            ->where('status', 'completed')
            ->count();
        $taskChange = $previousCompletedTasks > 0
            ? round((($completedTasks - $previousCompletedTasks) / $previousCompletedTasks) * 100, 1)
            : 0;

        // Productivity Calculation
        $productivity = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;
        $previousProductivity = $this->calculatePreviousProductivity($venueId, $startDate);
        $productivityChange = $previousProductivity > 0
            ? round($productivity - $previousProductivity, 1)
            : 0;

        // Active Projects
        $activeProjects = AppProject::where('venue_id', $venueId)
            ->where('status', 'active')
            ->count();
        $completedProjects = AppProject::where('venue_id', $venueId)
            ->where('status', 'completed')
            ->where('updated_at', '>=', $startDate)
            ->count();

        return [
            'totalStaff' => [
                'count' => $currentStaffCount,
                'change' => ($staffChange >= 0 ? '+' : '') . $staffChange . '% from previous period'
            ],
            'averageProductivity' => [
                'percentage' => $productivity,
                'change' => ($productivityChange >= 0 ? '+' : '') . $productivityChange . '% from previous period'
            ],
            'tasksCompleted' => [
                'count' => $completedTasks,
                'change' => ($taskChange >= 0 ? '+' : '') . $taskChange . '% from previous period'
            ],
            'activeProjects' => [
                'count' => $activeProjects,
                'completed' => $completedProjects . ' completed this period'
            ],
            'productivityTrend' => $this->getProductivityTrend($venueId, $startDate),
            'taskCompletionStatus' => $this->getTaskCompletionStatus($venueId, $startDate)
        ];
    }

    private function getPerformance(int $venueId, Carbon $startDate): array
    {
        return StaffActivity::where('staff_activities.venue_id', $venueId)  // Specify table name
        ->where('staff_activities.created_at', '>=', $startDate)  // Specify table name
        ->join('employees', 'staff_activities.employee_id', '=', 'employees.id')
            ->select(
                'employees.id',
                'employees.name',
                DB::raw('COUNT(*) as activity_count'),
                DB::raw('COUNT(DISTINCT DATE(staff_activities.created_at)) as active_days'),
                DB::raw('COUNT(CASE WHEN type LIKE "%create%" THEN 1 END) as completed_tasks')
            )
            ->groupBy('employees.id', 'employees.name')
            ->orderByDesc('activity_count')
            ->limit(5)
            ->get()
            ->map(function ($employee) use ($startDate) {
                $totalPossibleDays = now()->diffInDays($startDate);
                $performanceScore = round(
                    (($employee->activity_count / $totalPossibleDays) * 0.4 +
                        ($employee->active_days / $totalPossibleDays) * 0.3 +
                        ($employee->completed_tasks / $employee->activity_count) * 0.3) * 100
                );

                return [
                    'name' => $employee->name,
                    'role' => $this->getEmployeeRole($employee->id),
                    'performanceScore' => $performanceScore,
                    'stats' => [
                        'activities' => $employee->activity_count,
                        'activeDays' => $employee->active_days,
                        'completedTasks' => $employee->completed_tasks
                    ]
                ];
            })
            ->toArray();
    }

    private function getTasks(int $venueId, Carbon $startDate): array
    {
        $taskStatus = DB::table('tasks')
            ->where('tasks.venue_id', $venueId)
            ->where('tasks.created_at', '>=', $startDate)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress"),
                DB::raw("SUM(CASE WHEN status IN ('todo', 'backlog') THEN 1 ELSE 0 END) as not_started"),
                DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            )
            ->first();

        return [
            'taskDistribution' => Task::where('venue_id', $venueId)
                ->where('created_at', '>=', $startDate)
                ->select('priority')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed")
                ->whereNotNull('priority')
                ->groupBy('priority')
                ->get(),
            'taskStatus' => [
                'total' => $taskStatus->total ?? 0,
                'completed' => $taskStatus->completed ?? 0,
                'in_progress' => $taskStatus->in_progress ?? 0,
                'not_started' => $taskStatus->not_started ?? 0,
                'cancelled' => $taskStatus->cancelled ?? 0
            ]
        ];
    }
    private function getProjects(int $venueId, Carbon $startDate): array
    {
        return [
            'statusOverview' => AppProject::where('venue_id', $venueId)
                ->where('created_at', '>=', $startDate)
                ->select(
                    'id',
                    'name',
                    'status',
                    DB::raw('(SELECT COUNT(*) FROM tasks WHERE project_id = app_projects.id) as total_tasks'),
                    DB::raw('(SELECT COUNT(*) FROM tasks WHERE project_id = app_projects.id AND status = "completed") as completed_tasks')
                )
                ->get()
                ->map(function ($project) {
                    return [
                        'name' => $project->name,
                        'status' => $project->status,
                        'completion' => $project->total_tasks > 0
                            ? round(($project->completed_tasks / $project->total_tasks) * 100)
                            : 0
                    ];
                })
        ];
    }

    private function calculatePreviousProductivity(int $venueId, Carbon $startDate): float
    {
        $previousPeriod = [
            $startDate->copy()->subMonth(),
            $startDate
        ];

        $totalTasks = Task::where('venue_id', $venueId)
            ->whereBetween('created_at', $previousPeriod)
            ->count();

        $completedTasks = Task::where('venue_id', $venueId)
            ->whereBetween('created_at', $previousPeriod)
            ->where('status', 'completed')
            ->count();

        return $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;
    }

    private function getProductivityTrend(int $venueId, Carbon $startDate): array
    {
        $trend = Task::where('venue_id', $venueId)
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'dates' => $trend->pluck('date'),
            'actual' => $trend->map(function ($day) {
                return $day->total > 0 ? round(($day->completed / $day->total) * 100, 1) : 0;
            }),
            'expected' => array_fill(0, $trend->count(), 80) // 80% target
        ];
    }

    private function getTaskCompletionStatus(int $venueId, Carbon $startDate): array
    {
        $tasks = Task::where('venue_id', $venueId)
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress"),
                DB::raw("SUM(CASE WHEN status IN ('todo', 'backlog') THEN 1 ELSE 0 END) as not_started")
            )
            ->first();

        $total = $tasks->total ?: 1; // Avoid division by zero

        return [
            'completed' => round(($tasks->completed / $total) * 100),
            'inProgress' => round(($tasks->in_progress / $total) * 100),
            'notStarted' => round(($tasks->not_started / $total) * 100)
        ];
    }

    private function getEmployeeRole(int $employeeId): string
    {
        $employee = Employee::find($employeeId);
        return $employee->role?->name ?? 'Staff Member';
    }
}
