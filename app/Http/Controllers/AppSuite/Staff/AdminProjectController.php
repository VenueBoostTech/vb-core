<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\AppClient;
use App\Models\AppGallery;
use App\Models\AppProject;
use App\Models\City;
use App\Models\Country;
use App\Models\Employee;
use App\Models\State;
use App\Models\Team;
use App\Models\TimeEntry;
use App\Services\AppNotificationService;
use App\Services\VenueService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Notification;
use App\Models\User;

class AdminProjectController extends Controller
{
    protected VenueService $venueService;
    protected AppNotificationService $notificationService;

    public function __construct(VenueService $venueService, AppNotificationService $notificationService)
    {
        $this->venueService = $venueService;
        $this->notificationService = $notificationService;
    }

    public function getProjectStatuses(): JsonResponse
    {
        return response()->json(AppProject::getStatuses());
    }

    public function index(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $perPage = $request->input('per_page', 15);
        $projects = AppProject::where('venue_id', $venue->id)
            ->with([
                'department',
                'team',
                'assignedEmployees',
                'projectManager',
                'teamLeaders',
                'operationsManagers',
                'timeEntries',
                'client'
            ])
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        $formattedProjects = $projects->map(function ($project) {
            $totalEstimatedHours = $project->estimated_hours ?? 0;
            $totalWorkedHours = $project->timeEntries->sum('duration') / 3600;
            $progress = $totalEstimatedHours > 0 ? min(100, ($totalWorkedHours / $totalEstimatedHours) * 100) : 0;

            // Collect all team members without duplicates
            $allTeamMembers = collect()
                ->concat($project->assignedEmployees)
                ->concat($project->teamLeaders)
                ->concat($project->operationsManagers)
                ->when($project->projectManager, fn($collection) => $collection->push($project->projectManager))
                ->unique('id')
                ->values()
                ->map(function ($employee) {
                    return [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'avatar' => $employee->profile_picture
                            ? Storage::disk('s3')->temporaryUrl($employee->profile_picture, now()->addMinutes(5))
                            : $this->getInitials($employee->name)
                    ];
                });

            return [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'status' => $project->status,
                'progress' => round($progress, 2),
                'project_type' => $project->project_type,
                'project_category' => $project->project_category,
                'estimated_budget' => $project->estimated_budget,
                'estimated_hours' => $totalEstimatedHours,
                'worked_hours' => round($totalWorkedHours, 2),
                'client' => $project->client ? [
                    'id' => $project->client->id,
                    'name' => $project->client->name,
                ] : null,
                'assigned_employees' => $allTeamMembers
            ];
        });

        return response()->json([
            'projects' => $formattedProjects,
            'current_page' => $projects->currentPage(),
            'per_page' => $projects->perPage(),
            'total' => $projects->total(),
            'total_pages' => $projects->lastPage(),
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
    public function store(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:planning,in_progress,on_hold,completed,cancelled,archived,draft',
            'department_id' => 'nullable|exists:departments,id',
            'team_id' => 'nullable|exists:teams,id',
            'estimated_hours' => 'nullable|numeric|min:0',
            'estimated_budget' => 'nullable|numeric|min:0',
            'project_manager_id' => 'nullable|exists:employees,id',
            'project_type' => 'required|string',
            'project_category' => 'required|in:inhouse,client',
            'client_id' => 'nullable|required_if:project_category,client|exists:app_clients,id',
            'has_different_address' => 'required|boolean',
            'address' => 'required_if:has_different_address,true|array',
            'address.address_line1' => 'required_if:has_different_address,true|string|max:255',
            'address.city_id' => 'required_if:has_different_address,true|exists:cities,id',
            'address.state_id' => 'required_if:has_different_address,true|exists:states,id',
            'address.country_id' => 'required_if:has_different_address,true|exists:countries,id',
            'address.postal_code' => 'required_if:has_different_address,true|string|max:20',
            'team_leader_ids' => 'nullable|array',
            'team_leader_ids.*' => 'exists:employees,id',
            'operations_manager_ids' => 'nullable|array',
            'operations_manager_ids.*' => 'exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        try {
            DB::beginTransaction();

            // Address handling
            $addressId = null;
            if ($validated['has_different_address'] && isset($validated['address'])) {
                $state = State::findOrFail($validated['address']['state_id']);
                $country = Country::findOrFail($validated['address']['country_id']);
                $city = City::findOrFail($validated['address']['city_id']);

                $address = Address::create([
                    'address_line1' => $validated['address']['address_line1'],
                    'city_id' => $city->id,
                    'state_id' => $state->id,
                    'country_id' => $country->id,
                    'postcode' => $validated['address']['postal_code'],
                    'state' => $state->name,
                    'city' => $city->name,
                    'country' => $country->name,
                ]);

                $addressId = $address->id;
            } elseif ($validated['project_category'] === 'client') {
                // Use client's address
                $client = AppClient::findOrFail($validated['client_id']);
                $addressId = $client->address_id;
            }

            // Project creation
            $project = AppProject::create(array_merge(
                collect($validated)->except(['address', 'has_different_address', 'team_leader_ids', 'operations_manager_ids'])->toArray(),
                [
                    'venue_id' => $venue->id,
                    'address_id' => $addressId,
                ]
            ));

            // Attach Team Leaders
            if (isset($validated['team_leader_ids'])) {
                foreach ($validated['team_leader_ids'] as $teamLeaderId) {
                    $employee = Employee::find($teamLeaderId);
                    if (!$employee || !$employee->hasAnyRoleUpdated('Team Leader')) {
                        throw new \Exception('Employee with ID ' . $teamLeaderId . ' is not a Team Leader');
                    }
                    $project->teamLeaders()->attach($teamLeaderId);
                    // Send notification to team leader
                    $content = [
                        'project_id' => (string)$project->id,
                    ];
                    $notification = $this->notificationService->sendNotification($employee, 'new_project_assigned', 'You have been assigned to a new project: ' . $project->name, $content);
                    if($notification){
                        $this->notificationService->sendPushNotificationToUser(
                            $employee, 'new_project_assigned', 'You have been assigned to a new project: ' . $project->name, [
                                'project_id' => (string)$project->id,
                                'venue_id' => (string)$venue->id,
                                'click_action' => 'project_details',
                            ], $notification);
                    }
                   
                }
            }

            // Attach Operations Managers
            if (isset($validated['operations_manager_ids'])) {
                foreach ($validated['operations_manager_ids'] as $operationsManagerId) {
                    $employee = Employee::find($operationsManagerId);
                    if (!$employee || !$employee->hasAnyRoleUpdated('Operations Manager')) {
                        throw new \Exception('Employee with ID ' . $operationsManagerId . ' is not an Operations Manager');
                    }
                    $project->operationsManagers()->attach($operationsManagerId);
                    $content = [
                        'project_id' => (string)$project->id,
                    ];
                    $notification = $this->notificationService->sendNotification($employee, 'new_project_assigned', 'You have been assigned to a new project: ' . $project->name, $content);
                    if($notification){
                        $this->notificationService->sendPushNotificationToUser(
                            $employee, 'new_project_assigned', 'You have been assigned to a new project: ' . $project->name, [
                                'project_id' => (string)$project->id,
                                'venue_id' => (string)$venue->id,
                                'click_action' => 'project_details'
                            ], $notification);
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'Project created successfully'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create project: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
            'team_id' => 'nullable|exists:teams,id',
            'estimated_hours' => 'nullable|numeric|min:0',
            'estimated_budget' => 'nullable|numeric|min:0',
            'project_manager_id' => 'nullable|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $project->update($validator->validated());
        return response()->json($project);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $project->delete();
        return response()->json(['message' => 'Project deleted successfully'], 200);
    }

    public function assignEmployee(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee = Employee::where('restaurant_id', $venue->id)->find($validator->validated()['employee_id']);
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $project->assignedEmployees()->attach($employee->id, ['assigned_at' => now()]);

        $this->notificationService->sendNotification(
            $employee,
            'task_notifications',
            "You have been assigned to the project: {$project->name}"
        );

        return response()->json(['message' => 'Employee assigned successfully']);
    }

    public function unassignEmployee(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee = Employee::where('restaurant_id', $venue->id)->find($validator->validated()['employee_id']);
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Check if the employee is actually assigned to the project
        if (!$project->assignedEmployees()->where('employee_id', $employee->id)->exists()) {
            return response()->json(['error' => 'Employee is not assigned to this project'], 400);
        }

        // Detach the employee from the project
        $project->assignedEmployees()->detach($employee->id);

        $this->notificationService->sendNotification(
            $employee,
            'task_notifications',
            "You have been unassigned from the project: {$project->name}"
        );

        return response()->json(['message' => 'Employee unassigned successfully']);
    }

    public function assignTeam(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'team_id' => 'required|exists:teams,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $team = Team::where('venue_id', $venue->id)->find($validator->validated()['team_id']);
        if (!$team) {
            return response()->json(['error' => 'Team not found'], 404);
        }

        $project->team()->associate($team);
        $project->save();

        return response()->json(['message' => 'Team assigned successfully']);
    }

    public function assignProjectManager(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee = Employee::where('restaurant_id', $venue->id)->find($validator->validated()['employee_id']);
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $project->projectManager()->associate($employee);
        $project->save();

        $this->notificationService->sendNotification(
            $employee,
            'task_notifications',
            "You have been assigned as the project manager for: {$project->name}"
        );

        return response()->json(['message' => 'Project manager assigned successfully']);
    }


    public function show($id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $project = AppProject::where('venue_id', $venue->id)
            ->with([
                'department',
                'team',
                'assignedEmployees',
                'projectManager',
                'timeEntries',
                'tasks.assignedEmployees',
                'client',
                'address',
                'service',
                'serviceRequest',
                'teamLeaders',
                'operationsManagers'
            ])
            ->find($id);

        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $totalEstimatedHours = $project->estimated_hours ?? 0;
        $totalWorkedHours = $project->timeEntries->sum('duration') / 3600;
        $progress = $totalEstimatedHours > 0 ? min(100, ($totalWorkedHours / $totalEstimatedHours) * 100) : 0;

        // Collect all team members without duplicates
        $allTeamMembers = collect()
            ->concat($project->assignedEmployees)
            ->concat($project->teamLeaders)
            ->concat($project->operationsManagers)
            ->when($project->projectManager, fn($collection) => $collection->push($project->projectManager))
            ->unique('id')
            ->values()
            ->map(function ($employee) use ($project) {
                $employeeData = [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'avatar' => $employee->profile_picture
                        ? Storage::disk('s3')->temporaryUrl($employee->profile_picture, now()->addMinutes(5))
                        : $this->getInitials($employee->name)
                ];

                // Add tasks and time entries only for assigned employees
                if ($project->assignedEmployees->contains('id', $employee->id)) {
                    $employeeData['tasks'] = $project->tasks
                        ->where('employee_id', $employee->id)
                        ->map(function ($task) {
                            return [
                                'id' => $task->id,
                                'name' => $task->name,
                                'status' => $task->status,
                                'time_entries' => $task->timeEntries->map(function ($timeEntry) {
                                    return [
                                        'id' => $timeEntry->id,
                                        'start_time' => $timeEntry->start_time,
                                        'end_time' => $timeEntry->end_time,
                                        'duration' => $timeEntry->duration
                                    ];
                                })
                            ];
                        });

                    $employeeData['time_entries_without_tasks'] = $project->timeEntries
                        ->where('employee_id', $employee->id)
                        ->whereNull('task_id')
                        ->map(function ($timeEntry) {
                            return [
                                'id' => $timeEntry->id,
                                'start_time' => $timeEntry->start_time,
                                'end_time' => $timeEntry->end_time,
                                'duration' => $timeEntry->duration,
                                'description' => $timeEntry->description
                            ];
                        });
                }

                return $employeeData;
            });

        $formattedProject = [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'start_date' => $project->start_date,
            'end_date' => $project->end_date,
            'status' => $project->status,
            'progress' => round($progress, 2),
            'estimated_hours' => $totalEstimatedHours,
            'worked_hours' => round($totalWorkedHours, 2),
            'estimated_budget' => $project->estimated_budget,
            'project_type' => $project->project_type,
            'project_category' => $project->project_category,
            'project_source' => $project->project_source,
            'client' => $project->client ? [
                'id' => $project->client->id,
                'name' => $project->client->name,
                'type' => $project->client->type,
                'contact_person' => $project->client->contact_person,
                'email' => $project->client->email,
                'phone' => $project->client->phone,
            ] : null,
            'address' => $project->address ? [
                'address_line1' => $project->address->address_line1,
                'city' => $project->address->city,
                'state' => $project->address->state,
                'country' => $project->address->country,
                'postcode' => $project->address->postcode,
            ] : null,
            'service' => $project->service ? [
                'id' => $project->service->id,
                'name' => $project->service->name,
                'quoted_price' => $project->quoted_price,
                'final_price' => $project->final_price,
                'service_details' => $project->service_details,
            ] : null,
            'department' => $project->department ? [
                'id' => $project->department->id,
                'name' => $project->department->name,
            ] : null,
            'team' => $project->team ? [
                'id' => $project->team->id,
                'name' => $project->team->name,
            ] : null,
            'project_manager' => $project->projectManager ? [
                'id' => $project->projectManager->id,
                'name' => $project->projectManager->name,
                'avatar' => $this->getAvatarUrl($project->projectManager)
            ] : null,
            'team_leaders' => $project->teamLeaders->map(function ($leader) {
                return [
                    'id' => $leader->id,
                    'name' => $leader->name,
                    'avatar' => $this->getAvatarUrl($leader)
                ];
            }),
            'operations_managers' => $project->operationsManagers->map(function ($manager) {
                return [
                    'id' => $manager->id,
                    'name' => $manager->name,
                    'avatar' => $this->getAvatarUrl($manager)
                ];
            }),
            'assigned_employees' => $allTeamMembers,
            'tasks' => $project->tasks->map(function ($task) use ($project) {
                $assignedEmployee = $task->assignedEmployees->first();
                return [
                    'id' => $task->id,
                    'name' => $task->name,
                    'status' => $task->status,
                    'priority' => $task->priority ?? 'medium', // Add default priority
                    'start_date' => $task->start_date,
                    'due_date' => $task->due_date,
                    'assignee' => $assignedEmployee ? [
                        'id' => $assignedEmployee->id,
                        'name' => $assignedEmployee->name,
                        'avatar' => $this->getAvatarUrl($assignedEmployee)
                    ] : null,
                ];
            }),
            'time_entries' => $project->timeEntries->map(function ($timeEntry) {
                return [
                    'id' => $timeEntry->id,
                    'employee' => [
                        'id' => $timeEntry->employee->id,
                        'name' => $timeEntry->employee->name
                    ],
                    'start_time' => $timeEntry->start_time,
                    'end_time' => $timeEntry->end_time,
                    'duration' => $timeEntry->duration,
                    'description' => $timeEntry->description,
                    'task' => $timeEntry->task ? [
                        'id' => $timeEntry->task->id,
                        'name' => $timeEntry->task->name
                    ] : null
                ];
            })
        ];

        return response()->json($formattedProject);
    }

    private function getAvatarUrl($user): string
    {
        return $user->profile_picture
            ? Storage::disk('s3')->temporaryUrl($user->profile_picture, now()->addMinutes(5))
            : $this->getInitials($user->name);
    }

    public function assignTeamLeaders(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        // Validate project existence
        $project = AppProject::where('venue_id', $venue->id)->findOrFail($id);

        // Validate request data
        $validator = Validator::make($request->all(), [
            'team_leader_ids' => 'required|array',
            'team_leader_ids.*' => 'required|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Fetch team leaders
        $teamLeaders = Employee::whereIn('id', $request->team_leader_ids)
            ->where('restaurant_id', $venue->id)
            ->get();

        // Debugging output
        if ($teamLeaders->isEmpty()) {
            // Return which IDs were provided and are missing in the database
            return response()->json(['error' => 'No valid team leaders found for the provided IDs', 'provided_ids' => $request->team_leader_ids], 404);
        }

        // Check if all selected team leaders have the 'Team Leader' role
        $invalidLeaders = $teamLeaders->filter(function ($employee) {
            return !$employee->role || $employee->role->name !== 'Team Leader';
        });

        // If there are any invalid leaders, return an error
        if ($invalidLeaders->isNotEmpty()) {
            return response()->json(['error' => 'All selected employees must have the Team Leader role'], 422);
        }

        // If all validations pass, assign team leaders
        $project->teamLeaders()->sync($teamLeaders);

        return response()->json(['message' => 'Team leaders assigned successfully'], 200);
    }



    public function assignOperationsManagers(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        // Validate project existence
        $project = AppProject::where('venue_id', $venue->id)->findOrFail($id);

        // Validate request data
        $validator = Validator::make($request->all(), [
            'operations_manager_ids' => 'required|array',
            'operations_manager_ids.*' => 'required|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Fetch operations managers
        $operationsManagers = Employee::whereIn('id', $request->operations_manager_ids)
            ->where('restaurant_id', $venue->id)
            ->get();

        // Debugging output for missing managers
        if ($operationsManagers->isEmpty()) {
            return response()->json(['error' => 'No valid operations managers found for the provided IDs', 'provided_ids' => $request->operations_manager_ids], 404);
        }

        // Check if all selected operations managers have the 'Operations Manager' role
        $invalidManagers = $operationsManagers->filter(function ($employee) {
            return !$employee->role || $employee->role->name !== 'Operations Manager';
        });

        // If there are any invalid managers, return an error
        if ($invalidManagers->isNotEmpty()) {
            return response()->json(['error' => 'All selected employees must have the Operations Manager role'], 422);
        }

        // If all validations pass, assign operations managers
        $project->operationsManagers()->sync($operationsManagers);

        return response()->json(['message' => 'Operations managers assigned successfully'], 200);
    }

    public function getAppGalleriesByProjectId(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $perPage = $request->input('per_page', 15);
        $galleries = AppGallery::where('app_project_id', $id)
            ->with('uploader') // Eager load the uploader relationship
            ->paginate($perPage);

        $formattedGalleries = $galleries->map(function ($gallery) {
            $media = [
                'id' => $gallery->id,
                'type' => $gallery->type, // Use the new 'type' column
                'uploader' => [
                    'id' => $gallery->uploader->id,
                    'name' => $gallery->uploader->name,
                    'avatar' => $gallery->uploader->profile_picture
                        ? Storage::disk('s3')->temporaryUrl($gallery->uploader->profile_picture, '+5 minutes')
                        : $this->getInitials($gallery->uploader->name)
                ]
            ];

            if ($gallery->type === 'image') {
                $media['content'] = Storage::disk('s3')->temporaryUrl($gallery->photo_path, '+5 minutes');
            } elseif ($gallery->type === 'video') {
                $media['content'] = Storage::disk('s3')->temporaryUrl($gallery->video_path, '+5 minutes');
            }

            return $media;
        });

        return response()->json([
            'data' => $formattedGalleries,
            'current_page' => $galleries->currentPage(),
            'per_page' => $galleries->perPage(),
            'total' => $galleries->total(),
            'total_pages' => $galleries->lastPage(),
        ], 200);
    }


    // Add app gallery for the project
    public function addAppGallery(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $userId = auth()->user()->id;

        // find employee

        $employee = Employee::where('user_id', $userId)->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }


        // Update validation rules
        $validator = Validator::make($request->all(), [
            'photo' => 'nullable|image|max:15360', // Image validation
            'video' => 'nullable|mimes:mp4,avi,mov|max:102400', // Video validation (maximum file size of 100MB)
            'type' => 'required|string|in:image,video', // Ensure type is either image or video
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        // Initialize a new AppGallery instance
        $gallery = new AppGallery();
        $gallery->app_project_id = $project->id;
        $gallery->venue_id = $venue->id; // Store venue ID
        $gallery->uploader_id = $employee->id; // Use uploader_id here

        // Handle file uploads based on type
        if ($request->type === 'image' && $request->hasFile('photo')) {
            $photoFile = $request->file('photo');
            $filename = Str::random(20) . '.' . $photoFile->getClientOriginalExtension();
            // Upload photo to AWS S3
            $path = Storage::disk('s3')->putFileAs('project_galleries/images', $photoFile, $filename);
            $gallery->photo_path = $path; // Save the image path
        } elseif ($request->type === 'video' && $request->hasFile('video')) {
            $videoFile = $request->file('video');
            $filename = Str::random(20) . '.' . $videoFile->getClientOriginalExtension();
            // Upload video to AWS S3
            $path = Storage::disk('s3')->putFileAs('project_galleries/videos', $videoFile, $filename);
            $gallery->video_path = $path; // Save the video path
        } else {
            return response()->json(['error' => 'No valid file uploaded'], 400);
        }

        $gallery->type = $request->type; // Set the type (image or video)
        $gallery->save();

        return response()->json(['message' => 'Gallery item added successfully']);
    }

    // Remove app gallery for the project
    // Remove app gallery for the project
    public function removeAppGallery($id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        // Fetch the gallery item
        $gallery = AppGallery::where('venue_id', $venue->id)->find($id);
        if (!$gallery) {
            return response()->json(['error' => 'Gallery not found'], 404);
        }

        // Check if the authenticated user is the uploader
        $userId = auth()->user()->id;
        if ($gallery->uploader_id !== $userId) {
            return response()->json(['error' => 'You are not authorized to delete this gallery item'], 403);
        }

        // Delete the photo from AWS S3
        if ($gallery->photo_path) {
            Storage::disk('s3')->delete($gallery->photo_path);
        }

        // Delete the video from AWS S3 if it exists
        if ($gallery->video_path) {
            Storage::disk('s3')->delete($gallery->video_path);
        }

        // Delete the gallery record from the database
        $gallery->delete();

        return response()->json(['message' => 'Gallery item removed successfully']);
    }

    public function getProjectTeam($id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $project = AppProject::where('venue_id', $venue->id)
                ->with([
                    'assignedEmployees.role',
                    'projectManager.role',
                    'teamLeaders.role',
                    'operationsManagers.role',
                    'team'
                ])
                ->findOrFail($id);

            $teamData = [
                'project_manager' => $project->projectManager ? [
                    'id' => $project->projectManager->id,
                    'name' => $project->projectManager->name,
                    'email' => $project->projectManager->email,
                    'phone' => $project->projectManager->company_phone,
                    'profile_picture' => $project->projectManager->profile_picture
                        ? Storage::disk('s3')->temporaryUrl($project->projectManager->profile_picture, now()->addMinutes(5))
                        : $this->getInitials($project->projectManager->name),
                    'role' => $project->projectManager->role?->name,
                    'status' => $project->projectManager->status,
                ] : null,
                'team_leaders' => $project->teamLeaders->map(function ($leader) {
                    return [
                        'id' => $leader->id,
                        'name' => $leader->name,
                        'email' => $leader->email,
                        'phone' => $leader->company_phone,
                        'profile_picture' => $leader->profile_picture
                            ? Storage::disk('s3')->temporaryUrl($leader->profile_picture, now()->addMinutes(5))
                            : $this->getInitials($leader->name),
                        'role' => $leader->role?->name,
                        'status' => $leader->status,
                    ];
                }),
                'operations_managers' => $project->operationsManagers->map(function ($manager) {
                    return [
                        'id' => $manager->id,
                        'name' => $manager->name,
                        'email' => $manager->email,
                        'phone' => $manager->company_phone,
                        'profile_picture' => $manager->profile_picture
                            ? Storage::disk('s3')->temporaryUrl($manager->profile_picture, now()->addMinutes(5))
                            : $this->getInitials($manager->name),
                        'role' => $manager->role?->name,
                        'status' => $manager->status,
                    ];
                }),
                'team_members' => $project->assignedEmployees->map(function ($employee) {
                    return [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'phone' => $employee->company_phone,
                        'profile_picture' => $employee->profile_picture
                            ? Storage::disk('s3')->temporaryUrl($employee->profile_picture, now()->addMinutes(5))
                            : $this->getInitials($employee->name),
                        'role' => $employee->role?->name,
                        'status' => $employee->status,
                        'assigned_at' => $employee->pivot->created_at?->format('Y-m-d'),
                    ];
                }),
                'assigned_team' => $project->team ? [
                    'id' => $project->team->id,
                    'name' => $project->team->name,
                    'department' => $project->team->department ? [
                        'id' => $project->team->department->id,
                        'name' => $project->team->department->name,
                    ] : null,
                ] : null,
            ];

            return response()->json($teamData);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Project not found'], 404);
        }
    }


    // storeTimeEntry

    public function storeTimeEntry(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        // Fetch the project based on venue_id and id
        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'description' => 'nullable|string',
            'task_id' => 'nullable|exists:tasks,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate employee assignment to project
        $employee = Employee::where('restaurant_id', $venue->id)->find($request->employee_id);
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Collect all team members without duplicates
        $allTeamMembers = collect()
            ->concat($project->assignedEmployees()->pluck('employee_id'))
            ->concat($project->teamLeaders()->pluck('employee_id'))
            ->concat($project->operationsManagers()->pluck('employee_id'))
            ->when($project->projectManager, fn($collection) => $collection->push($project->projectManager->id))
            ->unique()
            ->values();

        $isTeamMember = $allTeamMembers->contains($request->employee_id);

        if (!$isTeamMember) {
            return response()->json(['error' => 'Employee is not assigned to this project'], 403);
        }

        // Prepare time entry data
        $timeEntryData = array_merge($validator->validated(), [
            'is_manually_entered' => true,
            'venue_id' => $venue->id,
            'project_id' => $project->id,
            'duration' => $this->calculateDuration($request->start_time, $request->end_time),
        ]);

        // Create the time entry
        $timeEntry = $project->timeEntries()->create($timeEntryData);

        return response()->json(['message' => 'Time entry created successfully', 'data' => $timeEntry]);
    }

    public function getAllTimeEntries(Request $request): JsonResponse
    {
        // Authenticate and authorize the user
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        // Fetch all time entries for the given venue_id
        $timeEntries = TimeEntry::where('venue_id', $venue->id)
            ->with(['employee', 'project', 'task'])  // Eager load relationships
            ->orderBy('id', 'desc')
            ->get();

        if ($timeEntries->isEmpty()) {
            return response()->json(['error' => 'No time entries found for this venue'], 404);
        }

        // Format the response data
        $timeEntriesData = $timeEntries->map(function ($timeEntry) {
            return [
                'id' => $timeEntry->id,
                'employee' => [
                    'id' => $timeEntry->employee->id,
                    'name' => $timeEntry->employee->name
                ],
                'start_time' => $timeEntry->start_time,
                'end_time' => $timeEntry->end_time,
                'duration' => $timeEntry->duration,
                'description' => $timeEntry->description,
                'task' => $timeEntry->task ? [
                    'id' => $timeEntry->task->id,
                    'name' => $timeEntry->task->name
                ] : null,
                'project' => [
                    'project_id' => $timeEntry->project?->id ?? '#',
                    'project_name' => $timeEntry->project?->name ?? 'Deleted/Deactivated Project'
                ]
            ];
        });

        return response()->json([
            'message' => 'Time entries fetched successfully',
            'data' => $timeEntriesData
        ]);
    }

    private function calculateDuration($startTime, $endTime): int
    {
        $start = new \DateTime($startTime);
        $end = new \DateTime($endTime);
        $interval = $end->diff($start);

        // Convert to seconds
        return ($interval->days * 24 * 60 * 60) +
            ($interval->h * 60 * 60) +
            ($interval->i * 60) +
            $interval->s;
    }
    public function unassignTeamLeader(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $project = AppProject::where('venue_id', $venue->id)->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'team_leader_ids' => 'required|array',
                'team_leader_ids.*' => 'required|exists:employees,id'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Check if team leaders are actually assigned to the project
            $assignedTeamLeaderIds = $project->teamLeaders()->pluck('employees.id');
            $unassignIds = collect($request->team_leader_ids)->filter(function($id) use ($assignedTeamLeaderIds) {
                return $assignedTeamLeaderIds->contains($id);
            });

            if ($unassignIds->isEmpty()) {
                return response()->json(['error' => 'None of the specified team leaders are assigned to this project'], 400);
            }

            // Detach only the specified team leaders
            $project->teamLeaders()->detach($unassignIds);

            // Send notification to the unassigned team leaders
            $teamLeaders = Employee::whereIn('id', $unassignIds)->get();

            foreach ($teamLeaders as $teamLeader) {
                $this->notificationService->sendNotification(
                    $teamLeader,
                    'task_notifications',
                    "You have been unassigned as team leader from the project: {$project->name}"
                );
            }

            return response()->json(['message' => 'Team leaders unassigned successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Project not found'], 404);
        }
    }

    public function unassignProjectManager(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $project = AppProject::where('venue_id', $venue->id)->findOrFail($id);

            if (!$project->project_manager_id) {
                return response()->json(['error' => 'No project manager assigned to this project'], 400);
            }

            // Store the current project manager for notification
            $currentManager = $project->projectManager;

            // Remove the project manager
            $project->project_manager_id = null;
            $project->save();

            if ($currentManager) {
                $this->notificationService->sendNotification(
                    $currentManager,
                    'task_notifications',
                    "You have been unassigned as project manager from the project: {$project->name}"
                );
            }

            return response()->json(['message' => 'Project manager unassigned successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Project not found'], 404);
        }
    }

    public function unassignOperationsManager(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $project = AppProject::where('venue_id', $venue->id)->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'operations_manager_ids' => 'required|array',
                'operations_manager_ids.*' => 'required|exists:employees,id'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Check if operations managers are actually assigned to the project
            $assignedManagerIds = $project->operationsManagers()->pluck('employees.id');
            $unassignIds = collect($request->operations_manager_ids)->filter(function($id) use ($assignedManagerIds) {
                return $assignedManagerIds->contains($id);
            });

            if ($unassignIds->isEmpty()) {
                return response()->json(['error' => 'None of the specified operations managers are assigned to this project'], 400);
            }

            // Detach only the specified operations managers
            $project->operationsManagers()->detach($unassignIds);

            // manage it with foreach
            $employess = Employee::whereIn('id', $unassignIds)->get();

            foreach ($employess as $employee) {
                $this->notificationService->sendNotification(
                    $employee,
                    'task_notifications',
                    "You have been unassigned as operations manager from the project: {$project->name}"
                );
            }

            return response()->json(['message' => 'Operations managers unassigned successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Project not found'], 404);
        }
    }



}
