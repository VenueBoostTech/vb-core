<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\AppSuite\Staff\Project;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeEntryController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function index(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $employee =  $this->venueService->employee();

        if($venue->id !== $employee->restaurant_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $timeEntries = $employee->timeEntries()->with(['project', 'task'])->get();
        return response()->json($timeEntries);
    }

    public function store(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $employee =  $this->venueService->employee();

        if($venue->id !== $employee->restaurant_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'start_time' => 'required|date',
            'end_time' => 'required_without:duration|date|after:start_time',
            'duration' => 'required_without:end_time|integer|min:1',
            'project_id' => 'nullable|exists:projects,id',
            'task_id' => 'nullable|exists:tasks,id',
            'description' => 'nullable|string',
            'is_manual' => 'required|boolean',
        ]);

        if (!isset($validated['duration'])) {
            $validated['duration'] = Carbon::parse($validated['end_time'])->diffInSeconds(Carbon::parse($validated['start_time']));
        }

        if (isset($validated['project_id'])) {
            $project = Project::findOrFail($validated['project_id']);
            if (!$employee->projects->contains($project->id)) {
                return response()->json(['message' => 'You are not assigned to this project'], 403);
            }
        }

        if (isset($validated['task_id'])) {
            $task = Task::findOrFail($validated['task_id']);
            if (!$employee->tasks->contains($task->id)) {
                return response()->json(['message' => 'You are not assigned to this task'], 403);
            }
        }

        $timeEntry = $employee->timeEntries()->create($validated);
        return response()->json($timeEntry, 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $employee =  $this->venueService->employee();

        if($venue->id !== $employee->restaurant_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $timeEntry = $employee->timeEntries()->findOrFail($id);

        $validated = $request->validate([
            'start_time' => 'sometimes|required|date',
            'end_time' => 'sometimes|required_without:duration|date|after:start_time',
            'duration' => 'sometimes|required_without:end_time|integer|min:1',
            'project_id' => 'nullable|exists:projects,id',
            'task_id' => 'nullable|exists:tasks,id',
            'description' => 'nullable|string',
        ]);

        if (isset($validated['start_time']) && isset($validated['end_time'])) {
            $validated['duration'] = Carbon::parse($validated['end_time'])->diffInSeconds(Carbon::parse($validated['start_time']));
        }

        if (isset($validated['project_id'])) {
            $project = Project::findOrFail($validated['project_id']);
            if (!$employee->projects->contains($project->id)) {
                return response()->json(['message' => 'You are not assigned to this project'], 403);
            }
        }

        if (isset($validated['task_id'])) {
            $task = Task::findOrFail($validated['task_id']);
            if (!$employee->tasks->contains($task->id)) {
                return response()->json(['message' => 'You are not assigned to this task'], 403);
            }
        }

        $timeEntry->update($validated);
        return response()->json($timeEntry);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $employee =  $this->venueService->employee();

        if($venue->id !== $employee->restaurant_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $timeEntry = $employee->timeEntries()->findOrFail($id);
        $timeEntry->delete();
        return response()->json(null, 204);
    }

    public function startTimer(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $employee =  $this->venueService->employee();

        if($venue->id !== $employee->restaurant_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'task_id' => 'nullable|exists:tasks,id',
            'description' => 'nullable|string',
        ]);

        $activeTimer = $employee->timeEntries()->whereNull('end_time')->first();
        if ($activeTimer) {
            return response()->json(['message' => 'You already have an active timer'], 400);
        }

        $timeEntry = $employee->timeEntries()->create([
            'start_time' => now(),
            'is_manual' => false,
            'project_id' => $validated['project_id'] ?? null,
            'task_id' => $validated['task_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json($timeEntry, 201);
    }

    public function stopTimer(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        $employee =  $this->venueService->employee();

        if($venue->id !== $employee->restaurant_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $activeTimer = $employee->timeEntries()->whereNull('end_time')->first();
        if (!$activeTimer) {
            return response()->json(['message' => 'No active timer found'], 404);
        }

        $activeTimer->update([
            'end_time' => now(),
            'duration' => now()->diffInSeconds($activeTimer->start_time),
        ]);

        return response()->json($activeTimer);
    }
}
