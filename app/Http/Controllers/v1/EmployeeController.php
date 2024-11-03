<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalaryHistory;
use App\Models\Role;
use App\Models\User;
use App\Models\VenueType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use function response;

/**
 * @OA\Info(
 *   title="Staff Management API",
 *   version="1.0",
 *   description="This API allows use Staff Management Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Staff Management",
 *   description="Operations related to Staff Management"
 * )
 */
class EmployeeController extends Controller
{
    /**
     * @OA\Get(
     *      path="/staff/employees",
     *      operationId="listEmployees",
     *      tags={"Staff Management"},
     *      summary="List all employees and their relationships",
     *      description="Returns a list of all employees and their relationships with managers and owners",
     *      @OA\Response(response=200, description="successful operation"),
     *      @OA\Response(response=401, description="unauthorized"),
     *      @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $employees = Employee::where('restaurant_id', $venue->id)
            ->orWhereHas('owner', function ($query) use ($venue) {
                $query->where('restaurant_id', $venue->id);
            })
            ->orWhereHas('manager.owner', function ($query) use ($venue) {
                $query->where('restaurant_id', $venue->id);
            })
            ->get();


        foreach ($employees as $employee) {
            if ($employee->role_id == 2 || $employee->role_id == 5 || $employee->role_id == 13) { // owner
                $employee->load(['employees', 'manager' => function ($query) {
                    $query->with('employees');
                }]);
            } elseif ($employee->role_id == 1 || $employee->role_id == 6 || $employee->role_id == 9 || $employee->role_id == 14) { // manager
                $employee->load(['employees', 'owner']);
            } else { // waiter or cook
                $employee->load('manager');
            }
        }

        return response()->json(['data' => $employees], 200);
    }


    public function getHousekeepingStaff(): \Illuminate\Http\JsonResponse
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $venueType = VenueType::where('id', $venue->venue_type)->first();

        $housekeepingRoleId = null;
        if ($venueType->name === 'Hotel') {
            // Get the role ID for "Housekeeping Staff Hotel"
            $housekeepingRoleId = Role::where('name', 'Housekeeping Staff Hotel')->first();
        }

        if ($venueType->short_name === 'vacation_rental') {
            // Get the role ID for "Housekeeping staff"
            $housekeepingRoleId = Role::where('name', 'Housekeeping staff')->first();
        }

        if (!$housekeepingRoleId) {
            return response()->json(['error' => 'Role "Housekeeping staff" not found'], 404);
        }

        $employees = Employee::where(function ($query) use ($venue, $housekeepingRoleId) {
            $query->where('restaurant_id', $venue->id)
                ->orWhereHas('owner', function ($query) use ($venue) {
                    $query->where('restaurant_id', $venue->id);
                })
                ->orWhereHas('manager.owner', function ($query) use ($venue) {
                    $query->where('restaurant_id', $venue->id);
                });
        })
            ->where('role_id', $housekeepingRoleId->id)
            ->get();

        return response()->json(['data' => $employees], 200);
    }

    /**
     * @OA\Post(
     *     path="/staff/employees",
     *     summary="Create a new employee",
     *     description="Creates a new employee with the given details",
     *     tags={"Staff Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"name", "email", "role_id", "salary", "salary_frequency"},
     *             properties={
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="johndoe@example.com"),
     *                 @OA\Property(property="role_id", type="integer", example=3),
     *                 @OA\Property(property="salary", type="number", format="decimal", example=2000.00),
     *                 @OA\Property(property="hire_date", type="string"),
     *                 @OA\Property(property="salary_frequency", type="string", enum={"daily", "weekly", "bi-weekly", "monthly"}, example="monthly"),
     *                 @OA\Property(property="manager_id", type="integer", example=2),
     *                 @OA\Property(property="owner_id", type="integer", example=1),
     *                 @OA\Property(property="currency_id", type="integer", example=1)
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Employee created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Employee created successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid request data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="The given data was invalid."
     *             ),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "email": {"The email has already been taken."},
     *                     "salary_frequency": {"The selected salary frequency is invalid."},
     *                     "manager_id": {"The selected manager id is invalid."},
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:employees,email',
            'role_id' => 'required|exists:roles,id',
            'salary' => 'required|numeric',
            'hire_date' => 'date_format:Y-m-d',
            'salary_frequency' => 'required|in:daily,weekly,bi-weekly,monthly,annual,custom,hourly',
            'frequency_number' => 'required_if:salary_frequency,custom|min:1',
            'frequency_unit' => 'required_if:salary_frequency,custom|in:days,weeks,months,years,hours',
            'custom_role' => 'nullable|string|max:255',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'manager_id' => (
                $request->input('role_id') == 3 ||
                $request->input('role_id') == 4 ||
                $request->input('role_id') == 7 ||
                $request->input('role_id') == 8 ||
                $request->input('role_id') == 10 ||
                $request->input('role_id') == 12 ||
                $request->input('role_id') == 11 ||
                $request->input('role_id') == 15 ||
                $request->input('role_id') == 16 ||
                $request->input('role_id') == 17
            ) ? 'required|exists:employees,id' : 'nullable',
//            'employee_password' => (
//                $request->input('role_id') == 3 ||
//                $request->input('role_id') == 4 ||
//                $request->input('role_id') == 7 ||
//                $request->input('role_id') == 8 ||
//                $request->input('role_id') == 10 ||
//                $request->input('role_id') == 12 ||
//                $request->input('role_id') == 11 ||
//                $request->input('role_id') == 15 ||
//                $request->input('role_id') == 16 ||
//                $request->input('role_id') == 17
//            )  ? 'nullable' : 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $employee = new Employee();

        if ($request->input('salary_frequency') === 'custom') {
            $employee->frequency_number = $request->input('frequency_number');
            $employee->frequency_unit = $request->input('frequency_unit');
        } else {
            $employee->frequency_number = null;
            $employee->frequency_unit = null;
        }

        // check if custom role is set and then set the custom_role string
        if ($request->input('custom_role')) {
            $employee->custom_role = $request->input('custom_role');
        }

        $employee->name = $request->input('name');
        $employee->email = $request->input('email');
        $employee->role_id = $request->input('role_id');
        $employee->hire_date = $request->input('hire_date') ?? null;
        $employee->manager_id =
            (
                $request->input('role_id') == 3 ||
                $request->input('role_id') == 4 ||
                $request->input('role_id') == 7 ||
                $request->input('role_id') == 8 ||
                $request->input('role_id') == 10 ||
                $request->input('role_id') == 12 ||
                $request->input('role_id') == 11 ||
                $request->input('role_id') == 15 ||
                $request->input('role_id') == 16 ||
                $request->input('role_id') == 17
            ) ? $request->input('manager_id') : null;

        // no owner id for non manager
        $employee->owner_id = (
            $request->input('role_id') == 3 ||
            $request->input('role_id') == 4 ||
            $request->input('role_id') == 7 ||
            $request->input('role_id') == 8 ||
            $request->input('role_id') == 10 ||
            $request->input('role_id') == 12 ||
            $request->input('role_id') == 11 ||
            $request->input('role_id') == 15 ||
            $request->input('role_id') == 16 ||
            $request->input('role_id') == 17
        ) ? null : $request->input('owner_id');
        $employee->salary = $request->input('salary');
        $employee->salary_frequency = $request->input('salary_frequency');
        $employee->restaurant_id = $venue->id;
        if ($request->hasFile('profile_picture')) {
            $path = Storage::disk('s3')->putFile('profile_pictures', $request->file('profile_picture'));
            $employee->profile_picture = $path;
        }
        $employee->save();

        // TODO: maybe we need this logic for multiple brands
//        if ($request->input('role_id')  === 1 || $request->input('role_id')  === 6 || $request->input('role_id')  === 9 || $request->input('role_id')  === 14 ) {
//
//            $userCreated = User::create([
//                'name' => $employee->name,
//                'email' => $employee->email,
//                'password' => Hash::make($request->input('employee_password')),
//                'country_code' => 'US',
//            ]);
//
//            $employee->user_id = $userCreated->id;
//            $employee->save();
//        }

        return response()->json(['employee' => $employee, 'message' => 'Employee created successfully'], 201);
    }

    /**
     * @OA\Put(
     *     path="/staff/employees/{id}",
     *     summary="Update an employee",
     *     description="Update an employee record",
     *     tags={"Staff Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of employee to update",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"name", "email", "salary", "salary_frequency"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="salary", type="number"),
     *             @OA\Property(property="salary_frequency", type="string", enum={"daily", "weekly", "bi-weekly", "monthly"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="employee", type="object",
     *                 @OA\Property(property="id", type="integer", format="int64"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="hire_date", type="string"),
     *                 @OA\Property(property="role_id", type="integer", format="int64"),
     *                 @OA\Property(property="manager_id", type="integer", format="int64", nullable=true),
     *                 @OA\Property(property="owner_id", type="integer", format="int64", nullable=true),
     *                 @OA\Property(property="salary", type="number", format="float"),
     *                 @OA\Property(property="salary_frequency", type="string", enum={"daily", "weekly", "bi-weekly", "monthly"}),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $employee = Employee::findOrFail($id);

        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        if (!$employee->restaurant_id) {
            // If employee has no restaurant, check manager and owner's restaurant IDs
            $manager = $employee->manager;
            $owner = $employee->owner;

            if (!$owner && ($manager && $manager->owner->restaurant_id != $venue->id)) {
                abort(403, 'Unauthorized action.');
            }
            if (!$manager && ($owner && $owner->restaurant_id != $venue->id)) {
                abort(403, 'Unauthorized action.');
            }
        } else {

            // If employee has a restaurant, check if it matches the API caller's restaurant ID
            if ($employee->restaurant_id != $venue->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        // Check if the user is authorized to edit the employee
//        $user = Employee::where('user_id', auth()->user()->id)->first();
//        if (!($user->role_id === 2)  || ($user->role_id === 1 && $employee->manager_id !== $user->id)) {
//            return response()->json(['error' => 'Unauthorized'], 401);
//        }

        $validator = Validator::make($request->all(), [
            'email' => ['sometimes', 'required', 'email', Rule::unique('employees')->ignore($employee->id)],
            'salary' => ['required', 'numeric'],
            'salary_frequency' => ['required', Rule::in(['daily', 'weekly', 'bi-weekly', 'monthly', 'annual', 'custom', 'hourly'])],
            'frequency_number' => 'required_if:salary_frequency,custom|min:1',
            'frequency_unit' => 'required_if:salary_frequency,custom|in:days,weeks,months,years,hours',
            'custom_role' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        // Save previous salary and salary frequency in history table if changed
        if ($employee->salary != $request->input('salary') || $employee->salary_frequency != $request->input('salary_frequency')) {
            $history = new EmployeeSalaryHistory([
                'employee_id' => $employee->id,
                'salary' => $employee->salary,
                'salary_frequency' => $employee->salary_frequency
            ]);
            $history->save();
        }

        $employee->update($request->all());

        return response()->json(['message' => 'Employee updated successfully', 'employee' => $employee], 200);
    }

    /**
     * @OA\Get(
     *     path="/staff/employees/{id}",
     *     summary="Get an employee with their related records",
     *     tags={"Staff Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the employee",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns the employee data and related records",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 description="Employee data",
     *             ),
     *             @OA\Property(
     *                 property="salary_histories",
     *                 description="Salary history records of the employee",
     *                 type="array",
     *                 @OA\Items(
     *                type="object",
     * )
     *             ),
     *             @OA\Property(
     *                 property="performances",
     *                 description="Performance records of the employee",
     *                 type="array",
     *                 @OA\Items(
     *           type="object",
     * )
     *             ),
     *             @OA\Property(
     *                 property="expenses",
     *                 description="Expense records of the employee",
     *                 type="array",
     *                 @OA\Items(
     *      type="object",
     * )
     *             ),
     *             @OA\Property(
     *                 property="payrolls",
     *                 description="Payroll records of the employee",
     *                 type="array",
     *                 @OA\Items(
     *
     * )
     *             ),
     *             @OA\Property(
     *                 property="role",
     *                 description="Role of the employee",
     *             ),
     *             @OA\Property(
     *                 property="manager",
     *                 description="Manager of the employee",
     *             ),
     *             @OA\Property(
     *                 property="owner",
     *                 description="Owner of the employee",
     *             ),
     *             @OA\Property(
     *                 property="owner_employees",
     *                 description="Employees of the owner of the employee",
     *                 type="array",
     *                 @OA\Items(
     *     type="object",
     * )
     *             ),
     *             @OA\Property(
     *                 property="employees",
     *                 description="Employees who report to the employee",
     *                 type="array",
     *                 @OA\Items(
     *     type="object",
     * )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Employee not found"
     *     )
     * )
     */
    public function show($id)
    {

        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $employee = Employee::with(
            'salaryHistories',
            'performances',
            'expenses',
            'payrolls',
            'role',
            'manager',
            'owner',
            'ownerEmployees',
            'employees'
        )->findOrFail($id);

        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        if (!$employee->restaurant_id) {
            // If employee has no restaurant, check manager and owner's restaurant IDs
            $manager = $employee->manager;
            $owner = $employee->owner;

            if (!$owner && ($manager && $manager->owner->restaurant_id != $venue->id)) {
                abort(403, 'Unauthorized action.');
            }
            if (!$manager && ($owner && $owner->restaurant_id != $venue->id)) {
                abort(403, 'Unauthorized action.');
            }
        } else {

            // If employee has a restaurant, check if it matches the API caller's restaurant ID
            if ($employee->restaurant_id != $venue->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        return response()->json(
            [
                'data' => $employee,
                'salary_histories' => $employee->salaryHistory,
                'performances' => $employee->performances,
                'expenses' => $employee->expenses,
                'payrolls' => $employee->payrolls,
                'role' => $employee->role,
                'manager' => $employee->manager,
                'owner' => $employee->owner,
                'owner_employees' => $employee->ownerEmployees,
                'employees' => $employee->employees
            ]);
    }
}
