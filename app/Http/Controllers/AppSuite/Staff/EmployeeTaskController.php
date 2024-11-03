<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppProject;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Comment;
use App\Models\ChatConversation;
use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\Task;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EmployeeTaskController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
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


    public function index(Request $request): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $employee = Employee::where('user_id', auth()->user()->id)->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $assigneeId = $employee->id;
        $perPage = $request->input('per_page', 15); // Number of tasks per page

        // Use paginate instead of get to implement pagination
        $tasks = Task::whereHas('assignedEmployees', function ($query) use ($assigneeId) {
            $query->where('employee_id', $assigneeId);
        })
            ->with(['comments', 'assignedEmployees' => function ($query) {
                // Specify the table name for the id
                $query->select('employees.id', 'employees.name', 'employees.profile_picture');
            }])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['id', 'name', 'description', 'priority', 'status', 'created_at', 'start_date', 'due_date']);


        // In EmployeeTaskController.php
        $formattedTasks = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'name' => $task->name,
                'description' => $task->description,
                'status' => $task->status,
                'due_date' => $task->due_date ?? null,
                'start_date' => $task->start_date ?? $task->start_date,
                'comments' => $task->comments->count(),  // Add comments count
                'priority' => $task->priority,
                'assignee' => [
                    'id' => $task->assignedEmployees->first()?->id,
                    'name' => $task->assignedEmployees->first()?->name,
                    'avatar' => $task->assignedEmployees->first()?->profile_picture
                        ? Storage::disk('s3')->temporaryUrl($task->assignedEmployees->first()->profile_picture, '+5 minutes')
                        : $this->getInitials($task->assignedEmployees->first()->name)
                ],
                'project' => [
                    'id' => $task->project?->id,
                    'name' => $task->project?->name
                ]
            ];
        });
        return response()->json([
            'tasks' => $formattedTasks,
            'current_page' => $tasks->currentPage(),
            'per_page' => $tasks->perPage(),
            'total' => $tasks->total(),
            'total_pages' => $tasks->lastPage(),
        ]);
    }



    public function show($taskId): JsonResponse
    {
        $task = Task::with(['project:id,name', 'timesheets:id,task_id,total_hours,created_at'])
            ->find($taskId);

        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        $taskDetails = [
            'name' => $task->name,
            'due_date' => $task->due_date ? $task->due_date : null,
            'start_date' => $task->start_date ? $task->start_date : null,
            'priority' => $task->priority ? $task->priority : null,
            'status' => $task->status,
            'project_name' => $task->project ? $task->project->name : 'No Project',
            'timesheets' => $task->timesheets->map(function ($timesheet) {
                return [
                    'id' => $timesheet->id,
                    'hours' => $timesheet->hours,
                    'created_at' => $timesheet->created_at->format('Y-m-d H:i:s')
                ];
            })
        ];

        return response()->json($taskDetails);
    }

    public function updateStatus(Request $request, $taskId): JsonResponse
    {

        $validator = Validator::make($request->all(), [
        // Validate the incoming request
            'status' => 'required|string|in:todo, in_progress,done,on_hold',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        // Find the task
        $task = Task::find($taskId);

        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        // Update the task status
        $task->status = $request->input('status');
        $task->save();

        return response()->json(['message' => 'Task status updated successfully', 'task' => [
            'id' => $task->id,
            'name' => $task->name,
            'status' => $task->status,
            'updated_at' => $task->updated_at->format('Y-m-d H:i:s'),
        ]]);
    }

}
