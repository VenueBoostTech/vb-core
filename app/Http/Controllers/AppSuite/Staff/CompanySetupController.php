<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Mail\NewStaffEmail;
use App\Models\AppProject;
use App\Models\City;
use App\Models\Country;
use App\Models\Department;
use App\Models\Order;
use App\Models\Role;
use App\Models\CustomRole;
use App\Models\Employee;
use App\Models\State;
use App\Models\Task;
use App\Models\Team;
use App\Models\Address;
use App\Models\User;
use App\Services\VenueService;
use App\Services\UserService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompanySetupController extends Controller
{
    protected VenueService $venueService;
    protected UserService $userService;

    public function __construct(VenueService $venueService, UserService $userService)
    {
        $this->venueService = $venueService;
        $this->userService = $userService;
    }

    private function authorizeOwner()
    {
        if ($response = $this->userService->checkOwnerAuthorization()) {
            return $response;
        }
        return $this->venueService->adminAuthCheck();
    }

    // Department Methods
    public function listDepartments(Request $request): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $perPage = $request->input('per_page', 15);

        $departments = Department::where('venue_id', $venue->id)
            ->withCount(['employees', 'teams', 'projects'])  // Add counts using withCount
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        // Transform the data to include the counts
        $transformedData = $departments->map(function ($department) {
            return [
                'id' => $department->id,
                'name' => $department->name,
                'description' => $department->description,
                'venue_id' => $department->venue_id,
                'created_at' => $department->created_at,
                'updated_at' => $department->updated_at,
                'stats' => [
                    'employees_count' => $department->employees_count,
                    'teams_count' => $department->teams_count,
                    'projects_count' => $department->projects_count,
                    'cross_teams_count' => $department->cross_teams_count,
                ]
            ];
        });

        $paginatedData = [
            'data' => $transformedData,
            'current_page' => $departments->currentPage(),
            'per_page' => $departments->perPage(),
            'total' => $departments->total(),
            'total_pages' => $departments->lastPage(),
        ];

        return response()->json(['departments' => $paginatedData], 200);
    }

    public function getDepartment($id): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $department = Department::where('venue_id', $venue->id)->findOrFail($id);
        return response()->json($department);
    }

    public function createDepartment(Request $request): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $department = Department::create(array_merge($validated, ['venue_id' => $venue->id]));
        return response()->json($department, 201);
    }

    public function updateDepartment(Request $request, $id): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $department = Department::where('venue_id', $venue->id)->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Department not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $department->update($validated);
        return response()->json($department);
    }

    public function deleteDepartment($id): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $department = Department::where('venue_id', $venue->id)->findOrFail($id);
            $department->delete();

            return response()->json(['message' => 'Department soft deleted successfully'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Department not found'], 404);
        }
    }
    // Role Methods
    public function listRoles(): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        // Fetch system roles
        $systemRoles = Role::where('role_type', 'vb_app')->select('id', 'name', 'description')->get();

        // Fetch custom roles created by the venue
        $customRoles = CustomRole::where('created_by_venue_id', $venue->id)->select('id', 'name', 'description')->get();

        // Get attached role IDs
        $attachedSystemRoleIds = $venue->roles()->pluck('roles.id')->toArray();
        $attachedCustomRoleIds = $venue->customRoles()->pluck('custom_roles.id')->toArray();

        // Separate attached and available roles
        $attachedRoles = collect();
        $availableRoles = collect();

        // Loop through system roles
        foreach ($systemRoles as $role) {
            $role->role_type = 'system'; // Add property to indicate this is a system role
            if (in_array($role->id, $attachedSystemRoleIds)) {
                $attachedRoles->push($role);
            } else {
                $availableRoles->push($role);
            }
        }

        // Loop through custom roles
        foreach ($customRoles as $role) {
            $role->role_type = 'custom'; // Add property to indicate this is a custom role
            if (in_array($role->id, $attachedCustomRoleIds)) {
                $attachedRoles->push($role);
            } else {
                $availableRoles->push($role);
            }
        }

        // Return the response with attached and available roles
        return response()->json([
            'attached_roles' => $attachedRoles->values(),
            'available_roles' => $availableRoles->values()
        ], 200);
    }

    public function createCustomRole(Request $request): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $customRole = CustomRole::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'created_by_venue_id' => $venue->id
        ]);

        $venue->customRoles()->attach($customRole->id);

        return response()->json($customRole, 201);
    }

    public function updateCustomRole(Request $request, $id): JsonResponse
    {
        // Authorize the venue owner and return an error if not authorized
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        // Find the custom role or return a 404 error if not found
        $customRole = CustomRole::where('created_by_venue_id', $venue->id)->find($id);
        if (!$customRole) {
            return response()->json(['message' => 'Custom role not found'], 404);
        }

        // Validate request data
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            // Update the custom role with the validated data
            $customRole->update($validated);

            return response()->json([
                'message' => 'Custom role updated successfully',
                'data' => $customRole,
            ], 200);
        } catch (\Exception $e) {
            // Return a 500 error if the update fails
            return response()->json([
                'message' => 'An error occurred while updating the custom role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function listCustomRoles(Request $request): JsonResponse
    {
        // Authorize the venue owner and return an error if not authorized
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        // Set pagination or default to 15 items per page
        $perPage = $request->input('per_page', 15);

        // Retrieve the custom roles created by the venue, optionally paginating
        $customRoles = CustomRole::where('created_by_venue_id', $venue->id)
            ->select('id', 'name', 'description', 'created_at', 'updated_at')
            ->paginate($perPage);

        // Format and return the response with pagination details
        return response()->json([
            'data' => $customRoles->items(),
            'current_page' => $customRoles->currentPage(),
            'per_page' => $customRoles->perPage(),
            'total' => $customRoles->total(),
            'total_pages' => $customRoles->lastPage(),
        ], 200);
    }


    public function detachRole(Request $request): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $validated = $request->validate([
            'role_id' => 'required|integer',
            'is_custom' => 'required|boolean',
        ]);

        try {
            if ($validated['is_custom']) {
                $role = CustomRole::findOrFail($validated['role_id']);
                $venue->customRoles()->detach($role->id);
            } else {
                $role = Role::findOrFail($validated['role_id']);
                $venue->roles()->detach($role->id);
            }

            return response()->json(['message' => 'Role detached successfully'], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Role not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to detach role: ' . $e->getMessage()], 500);
        }
    }

    public function deleteCustomRole($id): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $customRole = CustomRole::where('created_by_venue_id', $venue->id)->findOrFail($id);
            $customRole->delete();

            return response()->json(['message' => 'Custom role deleted successfully'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Custom role not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete custom role: ' . $e->getMessage()], 500);
        }
    }

    public function attachRole(Request $request): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $validated = $request->validate([
            'role_id' => 'required|integer',
            'is_custom' => 'required|boolean',
        ]);

        try {
            DB::beginTransaction();

            $existingRole = DB::table('restaurant_role')
                ->where('restaurant_id', $venue->id)
                ->where('role_id', $validated['role_id'])
                ->first();

            if ($existingRole) {
                // If the role exists but with a different is_custom value, update it
                if ($existingRole->is_custom != $validated['is_custom']) {
                    DB::table('restaurant_role')
                        ->where('restaurant_id', $venue->id)
                        ->where('role_id', $validated['role_id'])
                        ->update([
                            'is_custom' => $validated['is_custom'],
                            'updated_at' => now(),
                        ]);
                    $message = 'Role updated successfully';
                } else {
                    $message = 'Role already attached';
                }
            } else {
                // If the role doesn't exist, insert it
                DB::table('restaurant_role')->insert([
                    'restaurant_id' => $venue->id,
                    'role_id' => $validated['role_id'],
                    'is_custom' => $validated['is_custom'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $message = 'Role attached successfully';
            }

            // Ensure the role is attached to the appropriate relationship
            if ($validated['is_custom']) {
                $venue->customRoles()->syncWithoutDetaching([$validated['role_id']]);
            } else {
                $venue->roles()->syncWithoutDetaching([$validated['role_id']]);
            }

            DB::commit();
            return response()->json(['message' => $message], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Role not found'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to attach role', 'details' => $e->getMessage()], 500);
        }
    }

    // Employee Methods
    public function listEmployees(): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $employees = Employee::where('restaurant_id', $venue->id)
            ->with(['role', 'department', 'assignedProjects', 'assignedTasks'])
            ->select(
                'id',
                'name',
                'email',
                'hire_date',
                'personal_phone',
                'status',
                'profile_picture',
                'company_phone',
                'company_email',
                'role_id',
                'department_id',
                'user_id'
            )
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($employee) {
                // Get active projects count
                $activeProjects = $employee->assignedProjects()
                    ->whereIn('status', [
                        AppProject::STATUS_IN_PROGRESS,
                        AppProject::STATUS_PLANNING
                    ])
                    ->count();

                // Calculate performance based on tasks
                $totalTasks = $employee->assignedTasks()->count();

                // Get completed tasks
                $completedTasks = $employee->assignedTasks()
                    ->where('tasks.status', Task::STATUS_DONE)
                    ->count();

                // Calculate tasks completed on time
                $tasksCompletedOnTime = $employee->assignedTasks()
                    ->where('tasks.status', Task::STATUS_DONE)
                    ->where(function ($query) {
                        $query->whereNull('tasks.due_date')
                            ->orWhereRaw('tasks.updated_at <= tasks.due_date');
                    })
                    ->count();

                // Calculate performance score (0-100)
                $performanceScore = $totalTasks > 0
                    ? round(($completedTasks / $totalTasks * 0.5 +
                            $tasksCompletedOnTime / $totalTasks * 0.5) * 100)
                    : 0;

                // Check if a profile picture exists, otherwise generate initials
                $profilePicture = $employee->profile_picture
                    ? Storage::disk('s3')->temporaryUrl($employee->profile_picture, now()->addMinutes(5))
                    : $this->getInitials($employee->name);

                return [
                    'id' => $employee->id,
                    'user_id' => $employee->user_id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'status' => $employee->status,
                    'start_date' => $employee->hire_date,
                    'projects_assigned' => $activeProjects,
                    'performance' => $performanceScore,
                    'company_phone' => $employee->company_phone,
                    'company_email' => $employee->company_email,
                    'profile_picture' => $profilePicture,
                    'role' => $employee->role ? [
                        'id' => $employee->role->id,
                        'name' => $employee->role->name
                    ] : null,
                    'department' => $employee->department ? [
                        'id' => $employee->department->id,
                        'name' => $employee->department->name
                    ] : null,
                    'stats' => [
                        'total_tasks' => $totalTasks,
                        'completed_tasks' => $completedTasks,
                        'tasks_on_time' => $tasksCompletedOnTime,
                        'active_projects' => $activeProjects
                    ]
                ];
            });

        return response()->json($employees);
    }

    public function listProjectManagers(): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $projectManagers = Employee::where('restaurant_id', $venue->id)
            ->whereHas('role', function($query) {
                $query->where('name', 'Project Manager');
            })
            ->with(['role', 'department'])
            ->select(
                'id',
                'name',
                'email',
                'hire_date',
                'status',
                'profile_picture',
                'company_phone',
                'company_email',
                'role_id',
                'department_id'
            )
            ->get()
            ->map(function ($employee) {
                $activeProjects = $employee->assignedProjects()
                    ->whereIn('status', [
                        AppProject::STATUS_IN_PROGRESS,
                        AppProject::STATUS_PLANNING
                    ])
                    ->count();

                $totalTasks = $employee->assignedTasks()->count();
                $completedTasks = $employee->assignedTasks()
                    ->where('tasks.status', Task::STATUS_DONE)
                    ->count();

                $tasksCompletedOnTime = $employee->assignedTasks()
                    ->where('tasks.status', Task::STATUS_DONE)
                    ->where(function ($query) {
                        $query->whereNull('tasks.due_date')
                            ->orWhereRaw('tasks.updated_at <= tasks.due_date');
                    })
                    ->count();

                $performanceScore = $totalTasks > 0
                    ? round(($completedTasks / $totalTasks * 0.5 +
                            $tasksCompletedOnTime / $totalTasks * 0.5) * 100)
                    : 0;

                $profilePicture = $employee->profile_picture
                    ? Storage::disk('s3')->temporaryUrl($employee->profile_picture, now()->addMinutes(5))
                    : $this->getInitials($employee->name);

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'status' => $employee->status,
                    'start_date' => $employee->hire_date,
                    'projects_assigned' => $activeProjects,
                    'performance' => $performanceScore,
                    'profile_picture' => $profilePicture,
                    'company_phone' => $employee->company_phone,
                    'company_email' => $employee->company_email,
                    'role' => $employee->role ? [
                        'id' => $employee->role->id,
                        'name' => $employee->role->name
                    ] : null,
                    'department' => $employee->department ? [
                        'id' => $employee->department->id,
                        'name' => $employee->department->name
                    ] : null,
                    'stats' => [
                        'total_tasks' => $totalTasks,
                        'completed_tasks' => $completedTasks,
                        'tasks_on_time' => $tasksCompletedOnTime,
                        'active_projects' => $activeProjects
                    ]
                ];
            });

        return response()->json($projectManagers);
    }

    public function listTeamLeaders(): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $teamLeaders = Employee::where('restaurant_id', $venue->id)
            ->whereHas('role', function($query) {
                $query->where('name', 'Team Leader');
            })
            ->with(['role', 'department'])
            ->select(
                'id',
                'name',
                'email',
                'hire_date',
                'status',
                'profile_picture',
                'company_phone',
                'company_email',
                'role_id',
                'department_id'
            )
            ->get()
            ->map(function ($employee) {
                $activeProjects = $employee->assignedProjects()
                    ->whereIn('status', [
                        AppProject::STATUS_IN_PROGRESS,
                        AppProject::STATUS_PLANNING
                    ])
                    ->count();

                $totalTasks = $employee->assignedTasks()->count();
                $completedTasks = $employee->assignedTasks()
                    ->where('tasks.status', Task::STATUS_DONE)
                    ->count();

                $tasksCompletedOnTime = $employee->assignedTasks()
                    ->where('tasks.status', Task::STATUS_DONE)
                    ->where(function ($query) {
                        $query->whereNull('tasks.due_date')
                            ->orWhereRaw('tasks.updated_at <= tasks.due_date');
                    })
                    ->count();

                $performanceScore = $totalTasks > 0
                    ? round(($completedTasks / $totalTasks * 0.5 +
                            $tasksCompletedOnTime / $totalTasks * 0.5) * 100)
                    : 0;

                $profilePicture = $employee->profile_picture
                    ? Storage::disk('s3')->temporaryUrl($employee->profile_picture, now()->addMinutes(5))
                    : $this->getInitials($employee->name);

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'status' => $employee->status,
                    'start_date' => $employee->hire_date,
                    'projects_assigned' => $activeProjects,
                    'performance' => $performanceScore,
                    'profile_picture' => $profilePicture,
                    'company_phone' => $employee->company_phone,
                    'company_email' => $employee->company_email,
                    'role' => $employee->role ? [
                        'id' => $employee->role->id,
                        'name' => $employee->role->name
                    ] : null,
                    'department' => $employee->department ? [
                        'id' => $employee->department->id,
                        'name' => $employee->department->name
                    ] : null,
                    'stats' => [
                        'total_tasks' => $totalTasks,
                        'completed_tasks' => $completedTasks,
                        'tasks_on_time' => $tasksCompletedOnTime,
                        'active_projects' => $activeProjects
                    ]
                ];
            });

        return response()->json($teamLeaders);
    }

    public function listOperationsManagers(): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $operationsManagers = Employee::where('restaurant_id', $venue->id)
            ->whereHas('role', function($query) {
                $query->whereIn('name', [
                    'Operations Manager'
                ]);
            })
            ->with(['role', 'department'])
            ->select(
                'id',
                'name',
                'email',
                'hire_date',
                'status',
                'profile_picture',
                'company_phone',
                'company_email',
                'role_id',
                'department_id'
            )
            ->get()
            ->map(function ($employee) {
                $activeProjects = $employee->assignedProjects()
                    ->whereIn('status', [
                        AppProject::STATUS_IN_PROGRESS,
                        AppProject::STATUS_PLANNING
                    ])
                    ->count();

                $totalTasks = $employee->assignedTasks()->count();
                $completedTasks = $employee->assignedTasks()
                    ->where('tasks.status', Task::STATUS_DONE)
                    ->count();

                $tasksCompletedOnTime = $employee->assignedTasks()
                    ->where('tasks.status', Task::STATUS_DONE)
                    ->where(function ($query) {
                        $query->whereNull('tasks.due_date')
                            ->orWhereRaw('tasks.updated_at <= tasks.due_date');
                    })
                    ->count();

                $performanceScore = $totalTasks > 0
                    ? round(($completedTasks / $totalTasks * 0.5 +
                            $tasksCompletedOnTime / $totalTasks * 0.5) * 100)
                    : 0;

                $profilePicture = $employee->profile_picture
                    ? Storage::disk('s3')->temporaryUrl($employee->profile_picture, now()->addMinutes(5))
                    : $this->getInitials($employee->name);

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'status' => $employee->status,
                    'start_date' => $employee->hire_date,
                    'projects_assigned' => $activeProjects,
                    'performance' => $performanceScore,
                    'profile_picture' => $profilePicture,
                    'company_phone' => $employee->company_phone,
                    'company_email' => $employee->company_email,
                    'role' => $employee->role ? [
                        'id' => $employee->role->id,
                        'name' => $employee->role->name
                    ] : null,
                    'department' => $employee->department ? [
                        'id' => $employee->department->id,
                        'name' => $employee->department->name
                    ] : null,
                    'stats' => [
                        'total_tasks' => $totalTasks,
                        'completed_tasks' => $completedTasks,
                        'tasks_on_time' => $tasksCompletedOnTime,
                        'active_projects' => $activeProjects
                    ]
                ];
            });

        return response()->json($operationsManagers);
    }
    public function getEmployee($id): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $employee = Employee::where('restaurant_id', $venue->id)->findOrFail($id);

        // Handle profile picture: check if it exists or return initials if it doesn't
        $profilePicture = null;
        if ($employee->profile_picture) {
            // Generate a temporary URL for the profile picture
            $profilePicture = Storage::disk('s3')->temporaryUrl($employee->profile_picture, now()->addMinutes(5));
        } else {
            // If no profile picture, return initials
            $profilePicture = $this->getInitials($employee->name);
        }

        return response()->json([
            'id' => $employee->id,
            'name' => $employee->name,
            'profile_picture' => $profilePicture, // Include profile picture or initials
            'role' => $employee->role->name,
            'department' => $employee->department->name,
            'hire_date' => $employee->hire_date,
            'company_email' => $employee->company_email,
            'company_phone' => $employee->company_phone,
            'status' => $employee->status,
            'projects_assigned' => 0, // TODO: Implement project assignment tracking
            'performance' => 0, // TODO: Implement performance tracking
            'activities' => [], // TODO: Implement activities
        ]);
    }

    /**
     * Get the initials from the employee's name.
     *
     * @param string $name
     * @return string
     */
    private function getInitials($name): string
    {
        $words = explode(' ', $name);
        $initials = '';

        foreach ($words as $word) {
            if (isset($word[0])) {
                $initials .= strtoupper($word[0]); // Get the first letter of each name
            }
        }

        return $initials;
    }


    public function getEmployeeFullProfile($id): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $employee = Employee::with(['address', 'role', 'department'])
            ->where('restaurant_id', $venue->id)
            ->findOrFail($id);

        $profilePicture = $employee->profile_picture
            ? Storage::disk('s3')->temporaryUrl($employee->profile_picture, now()->addMinutes(5))
            : $this->getInitials($employee->name);

        $roleData = null;
        if ($employee->custom_role) {
            $customRole = CustomRole::find($employee->role_id);
            $roleData = [
                'id' => $customRole?->id,
                'name' => $customRole?->name,
                'role_type' => 'custom'
            ];
        } else {
            $roleData = [
                'id' => $employee->role?->id,
                'name' => $employee->role?->name,
                'role_type' => 'system'
            ];
        }

        return response()->json([
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'profile_picture' => $profilePicture,
            'hire_date' => $employee->hire_date,
            'personal_email' => $employee->personal_email,
            'personal_phone' => $employee->personal_phone,
            'company_email' => $employee->company_email,
            'company_phone' => $employee->company_phone,
            'status' => $employee->status,
            'role' => $roleData,
            'department_id' => $employee->department_id,
            'department' => [
                'id' => $employee->department?->id,
                'name' => $employee->department?->name
            ],
            'manager_id' => $employee->manager_id,
            'is_custom' => $employee->custom_role,
            'address' => [
                'address_line1' => $employee->address?->address_line1,
                'city_id' => $employee->address?->city_id,
                'state_id' => $employee->address?->state_id,
                'country_id' => $employee->address?->country_id,
                'postal_code' => $employee->address?->postcode
            ]
        ]);
    }

    public function createEmployee(Request $request): JsonResponse
    {
        // Authorize the venue
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:employees,email',
            'role_id' => 'required', // Ensure the role exists
            'is_custom' => 'required',
            'department_id' => 'required|exists:departments,id',
            'hire_date' => 'required|date',
            'personal_email' => 'required|email',
            'personal_phone' => 'required|string|max:20',
            'company_email' => 'required|email',
            'company_phone' => 'required|string|max:20',
            'address' => 'required|array',
            'address.address_line1' => 'required|string|max:255',
            'address.city_id' => 'required|exists:cities,id',
            'address.state_id' => 'required|exists:states,id',
            'address.country_id' => 'required|exists:countries,id',
            'address.postal_code' => 'required|string|max:20',
            'manager_id' => 'nullable|integer|exists:employees,id', // Ensure the manager_id is an employee
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'create_user' => 'required|boolean', // New property to indicate if a user should be created
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Extract the validated data
            $validatedData = $request->only([
                'name',
                'email',
                'role_id',
                'department_id',
                'hire_date',
                'personal_email',
                'personal_phone',
                'company_email',
                'company_phone',
                'manager_id',
                'profile_picture',
                'is_custom',
                'create_user'
            ]);

            // Check if the department belongs to the venue
            $department = Department::where('id', $validatedData['department_id'])
                ->where('venue_id', $venue->id)
                ->first();

            if (!$department) {
                return response()->json(['error' => 'The department does not belong to the venue.'], 422);
            }

            // Handle role verification
            if ($validatedData['is_custom']) {
                // Verify if the custom role exists for this venue
                $customRole = DB::table('custom_roles')
                    ->where('id', $validatedData['role_id'])
                    ->where('created_by_venue_id', $venue->id)
                    ->first();

                if (!$customRole) {
                    return response()->json(['error' => 'The custom role is not valid for this venue.'], 422);
                }

                // Find the 'CUSTOM ROLE' in the roles table
                $customRoleRecord = Role::where('name', 'CUSTOM ROLE')->first();

                if (!$customRoleRecord) {
                    return response()->json(['error' => 'Custom role definition not found.'], 422);
                }

                // Set role_id and custom_role for the employee
                $validatedData['custom_role'] = true; // Mark as custom role
                // Set role_id and custom_role for the employee
                $validatedData['role_id'] = $customRoleRecord->id;
            } else {
                // Check if the role is part of restaurant_roles
                $roleAttached = DB::table('restaurant_role')
                    ->where('role_id', $validatedData['role_id'])
                    ->where('restaurant_id', $venue->id)
                    ->first();

                if (!$roleAttached) {
                    return response()->json(['error' => 'The role is not valid for this venue.'], 422);
                }

                // Set role_id and custom_role for the employee
                $validatedData['custom_role'] = false; // Not a custom role
            }

            // Verify manager_id belongs to the venue (if provided)
            if ($validatedData['manager_id']) {
                $manager = Employee::where('id', $validatedData['manager_id'])
                    ->where('restaurant_id', $venue->id)
                    ->first();

                if (!$manager) {
                    $owner = Employee::where('id', $validatedData['manager_id'])->first();
                    $isOwner = $venue->user_id === $owner->user_id;

                    if (!$isOwner) {
                        return response()->json(['error' => 'The manager must be an employee under this venue.'], 422);
                    }
                }
            }

            // Create the address
            $state = State::where('id', $request->input('address.state_id'))->first();
            $country = Country::where('id', $request->input('address.country_id'))->first();
            $city = City::where('id', $request->input('address.city_id'))->first();

            $address = Address::create([
                'address_line1' => $request->input('address.address_line1'),
                'city_id' => $request->input('address.city_id'),
                'state_id' => $request->input('address.state_id'),
                'country_id' => $request->input('address.country_id'),
                'postcode' => $request->input('address.postal_code'),
                'state' => $state->name,
                'city' => $city->name,
                'country' => $country->name
            ]);

            // Prepare employee data
            $employeeData = array_merge(
                $validatedData,
                [
                    'restaurant_id' => $venue->id,
                    'address_id' => $address->id,
                    'external_ids' => json_encode([]) // Initialize empty external_ids
                ]
            );

            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                $path = Storage::disk('s3')->putFile('profile_pictures', $request->file('profile_picture'));
                $employeeData['profile_picture'] = $path; // Add profile picture path
            }

            // Create the employee
            $employee = Employee::create($employeeData);

            $validatedData['create_user'] = (int) $validatedData['create_user'];
            $userCreated = null;

            // Check if user creation is required
            if ($validatedData['create_user'] === 1) {
                $userCreated = User::create([
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'password' => Hash::make($request->input('employee_password')),
                    'country_code' => 'US',
                    'external_ids' => json_encode([]) // Initialize empty external_ids
                ]);

                // Assign the user ID to the employee
                $employee->user_id = $userCreated->id;
                $employee->save();

                // Send email notification to the new staff member
                // Commenting this since the email will be sent by the OmniStack Gateway
                /// Mail::to($employee->email)->send(new NewStaffEmail($venue));
            }

            // Call OmniStack Gateway to create employee in NestJS backend
            try {
                // Get the venue owner user to retrieve their OmniStack ID
                $venueOwner = User::find($venue->user_id);
                $adminOmniStackId = null;

                // Extract the venue owner's OmniStack ID from their external_ids
                if ($venueOwner && !empty($venueOwner->external_ids)) {
                    $ownerExternalIds = is_string($venueOwner->external_ids)
                        ? json_decode($venueOwner->external_ids, true)
                        : $venueOwner->external_ids;

                    if (is_array($ownerExternalIds) && isset($ownerExternalIds['omniStackGateway'])) {
                        $adminOmniStackId = $ownerExternalIds['omniStackGateway'];
                    }
                }

                // Split name into first and last name for NestJS service
                $nameParts = explode(' ', $employee->name, 2);
                $firstName = $nameParts[0];
                $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

                // Prepare external IDs and metadata
                $externalIds = [
                    'vbEmployeeId' => (string)$employee->id,
                    'vbVenueId' => (string)$venue->id
                ];

                if ($userCreated) {
                    $externalIds['vbUserId'] = (string)$userCreated->id;
                }

                $metadata = [
                    'source' => 'php_venue_app',
                    'vb_venue_id' => $venue->id,
                    'vb_employee_id' => $employee->id,
                    'vb_role_id' => $employee->role_id,
                    'vb_department_id' => $employee->department_id
                ];

                // Build payload for OmniStack
                $omniStackData = [
                    'name' => $firstName,
                    'surname' => $lastName,
                    'email' => $employee->email,
                    'adminUserId' => $adminOmniStackId, // Use the owner's OmniStack ID
                    'createAccount' => $validatedData['create_user'] === 1,
                    'password' => $request->input('employee_password') ? $request->input('employee_password') : null,
                    'external_ids' => $externalIds,
                    'metadata' => $metadata
                ];

                // Only proceed with the API call if we have a valid adminUserId
                if ($adminOmniStackId) {
                    // Make API call to OmniStack
                    $response = Http::withHeaders([
                        'x-api-key' => env('OMNISTACK_GATEWAY_API_KEY'),
                        'client-x-api-key' => env('OMNISTACK_GATEWAY_STAFFLUENT_X_API_KEY'),
                        'Content-Type' => 'application/json',
                    ])->post(rtrim(env('OMNISTACK_GATEWAY_BASEURL'), '/') . '/businesses/employee', $omniStackData);

                    // Process response
                    if ($response->successful()) {
                        $responseData = $response->json();

                        // Update local employee with OmniStack IDs
                        $employeeExternalIds = is_string($employee->external_ids) ? json_decode($employee->external_ids, true) : [];
                        if (!is_array($employeeExternalIds)) $employeeExternalIds = [];

                        $employeeExternalIds['omniStackEmployeeId'] = $responseData['employee']['_id'] ?? null;
                        $employee->external_ids = json_encode($employeeExternalIds);
                        $employee->save();

                        // If a user was created, update user's external_ids with omniStackGateway ID
                        if ($userCreated && isset($responseData['userId'])) {
                            $userExternalIds = is_string($userCreated->external_ids) ? json_decode($userCreated->external_ids, true) : [];
                            if (!is_array($userExternalIds)) $userExternalIds = [];

                            $userExternalIds['omniStackGateway'] = $responseData['userId'];
                            $userCreated->external_ids = json_encode($userExternalIds);
                            $userCreated->save();
                        }
                    } else {
                        // Log error but don't fail the transaction
                        \Log::error('Failed to create employee in OmniStack', [
                            'status' => $response->status(),
                            'response' => $response->json()
                        ]);
                    }
                } else {
                    \Log::warning('Could not call OmniStack Gateway: Missing adminUserId OmniStack ID', [
                        'venue_id' => $venue->id,
                        'venue_user_id' => $venue->user_id
                    ]);
                }

            } catch (\Exception $e) {
                // Log the error but don't fail the transaction
                \Log::error('Error calling OmniStack Gateway API: ' . $e->getMessage());
                \Sentry\captureException($e);
            }

            DB::commit();

            // Add the external_ids to response for debugging
            $employeeWithRelations = $employee->load('address', 'role', 'department');

            return response()->json($employeeWithRelations, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create employee: ' . $e->getMessage()], 500);
        }
    }

    public function deleteEmployee($id): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $employee = Employee::where('restaurant_id', $venue->id)->findOrFail($id);

            // TODO: Implement any necessary cleanup (e.g., removing from teams, reassigning tasks)

            $employee->delete();

            return response()->json(['message' => 'Employee deleted successfully'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Employee not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete employee: ' . $e->getMessage()], 500);
        }
    }

    public function updateEmployee(Request $request, $id): JsonResponse
    {
        // Authorize the venue
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $employee = Employee::with('address')
                ->where('restaurant_id', $venue->id)
                ->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('employees', 'email')->ignore($employee->id),
            ],
            'role_id' => 'sometimes|integer|exists:roles,id',
            'is_custom' => 'sometimes|boolean',
            'department_id' => 'sometimes|exists:departments,id',
            'hire_date' => 'sometimes|date',
            'personal_email' => 'sometimes|email',
            'personal_phone' => 'sometimes|string|max:20',
            'company_email' => 'sometimes|email',
            'company_phone' => 'sometimes|string|max:20',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'manager_id' => 'nullable|exists:employees,id',
            'address' => 'sometimes|array',
            'address.address_line1' => 'required_with:address|string|max:255',
            'address.city_id' => 'required_with:address|exists:cities,id',
            'address.state_id' => 'required_with:address|exists:states,id',
            'address.country_id' => 'required_with:address|exists:countries,id',
            'address.postal_code' => 'required_with:address|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $validatedData = $validator->validated();

            // Handle manager relationship
            if (isset($validatedData['manager_id'])) {
                $manager = Employee::where('id', $validatedData['manager_id'])
                    ->where('restaurant_id', $venue->id)
                    ->first();

                if (!$manager) {
                    return response()->json(['error' => 'Invalid manager selected'], 422);
                }
            }

            // Handle department verification
            if (isset($validatedData['department_id'])) {
                $department = Department::where('id', $validatedData['department_id'])
                    ->where('venue_id', $venue->id)
                    ->first();

                if (!$department) {
                    return response()->json(['error' => 'Invalid department selected'], 422);
                }
            }

            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                // Delete old picture if exists
                if ($employee->profile_picture) {
                    Storage::disk('s3')->delete($employee->profile_picture);
                }
                $path = Storage::disk('s3')->putFile('profile_pictures', $request->file('profile_picture'));
                $validatedData['profile_picture'] = $path;
            }

            // Handle address update
            if (isset($validatedData['address'])) {
                if ($employee->address) {
                    // Update existing address
                    $employee->address->update($validatedData['address']);
                } else {
                    // Create new address
                    $address = new Address($validatedData['address']);
                    $employee->address()->save($address);
                }
                // Remove address from validatedData since we've handled it separately
                unset($validatedData['address']);
            }

            // Update employee
            $employee->update($validatedData);

            DB::commit();

            // Reload employee with relationships
            $employee->load(['address', 'role', 'department', 'manager']);

            return response()->json($employee, 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to update employee',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Team Methods
    public function listTeams(): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $teams = Team::where('venue_id', $venue->id)->get();
        return response()->json($teams);
    }

    public function getTeam($id): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $team = Team::where('venue_id', $venue->id)->findOrFail($id);
        return response()->json($team);
    }

    public function createTeam(Request $request): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'required|exists:departments,id',
        ]);

        $team = Team::create(array_merge($validated, ['venue_id' => $venue->id]));
        return response()->json($team, 201);
    }

    public function updateTeam(Request $request, $id): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $team = Team::where('venue_id', $venue->id)->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'sometimes|required|exists:departments,id',
        ]);

        $team->update($validated);
        return response()->json($team);
    }

    /**
     * List all active countries
     */
    public function listCountries(): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $countries = Country::where('active', true)
                ->select('id', 'name', 'code')
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => $countries
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch countries: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List states by country
     */
    public function listStatesByCountry($countryId): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $states = State::where('country_id', $countryId)
                ->where('active', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => $states
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch states: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List cities by state
     */
    public function listCitiesByState($stateId): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $cities = City::where('states_id', $stateId)
                ->where('active', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => $cities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch cities: ' . $e->getMessage()
            ], 500);
        }
    }

    // Optional: Helper method to validate location exists
    private function validateLocation($countryId, $stateId, $cityId): array|JsonResponse
    {
        try {
            $country = Country::where('id', $countryId)
                ->where('active', true)
                ->firstOrFail();

            $state = State::where('id', $stateId)
                ->where('country_id', $countryId)
                ->where('active', true)
                ->firstOrFail();

            $city = City::where('id', $cityId)
                ->where('states_id', $stateId)
                ->where('active', true)
                ->firstOrFail();

            return [
                'country' => $country,
                'state' => $state,
                'city' => $city
            ];
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Invalid location data provided'
            ], 422);
        }
    }


}
