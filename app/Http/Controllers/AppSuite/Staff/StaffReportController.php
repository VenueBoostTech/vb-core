<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffReportController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function timeTracking(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        $timeEntries = TimeEntry::whereHas('employee', function ($query) use ($venue) {
            $query->where('venue_id', $venue->id);
        })
            ->whereBetween('start_time', [$startDate, $endDate])
            ->with(['employee', 'project', 'task'])
            ->get();

        $report = $timeEntries->groupBy('employee.id')->map(function ($entries, $employeeId) {
            $employee = $entries->first()->employee;
            return [
                'employee_id' => $employeeId,
                'employee_name' => $employee->name,
                'total_hours' => $entries->sum('duration') / 3600, // Convert seconds to hours
                'projects' => $entries->groupBy('project.id')->map(function ($projectEntries, $projectId) {
                    $project = $projectEntries->first()->project;
                    return [
                        'project_id' => $projectId,
                        'project_name' => $project->name,
                        'project_hours' => $projectEntries->sum('duration') / 3600,
                        'tasks' => $projectEntries->groupBy('task.id')->map(function ($taskEntries, $taskId) {
                            $task = $taskEntries->first()->task;
                            return [
                                'task_id' => $taskId,
                                'task_name' => $task ? $task->name : 'No specific task',
                                'task_hours' => $taskEntries->sum('duration') / 3600,
                            ];
                        })->values(),
                    ];
                })->values(),
            ];
        })->values();

        return response()->json($report);
    }

    public function taskCompletion(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        $tasks = Task::whereHas('project', function ($query) use ($venue) {
            $query->where('venue_id', $venue->id);
        })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['project', 'assignedEmployees'])
            ->get();

        $report = $tasks->groupBy('project.id')->map(function ($projectTasks, $projectId) {
            $project = $projectTasks->first()->project;
            $totalTasks = $projectTasks->count();
            $completedTasks = $projectTasks->where('status', 'done')->count();

            return [
                'project_id' => $projectId,
                'project_name' => $project->name,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'completion_rate' => $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0,
                'tasks' => $projectTasks->map(function ($task) {
                    return [
                        'task_id' => $task->id,
                        'task_name' => $task->name,
                        'status' => $task->status,
                        'assigned_employees' => $task->assignedEmployees->pluck('name'),
                    ];
                }),
            ];
        })->values();

        return response()->json($report);
    }


    public function employeeReport(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $employee =  $this->venueService->employee();

        if($venue->id !== $employee->restaurant_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();


        // Time tracking report
        $timeEntries = TimeEntry::where('employee_id', $employee->id)
            ->whereBetween('start_time', [$startDate, $endDate])
            ->with(['project', 'task'])
            ->get();

        $timeReport = [
            'total_hours' => $timeEntries->sum('duration') / 3600,
            'projects' => $timeEntries->groupBy('project.id')->map(function ($projectEntries, $projectId) {
                $project = $projectEntries->first()->project;
                return [
                    'project_id' => $projectId,
                    'project_name' => $project->name,
                    'project_hours' => $projectEntries->sum('duration') / 3600,
                    'tasks' => $projectEntries->groupBy('task.id')->map(function ($taskEntries, $taskId) {
                        $task = $taskEntries->first()->task;
                        return [
                            'task_id' => $taskId,
                            'task_name' => $task ? $task->name : 'No specific task',
                            'task_hours' => $taskEntries->sum('duration') / 3600,
                        ];
                    })->values(),
                ];
            })->values(),
        ];

        $employeeId = $employee->id;

        // Task completion report
        $tasks = Task::whereHas('assignedEmployees', function ($query) use ($employeeId) {
            $query->where('employees.id', $employeeId);
        })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('project')
            ->get();

        $taskReport = [
            'total_tasks' => $tasks->count(),
            'completed_tasks' => $tasks->where('status', 'done')->count(),
            'completion_rate' => $tasks->count() > 0 ? ($tasks->where('status', 'done')->count() / $tasks->count()) * 100 : 0,
            'tasks_by_project' => $tasks->groupBy('project.id')->map(function ($projectTasks, $projectId) {
                $project = $projectTasks->first()->project;
                return [
                    'project_id' => $projectId,
                    'project_name' => $project->name,
                    'total_tasks' => $projectTasks->count(),
                    'completed_tasks' => $projectTasks->where('status', 'done')->count(),
                    'tasks' => $projectTasks->map(function ($task) {
                        return [
                            'task_id' => $task->id,
                            'task_name' => $task->name,
                            'status' => $task->status,
                        ];
                    }),
                ];
            })->values(),
        ];

        $report = [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'time_tracking' => $timeReport,
            'task_completion' => $taskReport,
        ];

        return response()->json($report);
    }

    public function employees(Request $request){
        $venue = $this->venueService->adminAuthCheck();
        if (auth()->user()->role != 'owner' || auth()->user()->role != 'manager') {
           return response()->json(['error' => 'Unauthorized'], 403);
        }
        $employees = Employee::where('restaurant_id', $venue->id)
            ->with('department')
            ->withCount([
                'assignedProjects',
                'assignedProjects as completed_projects_count' => function ($query) {
                    $query->where('status', 'completed');
                },
                'assignedTasks',
                'assignedTasks as completed_tasks_count' => function ($query) {
                    $query->where('status', 'completed');
                },
            ])
            ->get()
            ->each(function ($employee) {
                $employee->performance = [
                    'projects' => $employee->assigned_projects_count > 0
                        ? ($employee->completed_projects_count / $employee->assigned_projects_count) * 100 . "%"
                        : 0,
                    'tasks' => $employee->assigned_tasks_count > 0
                        ? ($employee->completed_tasks_count / $employee->assigned_tasks_count) * 100 . "%"
                        : 0,
                ];
            });

        return response()->json($employees);
    }
}
