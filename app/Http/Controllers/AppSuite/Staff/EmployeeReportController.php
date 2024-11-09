<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppProject;
use App\Models\AppProjectTimesheet;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeReportController extends Controller
{
    public function getReportData(Request $request): JsonResponse
    {
        if ($request->input('is_demo', false)) {
            return response()->json([
                'weeklyAttendance' => [
                    [
                        'onTime' => 20,
                        'early' => 10,
                        'late' => 5
                    ],
                    [
                        'onTime' => 25,
                        'early' => 12,
                        'late' => 3
                    ],
                    [
                        'onTime' => 18,
                        'early' => 8,
                        'late' => 7
                    ]
                ],
                'taskStats' => [
                    'inProgress' => 35,
                    'overdue' => 10,
                    'upcoming' => 25,
                    'completed' => 30
                ],
                'topTasks' => [
                    [
                        'title' => 'Change language',
                        'project' => 'BoA App v1',
                        'time' => '6h spent • ' . now()->format('d/m/Y'),
                        'status' => 'Completed'
                    ],
                    [
                        'title' => 'Redo base code',
                        'project' => 'BoA App v1',
                        'time' => '8h spent • ' . now()->format('d/m/Y'),
                        'status' => 'Pending'
                    ],
                    [
                        'title' => 'Update UI design',
                        'project' => 'BoA App v1',
                        'time' => '4h spent • ' . now()->format('d/m/Y'),
                        'status' => 'In Progress'
                    ]
                ],
                'topProjects' => [
                    [
                        'title' => 'BoA App v1',
                        'project' => 'Software Developer',
                        'time' => '45h spent • ' . now()->format('d/m/Y'),
                        'status' => '60/100%'
                    ],
                    [
                        'title' => 'Client Portal',
                        'project' => 'Software Developer',
                        'time' => '32h spent • ' . now()->format('d/m/Y'),
                        'status' => '80/100%'
                    ],
                    [
                        'title' => 'Mobile App',
                        'project' => 'Software Developer',
                        'time' => '28h spent • ' . now()->format('d/m/Y'),
                        'status' => '40/100%'
                    ]
                ],
                'tasksProgress' => [
                    'totalTrackedTime' => 124.5,
                    'mostConsumingProject' => 'BoA App v1',
                    'leastConsumingProject' => 'Mobile App'
                ],
                'productivityTrends' => [
                    'tasksCompleted' => [
                        'value' => 200,
                        'trend' => '-8%'
                    ],
                    'hoursWorked' => [
                        'value' => 44,
                        'trend' => '+24%'
                    ],
                    'productivityScore' => [
                        'value' => '80/100',
                        'trend' => '+6%'
                    ]
                ],
                'monthlyProductivity' => [
                    'Jun' => 145,
                    'Jul' => 156,
                    'Aug' => 142,
                    'Sep' => 168,
                    'Oct' => 158,
                    'Nov' => 172
                ]
            ]);
        }

        // Original code for real data
        $employee = auth()->user()->employee;

        try {
            return response()->json([
                'weeklyAttendance' => $this->getWeeklyAttendance($employee),
                'taskStats' => $this->getTaskStatistics($employee),
                'topTasks' => $this->getTopTasks($employee),
                'topProjects' => $this->getTopProjects($employee),
                'tasksProgress' => $this->getTasksProgress($employee),
                'productivityTrends' => $this->getProductivityTrends($employee),
                'monthlyProductivity' => $this->getMonthlyProductivity($employee)
            ]);
        } catch (\Exception $e) {
            dd($e);
            return response()->json(['error' => 'Failed to generate report'], 500);
        }
    }
    private function getWeeklyAttendance($employee): array
    {
        $currentDate = Carbon::now();
        $weekStart = $currentDate->copy()->startOfWeek();
        $weeklyAttendance = [];

        for ($i = 0; $i < 3; $i++) {
            $startDate = $weekStart->copy()->subWeeks($i);
            $endDate = $startDate->copy()->endOfWeek();

            $timesheetData = AppProjectTimesheet::where('employee_id', $employee->id)
                ->whereBetween('clock_in_time', [$startDate, $endDate])
                ->select(
                    DB::raw('SUM(CASE WHEN TIME(clock_in_time) <= "09:00:00" THEN total_hours ELSE 0 END) as on_time_hours'),
                    DB::raw('SUM(CASE WHEN TIME(clock_in_time) < "09:00:00" THEN total_hours ELSE 0 END) as early_hours'),
                    DB::raw('SUM(CASE WHEN TIME(clock_in_time) > "09:00:00" THEN total_hours ELSE 0 END) as late_hours')
                )
                ->first();

            $weeklyAttendance[] = [
                'onTime' => round($timesheetData->on_time_hours ?? 0, 2),
                'early' => round($timesheetData->early_hours ?? 0, 2),
                'late' => round($timesheetData->late_hours ?? 0, 2)
            ];
        }

        return array_reverse($weeklyAttendance);
    }

    private function getTaskStatistics($employee): array
    {
        return [
            'inProgress' => Task::whereHas('assignedEmployees', function($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })->where('status', Task::STATUS_IN_PROGRESS)->count(),
            'overdue' => Task::whereHas('assignedEmployees', function($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })->where('due_date', '<', now())
                ->where('status', '!=', Task::STATUS_DONE)
                ->count(),
            'upcoming' => Task::whereHas('assignedEmployees', function($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })->where('status', Task::STATUS_TODO)->count(),
            'completed' => Task::whereHas('assignedEmployees', function($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })->where('status', Task::STATUS_DONE)->count()
        ];
    }

    private function getTopTasks($employee): array
    {
        return Task::whereHas('assignedEmployees', function($q) use ($employee) {
            $q->where('employee_id', $employee->id);
        })
            ->with(['project:id,name'])
            ->select('id', 'name', 'project_id', 'status', 'due_date')
            ->orderBy('due_date', 'desc')
            ->limit(3)
            ->get()
            ->map(function($task) {
                return [
                    'title' => $task->name,
                    'project' => $task->project->name,
                    'time' => $task->timesheets()
                            ->where('employee_id', auth()->user()->employee->id)
                            ->sum('total_hours') . 'h spent • ' . $task->due_date->format('d/m/Y'),
                    'status' => ucfirst($task->status)
                ];
            })
            ->toArray();
    }

    private function getTopProjects($employee): array
    {
        return AppProject::whereHas('assignedEmployees', function($q) use ($employee) {
            $q->where('employee_id', $employee->id);
        })
            ->withCount(['tasks as total_tasks', 'tasks as completed_tasks' => function($q) {
                $q->where('status', Task::STATUS_DONE);
            }])
            ->select('id', 'name')
            ->limit(3)
            ->get()
            ->map(function($project) use ($employee) {
                $progress = $project->total_tasks > 0
                    ? round(($project->completed_tasks / $project->total_tasks) * 100)
                    : 0;
                return [
                    'title' => $project->name,
                    'project' => 'Software Developer',
                    'time' => $project->timesheets()
                            ->where('employee_id', $employee->id)
                            ->sum('total_hours') . 'h spent • ' . now()->format('d/m/Y'),
                    'status' => $progress . '/100%'
                ];
            })
            ->toArray();
    }

    private function getTasksProgress($employee): array
    {
        $totalTrackedTime = AppProjectTimesheet::where('employee_id', $employee->id)
            ->sum('total_hours');

        $projectTimes = AppProjectTimesheet::where('employee_id', $employee->id)
            ->join('app_projects', 'app_project_timesheets.app_project_id', '=', 'app_projects.id')
            ->select('app_projects.name', DB::raw('SUM(total_hours) as total_hours'))
            ->groupBy('app_projects.id', 'app_projects.name')
            ->orderBy('total_hours', 'desc')
            ->get();

        return [
            'totalTrackedTime' => round($totalTrackedTime, 1),
            'mostConsumingProject' => $projectTimes->first()?->name ?? 'N/A',
            'leastConsumingProject' => $projectTimes->last()?->name ?? 'N/A'
        ];
    }

    private function getProductivityTrends($employee): array
    {
        $currentPeriod = Carbon::now();
        $previousPeriod = Carbon::now()->subMonth();

        // Tasks Completed Trend
        $currentTasksCompleted = Task::whereHas('assignedEmployees', function($q) use ($employee) {
            $q->where('employee_id', $employee->id);
        })
            ->where('status', Task::STATUS_DONE)
            ->whereMonth('updated_at', $currentPeriod->month)
            ->count();

        $previousTasksCompleted = Task::whereHas('assignedEmployees', function($q) use ($employee) {
            $q->where('employee_id', $employee->id);
        })
            ->where('status', Task::STATUS_DONE)
            ->whereMonth('updated_at', $previousPeriod->month)
            ->count();

        // Hours Worked Trend
        $currentHoursWorked = AppProjectTimesheet::where('employee_id', $employee->id)
            ->whereMonth('clock_in_time', $currentPeriod->month)
            ->sum('total_hours');

        $previousHoursWorked = AppProjectTimesheet::where('employee_id', $employee->id)
            ->whereMonth('clock_in_time', $previousPeriod->month)
            ->sum('total_hours');

        // Productivity Score
        $currentProductivity = $this->calculateProductivityScore($employee, $currentPeriod);
        $previousProductivity = $this->calculateProductivityScore($employee, $previousPeriod);

        return [
            'tasksCompleted' => [
                'value' => $currentTasksCompleted,
                'trend' => $this->calculateTrendPercentage($currentTasksCompleted, $previousTasksCompleted)
            ],
            'hoursWorked' => [
                'value' => round($currentHoursWorked, 1),
                'trend' => $this->calculateTrendPercentage($currentHoursWorked, $previousHoursWorked)
            ],
            'productivityScore' => [
                'value' => $currentProductivity . '/100',
                'trend' => $this->calculateTrendPercentage($currentProductivity, $previousProductivity)
            ]
        ];
    }

    private function calculateProductivityScore($employee, $date): int
    {
        // Tasks completion rate (40% weight)
        $totalTasks = Task::whereHas('assignedEmployees', function($q) use ($employee) {
            $q->where('employee_id', $employee->id);
        })
            ->whereMonth('updated_at', $date->month)
            ->count();

        $completedTasks = Task::whereHas('assignedEmployees', function($q) use ($employee) {
            $q->where('employee_id', $employee->id);
        })
            ->where('status', Task::STATUS_DONE)
            ->whereMonth('updated_at', $date->month)
            ->count();

        $tasksScore = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 40 : 0;

        // Time tracking consistency (30% weight)
        $workingDays = $date->daysInMonth * 5/7;

        // Fixed date counting query
        $daysTracked = AppProjectTimesheet::where('employee_id', $employee->id)
            ->whereMonth('clock_in_time', $date->month)
            ->selectRaw('COUNT(DISTINCT DATE(clock_in_time)) as days_count')
            ->first()
            ->days_count;

        $trackingScore = ($daysTracked / $workingDays) * 30;

        // On-time completion (30% weight)
        $totalTasksWithDueDate = Task::whereHas('assignedEmployees', function($q) use ($employee) {
            $q->where('employee_id', $employee->id);
        })
            ->whereNotNull('due_date')
            ->whereMonth('updated_at', $date->month)
            ->count();

        $onTimeCompletions = Task::whereHas('assignedEmployees', function($q) use ($employee) {
            $q->where('employee_id', $employee->id);
        })
            ->whereNotNull('due_date')
            ->where('status', Task::STATUS_DONE)
            ->whereRaw('updated_at <= due_date')
            ->whereMonth('updated_at', $date->month)
            ->count();

        $onTimeScore = $totalTasksWithDueDate > 0 ? ($onTimeCompletions / $totalTasksWithDueDate) * 30 : 0;

        return round($tasksScore + $trackingScore + $onTimeScore);
    }

    private function calculateTrendPercentage($current, $previous): string
    {
        if ($previous == 0) {
            return $current > 0 ? '+100%' : '0%';
        }

        $change = (($current - $previous) / $previous) * 100;
        return ($change >= 0 ? '+' : '') . round($change, 1) . '%';
    }

    private function getMonthlyProductivity($employee): array
    {
        $monthlyProductivity = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthlyProductivity[$date->format('M')] = AppProjectTimesheet::where('employee_id', $employee->id)
                ->whereYear('clock_in_time', $date->year)
                ->whereMonth('clock_in_time', $date->month)
                ->sum('total_hours');
        }
        return $monthlyProductivity;
    }
}
