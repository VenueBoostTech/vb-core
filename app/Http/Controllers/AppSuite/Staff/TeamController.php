<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Team;
use App\Models\Department;
use App\Services\VenueService;
use App\Services\UserService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TeamController extends Controller
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

    public function listTeams(Request $request): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $perPage = $request->input('per_page', 15);
        $teams = Team::where('venue_id', $venue->id)
            ->with(['department', 'employees', 'departments'])
            ->paginate($perPage);

        return response()->json([
            'data' => $teams->items(),
            'current_page' => $teams->currentPage(),
            'per_page' => $teams->perPage(),
            'total' => $teams->total(),
            'total_pages' => $teams->lastPage(),
        ], 200);
    }

    public function getTeam($id): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $team = Team::where('venue_id', $venue->id)
                ->with(['department', 'teamLeader', 'operationsManager'])
                ->findOrFail($id);

            // Format employees using the same logic as getTeamEmployees
            $employees = $team->employees()
                ->select([
                    'employees.id',
                    'employees.name',
                    'employees.email',
                    'employees.company_phone',
                    'employees.profile_picture',
                    'employees.role_id',
                    'employees.custom_role',
                    'employees.status'
                ])
                ->with(['role:id,name'])
                ->withPivot('created_at')
                ->get()
                ->map(function ($employee) use ($venue) {
                    $profilePicture = $employee->profile_picture
                        ? Storage::disk('s3')->temporaryUrl($employee->profile_picture, now()->addMinutes(5))
                        : $this->getInitials($employee->name);

                    $roleName = null;
                    if ($employee->custom_role) {
                        $customRole = DB::table('custom_roles')
                            ->where('created_by_venue_id', $venue->id)
                            ->where('id', $employee->role_id)
                            ->first();
                        $roleName = $customRole?->name;
                    } else {
                        $roleName = $employee->role?->name;
                    }

                    return [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'phone' => $employee->company_phone,
                        'profile_picture' => $profilePicture,
                        'status' => $employee->status,
                        'role' => $roleName,
                        'joined_at' => $employee->pivot->created_at?->format('Y-m-d'),
                    ];
                });

            // Add formatted employees to team data
            $teamData = $team->toArray();
            $teamData['employees'] = $employees;

            return response()->json($teamData);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Team not found'], 404);
        }
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

        if (isset($validated['department_id'])) {
            $department = Department::where('id', $validated['department_id'])
                ->where('venue_id', $venue->id)
                ->first();

            if(!$department){
                return response()->json(['error' => 'Department not found'], 404);
            }
        }

        $team = Team::create(array_merge($validated, ['venue_id' => $venue->id]));

        return response()->json($team->load('department'), 201);
    }

    public function updateTeam(Request $request, $id): JsonResponse
    {

        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $team = Team::where('venue_id', $venue->id)->findOrFail($id);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Team not found'], 404);
        }


        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'sometimes|required|exists:departments,id',
        ]);



        if (isset($validated['department_id'])) {
            $department = Department::where('id', $validated['department_id'])
                ->where('venue_id', $venue->id)
                ->first();

            if(!$department){
                return response()->json(['error' => 'Department not found'], 404);
            }
        }

        $team->update($validated);

        return response()->json($team->load('department'));
    }
    public function deleteTeam($id): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $team = Team::where('venue_id', $venue->id)->findOrFail($id);
            $team->departments()->detach();
            $team->delete();

            return response()->json(['message' => 'Team deleted successfully'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Team not found'], 404);
        }
    }

    public function getTeamDepartments($id): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $team = Team::where('venue_id', $venue->id)->findOrFail($id);

            // Get primary department with stats
            $primaryDepartment = null;
            if ($team->department) {
                $primaryDepartment = array_merge($team->department->toArray(), [
                    'stats' => [
                        'employees_count' => $team->department->employees()->count(),
                        'teams_count' => $team->department->teams()->count(),
                        'projects_count' => $team->department->projects()->count(),
                        'cross_teams_count' => $team->department->crossTeams()->count(),
                    ]
                ]);
            }

            // Get additional departments with stats
            $additionalDepartments = $team->departments->map(function($department) {
                return array_merge($department->toArray(), [
                    'stats' => [
                        'employees_count' => $department->employees()->count(),
                        'teams_count' => $department->teams()->count(),
                        'projects_count' => $department->projects()->count(),
                        'cross_teams_count' => $department->crossTeams()->count(),
                    ]
                ]);
            });

            return response()->json([
                'primary_department' => $primaryDepartment,
                'additional_departments' => $additionalDepartments
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Team not found'], 404);
        }
    }


    public function updateTeamDepartments(Request $request, $id): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $team = Team::where('id', $id)->where('venue_id', $venue->id)->first();
            if (!$team) {
                return response()->json(['error' => 'Team not found'], 404);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Team not found'], 404);
        }

        // Manually validate the request data
        $validator = Validator::make($request->all(), [
            'department_id' => 'nullable|exists:departments,id',
            'additional_department_ids' => 'nullable|array',
            'additional_department_ids.*' => 'exists:departments,id',
        ]);

        // If validation fails, return a detailed response with the errors
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        try {
            if (isset($validated['department_id'])) {
                $primaryDepartment = Department::where('id', $validated['department_id'])
                    ->where('venue_id', $venue->id)
                    ->first();

                if (!$primaryDepartment) {
                    return response()->json(['error' => 'Department not found'], 404);
                }
                $team->department_id = $primaryDepartment->id;
                $team->save();
            }

            if (isset($validated['additional_department_ids'])) {
                $departments = Department::whereIn('id', $validated['additional_department_ids'])
                    ->where('venue_id', $venue->id)
                    ->get();

                // Check if the count matches the expected number
                if ($departments->count() !== count($validated['additional_department_ids'])) {
                    return response()->json(['error' => 'One or more departments do not belong to this venue.'], 422);
                }

                $team->departments()->sync($departments->pluck('id'));
            }

            DB::commit();
            return response()->json(['message' => 'Team departments updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }


    public function assignEmployeesToTeam(Request $request, $teamId): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $validated = $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        try {
            DB::beginTransaction();

            $team = Team::where('venue_id', $venue->id)->findOrFail($teamId);

            $employees = Employee::whereIn('id', $validated['employee_ids'])
                ->where('restaurant_id', $venue->id)
                ->get();

            if ($employees->count() !== count($validated['employee_ids'])) {
                throw new \Exception('One or more employees do not belong to this venue.');
            }

            $team->employees()->syncWithoutDetaching($employees->pluck('id'));

            DB::commit();

            return response()->json([
                'message' => 'Employees assigned to team successfully',
                'assigned_employees' => $employees->pluck('id'),
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Team not found'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function removeEmployeesFromTeam(Request $request, $teamId): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        $validated = $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        try {
            DB::beginTransaction();

            $team = Team::where('venue_id', $venue->id)->findOrFail($teamId);

            $employees = Employee::whereIn('id', $validated['employee_ids'])
                ->where('restaurant_id', $venue->id)
                ->get();

            if ($employees->count() !== count($validated['employee_ids'])) {
                throw new \Exception('One or more employees do not belong to this venue.');
            }

            $team->employees()->detach($employees->pluck('id'));

            DB::commit();

            return response()->json([
                'message' => 'Employees removed from team successfully',
                'removed_employees' => $employees->pluck('id'),
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Team not found'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function getTeamEmployees($teamId): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $team = Team::where('venue_id', $venue->id)->findOrFail($teamId);
            $employees = $team->employees()
                ->select([
                    'employees.id',
                    'employees.name',
                    'employees.email',
                    'employees.company_phone',
                    'employees.profile_picture',
                    'employees.role_id',
                    'employees.custom_role',
                    'employees.status'
                ])
                ->with(['role:id,name'])
                ->withPivot('created_at')  // Get the pivot table created_at
                ->get()
                ->map(function ($employee) use ($venue) {
                    // Handle profile picture or generate initials
                    $profilePicture = $employee->profile_picture
                        ? Storage::disk('s3')->temporaryUrl($employee->profile_picture, now()->addMinutes(5))
                        : $this->getInitials($employee->name);

                    // Handle role based on custom_role flag
                    $roleName = null;
                    if ($employee->custom_role) {
                        // For custom roles, check custom_roles table
                        $customRole = DB::table('custom_roles')
                            ->where('created_by_venue_id', $venue->id)
                            ->where('id', $employee->role_id)
                            ->first();
                        $roleName = $customRole?->name;
                    } else {
                        // For regular roles, use the role relationship
                        $roleName = $employee->role?->name;
                    }

                    return [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'phone' => $employee->company_phone,
                        'profile_picture' => $profilePicture,
                        'status' => $employee->status,
                        'role' => $roleName,
                        'joined_at' => $employee->pivot->created_at?->format('Y-m-d'),  // Format the pivot created_at
                    ];
                });

            return response()->json($employees);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Team not found'], 404);
        }
    }
    public function assignTeamLeader(Request $request, $id): JsonResponse
    {
        $venue = $this->authorizeOwner();
        if ($venue instanceof JsonResponse) return $venue;

        // Validate team existence
        $team = Team::where('venue_id', $venue->id)->find($id);
        if (!$team) {
            return response()->json(['error' => 'Team not found'], 404);
        }

        // Validate request data
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Fetch employee and check venue association
        $employee = Employee::where('restaurant_id', $venue->id)
            ->find($validator->validated()['employee_id']);

        if (!$employee) {
            return response()->json(['error' => 'Employee not found or does not belong to this venue'], 404);
        }

        // Check if employee has Team Leader role
        if (!$employee->role || $employee->role->name !== 'Team Leader') {
            return response()->json(['error' => 'Employee must have the Team Leader role to be assigned as team leader'], 422);
        }

        // Assign the team leader
        $team->team_leader_id = $employee->id;
        $team->save();

        return response()->json(['message' => 'Team leader assigned successfully']);
    }

    public function assignOperationsManager(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'team_ids' => 'required|array',
            'team_ids.*' => 'exists:teams,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee = Employee::with('role')->where('restaurant_id', $venue->id)
            ->where('id', $validator->validated()['employee_id'])
            ->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found or does not belong to this venue'], 404);
        }

        if (!$employee->role || $employee->role->name !== 'Operations Manager') {
            return response()->json(['error' => 'Employee must have the Operations Manager role to be assigned as operations manager'], 422);
        }

        $teams = Team::where('venue_id', $venue->id)
            ->whereIn('id', $validator->validated()['team_ids'])
            ->get();

        if ($teams->count() !== count($validator->validated()['team_ids'])) {
            return response()->json(['error' => 'One or more teams not found or do not belong to this venue'], 404);
        }

        DB::transaction(function () use ($employee, $teams) {
            foreach ($teams as $team) {
                $team->operations_manager_id = $employee->id;
                $team->save();
            }
        });

        return response()->json(['message' => 'Operations manager assigned successfully to the specified teams']);
    }

    /**
     * Helper function to generate initials from name
     */
    private function getInitials($name): string
    {
        $words = explode(' ', $name);
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return substr($initials, 0, 2);
    }

}
