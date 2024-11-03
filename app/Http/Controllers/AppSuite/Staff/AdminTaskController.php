<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppProject;
use App\Models\Employee;
use App\Models\Task;
use App\Services\AppNotificationService;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminTaskController extends Controller
{
    protected VenueService $venueService;
    protected AppNotificationService $notificationService;

    public function __construct(VenueService $venueService, AppNotificationService $notificationService)
    {
        $this->venueService = $venueService;
        $this->notificationService = $notificationService;
    }

    public function index(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $perPage = $request->input('per_page', 15); // Default to 15 items per page
        $tasks = Task::whereHas('project', function ($query) use ($venue) {
            $query->where('venue_id', $venue->id);
        })
            ->with(['assignedEmployees', 'project']) // Eager load assigned employees and project
            ->paginate($perPage);

        // Format the tasks with the required fields
        $formattedTasks = $tasks->map(function ($task) {
            $assignee = $task->assignedEmployees->first(); // Get the first assignee
            return [
                'id' => $task->id,
                'name' => $task->name,
                'status' => $task->status,
                'priority' => $task->priority,
                'assignee' => $assignee ? [
                    'id' => $assignee->id,
                    'name' => $assignee->name,
                    'avatar' => $assignee->profile_picture
                        ? Storage::disk('s3')->temporaryUrl($assignee->profile_picture, now()->addMinutes(5))
                        : $this->getInitials($assignee->name)
                ] : null,
                'project' => [
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                ],
            ];
        });

        return response()->json([
            'tasks' => $formattedTasks,
            'current_page' => $tasks->currentPage(),
            'per_page' => $tasks->perPage(),
            'total' => $tasks->total(),
            'total_pages' => $tasks->lastPage(),
        ], 200);
    }

    private function getInitials($name): string
    {
        $words = explode(' ', $name);
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return substr($initials, 0, 2);
    }

    public function getTaskStatuses(): JsonResponse
    {
        return response()->json(Task::getStatuses());
    }

    public function show(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $task = Task::whereHas('project', function ($query) use ($venue) {
            $query->where('venue_id', $venue->id);
        })->with('assignedEmployees')->findOrFail($id);

        return response()->json($task);
    }

    public function store(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'priority' => 'required|in:low,medium,high',
            'status' => 'required|in:todo,in_progress,done,on_hold',
            'project_id' => 'nullable|exists:app_projects,id',
            'assigned_employee_ids' => 'nullable|array',
            'assigned_employee_ids.*' => 'exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if start_date is provided; if not, set it to now
        $validatedData = $validator->validated();
        if (empty($validatedData['start_date'])) {
            $validatedData['start_date'] = now(); // Set to current date/time
        }

        // Create the task with the venue ID
        $taskData = array_merge($validatedData, ['venue_id' => $venue->id]);
        $task = Task::create($taskData);

        // Handle employee assignments and notifications
        if (isset($taskData['assigned_employee_ids'])) {
            $assignedEmployeesData = array_map(function ($employeeId) {
                return [
                    'employee_id' => $employeeId,
                    'assigned_at' => now(), // Set the assigned_at timestamp
                ];
            }, $taskData['assigned_employee_ids']);

            $task->assignedEmployees()->attach($assignedEmployeesData);

            foreach ($taskData['assigned_employee_ids'] as $employeeId) {
                $employee = Employee::findOrFail($employeeId);
                // Send notifications to the assigned employees
                $this->notificationService->sendNotification(
                    $employee,
                    'task_notifications',
                    "You have been assigned to a new task: {$task->name}."
                );
            }
        }

        return response()->json($task, 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        // Find the task for the given venue
        $task = Task::where('venue_id', $venue->id)->find($id);
        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'priority' => 'required|in:low,medium,high',
            'status' => 'sometimes|required|in:todo,in_progress,done,on_hold',
            'project_id' => 'nullable|exists:app_projects,id',
            'assigned_employee_ids' => 'nullable|array',
            'assigned_employee_ids.*' => 'exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update the task with validated data
        $task->update($validator->validated());

        // Handle employee assignments and notifications
        $newEmployeeIds = $validator->validated()['assigned_employee_ids'] ?? [];
        $oldEmployeeIds = $task->assignedEmployees()->pluck('employee_id')->toArray(); // Use 'employee_id' instead of 'id'

        // Determine which employees to remove and add
        $employeesToRemove = array_diff($oldEmployeeIds, $newEmployeeIds);
        $employeesToAdd = array_diff($newEmployeeIds, $oldEmployeeIds);

        // Detach old employees
        if (!empty($employeesToRemove)) {
            $task->assignedEmployees()->detach($employeesToRemove);
            foreach ($employeesToRemove as $employeeId) {
                $employee = Employee::find($employeeId); // Use find() to avoid exception if not found
                if ($employee) {
                    $this->notificationService->sendNotification(
                        $employee,
                        'task_notifications',
                        "You have been unassigned from the task: {$task->name}."
                    );
                }
            }
        }

        // Attach new employees
        if (!empty($employeesToAdd)) {
            $assignedEmployeesData = array_map(function ($employeeId) {
                return [
                    'employee_id' => $employeeId,
                    'assigned_at' => now(), // Set assigned_at timestamp
                ];
            }, $employeesToAdd);

            $task->assignedEmployees()->attach($assignedEmployeesData);

            foreach ($employeesToAdd as $employeeId) {
                $employee = Employee::find($employeeId); // Use find() to avoid exception if not found
                if ($employee) {
                    $this->notificationService->sendNotification(
                        $employee,
                        'task_notifications',
                        "You have been assigned to the task: {$task->name}."
                    );
                }
            }
        }

        return response()->json($task, 200); // Return the updated task with a 200 status
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $task = Task::whereHas('project', function ($query) use ($venue) {
            $query->where('venue_id', $venue->id);
        })->findOrFail($id);

        // Optionally send notification to all assigned employees about task deletion
        foreach ($task->assignedEmployees as $employee) {
            $this->notificationService->sendNotification(
                $employee,
                'task_notifications',
                "The task: {$task->name} in project {$task->project->name} has been deleted."
            );
        }

        $task->delete();
        return response()->json(['message' => 'Project deleted successfully'], 200);
    }

    // New method to assign an employee to a task
    public function assignEmployee(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        // Ensure the task belongs to the venue
        $task = Task::whereHas('project', function ($query) use ($venue) {
            $query->where('venue_id', $venue->id);
        })->findOrFail($id);

        // Validate the request data
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        // Find the employee
        $employee = Employee::findOrFail($validated['employee_id']);

        // Attach the employee with the assigned_at timestamp
        $task->assignedEmployees()->attach($employee, ['assigned_at' => now()]); // Add assigned_at here

        // Send notification for assignment
        $this->notificationService->sendNotification(
            $employee,
            'task_notifications',
            "You have been assigned to the task: {$task->name} in project {$task->project->name}"
        );

        return response()->json($task);
    }


    // New method to unassign an employee from a task
    public function unassignEmployee(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $task = Task::whereHas('project', function ($query) use ($venue) {
            $query->where('venue_id', $venue->id);
        })->findOrFail($id);

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $task->assignedEmployees()->detach($employee);

        // Send notification for unassignment
        $this->notificationService->sendNotification(
            $employee,
            'task_notifications',
            "You have been unassigned from the task: {$task->name} in project {$task->project->name}"
        );

        return response()->json($task);
    }
}
