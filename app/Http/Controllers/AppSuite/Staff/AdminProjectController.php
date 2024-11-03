<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\AppGallery;
use App\Models\AppProject;
use App\Models\City;
use App\Models\Country;
use App\Models\Employee;
use App\Models\State;
use App\Models\Team;
use App\Services\AppNotificationService;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
            ->with(['department', 'team', 'assignedEmployees', 'projectManager', 'timeEntries'])
            ->paginate($perPage);

        $formattedProjects = $projects->map(function ($project) {
            $totalEstimatedHours = $project->estimated_hours ?? 0;
            $totalWorkedHours = $project->timeEntries->sum('duration') / 3600; // Convert seconds to hours
            $progress = $totalEstimatedHours > 0 ? min(100, ($totalWorkedHours / $totalEstimatedHours) * 100) : 0;

            return [
                'id' => $project->id,
                'name' => $project->name,
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'status' => $project->status,
                'progress' => round($progress, 2),
                'assigned_employees' => $project->assignedEmployees->map(function ($employee) {
                    return [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'avatar' => $employee->profile_picture
                            ? Storage::disk('s3')->temporaryUrl($employee->profile_picture, now()->addMinutes(5))
                            : $this->getInitials($employee->name)
                    ];
                })
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
            'status' => 'required|string|in:' . implode(',', array_keys(AppProject::getStatuses())),
            'department_id' => 'nullable|exists:departments,id',
            'team_id' => 'nullable|exists:teams,id',
            'estimated_hours' => 'nullable|numeric|min:0',
            'estimated_budget' => 'nullable|numeric|min:0',
            'project_manager_id' => 'nullable|exists:employees,id',
            'project_type' => 'required|string',
            'project_category' => 'required|in:inhouse,client',
            'client_id' => 'required_if:project_category,client|exists:app_clients,id',
            'address' => 'required|array',
            'address.address_line1' => 'required|string|max:255',
            'address.city_id' => 'required|exists:cities,id',
            'address.state_id' => 'required|exists:states,id',
            'address.country_id' => 'required|exists:countries,id',
            'address.postal_code' => 'required|string|max:20',
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

            // Address creation logic
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

            // Project creation with address ID
            $project = AppProject::create(array_merge($validated, [
                'venue_id' => $venue->id,
                'address_id' => $address->id,
            ]));

            // Validate and attach Team Leaders
            if (isset($validated['team_leader_ids'])) {
                foreach ($validated['team_leader_ids'] as $teamLeaderId) {
                    $employee = Employee::find($teamLeaderId);

                    // Check if employee exists and has 'Team Leader' role
                    if (!$employee || !$employee->hasAnyRoleUpdated('Team Leader')) {
                        return response()->json(['error' => 'Employee with ID ' . $teamLeaderId . ' is not a Team Leader'], 422);
                    }

                    $project->teamLeaders()->attach($teamLeaderId);
                }
            }

            // Validate and attach Operations Managers
            if (isset($validated['operations_manager_ids'])) {
                foreach ($validated['operations_manager_ids'] as $operationsManagerId) {
                    $employee = Employee::find($operationsManagerId);

                    // Check if employee exists and has 'Operations Manager' role
                    if (!$employee || !$employee->hasAnyRoleUpdated('Operations Manager')) {
                        return response()->json(['error' => 'Employee with ID ' . $operationsManagerId . ' is not an Operations Manager'], 422);
                    }

                    $project->operationsManagers()->attach($operationsManagerId);
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
            ->with(['department', 'team', 'assignedEmployees', 'projectManager', 'timeEntries', 'tasks'])
            ->find($id);

        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $totalEstimatedHours = $project->estimated_hours ?? 0;
        $totalWorkedHours = $project->timeEntries->sum('duration') / 3600; // Convert seconds to hours
        $progress = $totalEstimatedHours > 0 ? min(100, ($totalWorkedHours / $totalEstimatedHours) * 100) : 0;

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
            'assigned_employees' => $project->assignedEmployees->map(function ($employee) use ($project) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'avatar' => $this->getAvatarUrl($employee),
                    'tasks' => $project->tasks->where('employee_id', $employee->id)->map(function ($task) {
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
                    }),
                    'time_entries_without_tasks' => $project->timeEntries
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
                        })
                ];
            }),
            'tasks' => $project->tasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'name' => $task->name,
                    'status' => $task->status,
                    'assigned_to' => $task->employee ? [
                        'id' => $task->employee->id,
                        'name' => $task->employee->name
                    ] : null
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
            'galleries' => $formattedGalleries,
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



}
