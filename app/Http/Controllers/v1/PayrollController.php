<?php
namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Restaurant;
use App\Models\Schedule;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use function response;
use function url;
use function view;

/**
 * @OA\Info(
 *   title="Staff Management API",
 *   version="1.0",
 *   description="This API allows use Staff Management Related APIs for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Staff Management",
 *   description="Operations related to Staff Management"
 * )
 */


class PayrollController extends Controller
{


    /**
     * @OA\Get(
     *     path="/staff/payroll",
     *     summary="List payroll information for all venue employees",
     *     tags={"Staff Management"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(
     *                         property="employee_name",
     *                         type="string",
     *                         example="John Doe"
     *                     ),
     *                     @OA\Property(
     *                         property="salary",
     *                         type="integer",
     *                         example=50000
     *                     ),
     *                     @OA\Property(
     *                         property="bonus",
     *                         type="integer",
     *                         example=2000
     *                     ),
     *                     @OA\Property(
     *                         property="deductions",
     *                         type="integer",
     *                         example=1000
     *                     ),
     *                     @OA\Property(
     *                         property="taxes",
     *                         type="integer",
     *                         example=3000
     *                     ),
     *                     @OA\Property(
     *                         property="net_pay",
     *                         type="integer",
     *                         example=42000
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function index()
    {
        $payrolls = Payroll::all();

        if ($payrolls->isEmpty()) {
            return response()->json(['error' => 'No payroll information found'], 404);
        }

        $result = [];
        foreach ($payrolls as $payroll) {
            $employee = Employee::find($payroll->employee_id);
            $result[] = [
                'employee_name' => $employee->name,
                'employee_role' => $employee->role->name,
                'salary' => $employee->salary,
                'bonus' => $payroll->bonus,
                'deductions' => $payroll->deductions,
                'taxes' => $payroll->taxes,
                'net_pay' => $payroll->net_pay,
            ];
        }

        return response()->json($result);
    }

    /**
     * @OA\POST(
     *     path="/staff/payroll/generate-paycheck",
     *     summary="Generate paycheck for a specific employee",
     *     tags={"Staff Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Paycheck generated successfully"
     *             ),
     *             @OA\Property(
     *                 property="paycheck",
     *                 type="string",
     *                 example="http://localhost/paychecks/1.xls"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function generatePaycheck(Request $request)
    {
        $employeeId = $request->input('employee_id');
        $employee = Employee::find($employeeId);

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $payroll = $employee->payrolls()->first();

        if (!$payroll) {
            return response()->json(['error' => 'Payroll information not found for this employee'], 404);
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Paycheck for ' . $employee->name);
        $sheet->setCellValue('A2', 'Salary: ' . $employee->salary);
        $sheet->setCellValue('A3', 'Bonus: ' . $payroll->bonus);
        $sheet->setCellValue('A4', 'Deductions: ' . $payroll->deductions);
        $sheet->setCellValue('A5', 'Taxes: ' . $payroll->taxes);
        $sheet->setCellValue('A6', 'Net Pay: ' . $payroll->net_pay);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="paycheck_' . $employee->name . '.xls"');
        header('Cache-Control: max-age=0');


        $writer->save('php://output');
    }

    /**
     * @OA\Post(
     *     path="/staff/payroll/calculate",
     *     summary="Calculate payroll for an employee",
     *     tags={"Staff Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="employee_id",
     *                 type="integer"
     *             ),
     *             @OA\Property(
     *                 property="month",
     *                 type="string",
     *                 example="January"
     *             ),
     *             @OA\Property(
     *                 property="year",
     *                 type="integer",
     *                 example="2022"
     *             ),
     *             @OA\Property(
     *                 property="hours_worked",
     *                 type="float",
     *                 example="176"
     *             ),
     *             @OA\Property(
     *                 property="overtime_hours",
     *                 type="float",
     *                 example="20"
     *             ),
     *             @OA\Property(
     *                 property="bonus",
     *                 type="float",
     *                 example="1000"
     *             ),
     *             @OA\Property(
     *                 property="deductions",
     *                 type="float",
     *                 example="200"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *        @OA\Property(
     *         property="message",
     *         type="string",
     *         example="Payroll calculated successfully"
     *         ),
     *         @OA\Property(
     *        property="payroll",
     *         type="object",
     *         @OA\Property(
     *         property="employee_id",
     *         type="integer",
     *         example="1"
     *     ),
     *     @OA\Property(
     *     property="month",
     *     type="string",
     *     example="January"
     *    ),
     *     @OA\Property(
     *     property="year",
     *     type="integer",
     *     example="2022"
     *   ),
     *     @OA\Property(
     *     property="hours_worked",
     *     type="float",
     *     example="176"
     *  ),
     *     @OA\Property(
     *     property="overtime_hours",
     *     type="float",
     *     example="20"
     * ),
     *     @OA\Property(
     *     property="bonus",
     *     type="float",
     *     example="1000"
     * ),
     *     @OA\Property(
     *     property="deductions",
     *     type="float",
     *     example="200"
     * ),
     *     @OA\Property(
     *     property="taxes",
     *     type="float",
     *     example="200"
     * ),
     *     @OA\Property(
     *     property="net_pay",
     *     type="float",
     *     example="200"
     * ),
     *     @OA\Property(
     *     property="updated_at",
     *     type="string",
     *     example="2021-09-01T12:00:00.000000Z"
     * ),
     *     @OA\Property(
     *     property="created_at",
     *     type="string",
     *     example="2021-09-01T12:00:00.000000Z"
     * ),
     *     @OA\Property(
     *     property="id",
     *     type="integer",
     *     example="1"
     * )
     * )
     * )
     *    ),
     *     @OA\Response(
     *     response=404,
     *     description="Employee not found",
     *     ),
     *     @OA\Response(
     *     response=500,
     *     description="Internal Server Error"
     *    )
     * )
     */
    public function calculatePayroll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|numeric',
            'month' => 'required|string',
            'year' => 'required|numeric',
            'hours_worked' => 'required|numeric',
            'overtime_hours' => 'required|numeric',
            'bonus' => 'required|numeric',
            'deductions' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

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

        $employee = Employee::find($request->employee_id);

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        if ($employee->isOwner()) {
            $employeeRestaurantId = $employee->restaurant_id;
        } elseif ($employee->isManager()) {
            $owner = $employee->owner;
            if (!$owner) {
                return response()->json(['error' => 'Owner not found'], 404);
            }
            $employeeRestaurantId = $owner->restaurant_id;
        } else {
            $manager = $employee->manager;
            if (!$manager) {
                return response()->json(['error' => 'Manager not found'], 404);
            }
            $owner = $manager->owner;
            if (!$owner) {
                return response()->json(['error' => 'Owner not found'], 404);
            }
            $employeeRestaurantId = $owner->restaurant_id;
        }

        $employeeRestaurant = Restaurant::find($employeeRestaurantId);

        if (!$employeeRestaurant || $employeeRestaurant->id != $venue->id) {
            return response()->json(['error' => 'Restaurant not found'], 404);
        }

        $payroll = new Payroll();
        $payroll->employee_id = $employee->id;
        $payroll->month = $request->input('month');
        $payroll->year = $request->input('year');

        $payroll->hours_worked = $request->input('hours_worked');
        $payroll->overtime_hours = $request->input('overtime_hours');
        $payroll->bonus = $request->input('bonus');
        $payroll->deductions = $request->input('deductions');

        $salary = $employee->salary;
        switch ($employee->salary_frequency) {
            case 'daily':
                $salary *= $payroll->hours_worked / 8;
                break;
            case 'weekly':
                $salary *= $payroll->hours_worked / 40;
                break;
            case 'bi-weekly':
                $salary *= $payroll->hours_worked / 80;
                break;
            case 'monthly':
                break;
            default:
                return response()->json(['error' => 'Invalid salary frequency'], 400);
        }

        // calculate salary
        $payroll->salary = $salary;
        $overtime_rate = $salary / $payroll->hours_worked;
        $payroll->salary += $overtime_rate * $payroll->overtime_hours;

        $payroll->taxes = $payroll->salary * 0.1;
        $payroll->net_pay = $payroll->salary + $payroll->bonus - $payroll->deductions - $payroll->taxes;
        $payroll->restaurant_id = $venue->id;

        $payroll->save();

        return response()->json([
            'message' => 'Payroll calculated successfully',
            'payroll' => $payroll
        ], 200);
    }


    /**
     * @OA\Get(
     *     path="/staff/reports/generate",
     *     summary="Generate staff report",
     *     tags={"Staff Management"},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Report generated successfully"
     *             ),
     *             @OA\Property(
     *                 property="report_url",
     *                 type="string",
     *                 example="http://localhost/reports/staff_report.pdf"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function generateReport(Request $request)
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
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $employees = Employee::where('restaurant_id', $venue->id)
            ->orWhereHas('owner', function ($query) use ($venue) {
                $query->where('restaurant_id', $venue->id);
            })
            ->orWhereHas('manager.owner', function ($query) use ($venue) {
                $query->where('restaurant_id', $venue->id);
            })
            ->whereBetween('hire_date', [$startDate, $endDate])->get();

        $dompdf = new Dompdf();
        $dompdf->loadHtml(view('staff_report', ['employees' => $employees, 'start_date' => $startDate, 'end_date' => $endDate]));
        $dompdf->render();
        $pdf_data = $dompdf->output();

        $fileName = 'staff_report_'. time() .'.pdf';

        file_put_contents(storage_path('app/public/reports/'. $fileName), $pdf_data);

        Storage::put('app/public/reports/'. $fileName, $pdf_data);

        // TODO: after v1 testing store the reports in s3
        return response()->json([
            'message' => 'Report generated successfully',
            // 'report_url' => url('storage/reports/'. $fileName)
        ], 200);
    }


    /**
     * @OA\Post(
     *     path="/staff/calculate-overtime",
     *     tags={"Staff Management"},
     *     summary="Calculate overtime pay for an employee",
     *     description="Calculates the overtime pay for an employee based on their regular hours and additional hours worked, and saves the result in the employee's overtime_pay field.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee"),
     *             @OA\Property(property="additional_hours", type="integer", description="Additional hours worked by the employee")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="overtime_pay", type="number", description="Overtime pay for the employee")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", description="Error message")
     *         )
     *     )
     * )
     */
    public function calculateOvertime(Request $request)
    {
        $employee = Employee::find($request->input('employee_id'));
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $regularHours = $employee->regular_hours;
        $additionalHours = $request->input('additional_hours');
        $overtimePay = ($additionalHours - $regularHours) * $employee->overtime_rate;

        $payroll = Payroll::where('employee_id', $employee->id)->first();
        $payroll->overtime_pay = $overtimePay;
        // save the overtime pay to the payroll table
        $payroll->save();
        return response()->json(['overtime_pay' => $overtimePay]);
    }

    /**
     * @OA\Post(
     *   path="/staff/approve-time-off",
     *   tags={"Staff Management"},
     *   summary="Approve time off request",
     *   operationId="approveTimeOff",
     *   @OA\RequestBody(
     *      required=true,
     *      @OA\JsonContent(
     *     @OA\Property(
     *     property="employee_id",
     *     type="integer",
     *     example=1
     *     ),
     *     @OA\Property(
     *     property="schedule_id",
     *     type="integer",
     *     example=1
     *     )
     *     )
     *   ),
     *  @OA\Response(
     *     response=200,
     *       description="Successful operation",
     * @OA\JsonContent(
     *     @OA\Property(
     *     property="message",
     *     type="string",
     *     example="Time off request approved"
     *    ),
     * )
     * ),
     * @OA\Response(
     * response=404,
     * description="Employee or Schedule not found",
     * )
     * )
     */
    public function approveTimeOff(Request $request)
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
            'approved' => 'required|in:1,2',
            'employee_id' => 'required|integer|exists:employees,id',
            'schedule_id' => 'required|integer|exists:schedules,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Get the employee
        $employee = Employee::find($request->input('employee_id'));

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
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

        // Check if the user is authorized to approve the time-off request for the employee
        $user = Employee::where('user_id', auth()->user()->id)->first();
        if (!($user->role_id === 2)  || ($user->role_id === 1 && $employee->manager_id !== $user->id)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get the schedule for the employee
        $schedule = Schedule::where('employee_id', $employee->id)->where('id', $request->input('schedule_id'))->first();

        if (!$schedule) {
            return response()->json(['error' => 'Schedule not found'], 404);
        }

        // Approve the time off request
        $schedule->status = $request->input('approved') === "1" ? 'approved' : 'declined';
        $schedule->time_off_request = $request->input('approved') === "1" ? 'approved' : 'declined';
        $schedule->save();

        return response()->json([
            'message' => 'Time off request approved',
            'schedule' => $schedule,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/staff/view-schedule-conflicts",
     *     summary="View schedule conflicts for an employee",
     *     operationId="viewScheduleConflicts",
     *     tags={"Staff Management"},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="ID of the employee to view schedule conflicts for",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Schedule conflicts"
     *             ),
     *             @OA\Property(
     *                 property="schedule_conflicts",
     *                 type="array",
     *                 @OA\Items(
     *                      @OA\Property(
     *                          property="id",
     *                          type="integer"
     *                      ),
     *                      @OA\Property(
     *                          property="employee_id",
     *                          type="integer"
     *                      ),
     *                      @OA\Property(
     *                          property="date",
     *                          type="string"
     *                      ),
     *                      @OA\Property(
     *                          property="start_time",
     *                          type="string"
     *                      ),
     *                      @OA\Property(
     *                          property="end_time",
     *                          type="string"
     *                      ),
     *                      @OA\Property(
     *                          property="status",
     *                          type="string"
     *                      )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Employee not found"
     *             )
     *         )
     *     )
     * )
     */
    public function viewScheduleConflicts(Request $request): \Illuminate\Http\JsonResponse
    {
        // TODO: after v1 testing maybe improve schedule conflict detection
        // TODO: after v1 testing ask chatgpt for more details on how to detect schedule conflicts

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

        // Get the employee
        $employee = Employee::find($request->input('employee_id'));

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
        $user = Employee::where('user_id', auth()->user()->id)->first();
        if (!($user->role_id === 2)  || ($user->role_id === 1 && $employee->manager_id !== $user->id)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }


        // Get all schedules for the employee
        $schedules = Schedule::where('employee_id', $employee->id)->get();

        // Initialize an array to store schedule conflicts
        $scheduleConflicts = [];

        // Loop through each schedule to check for conflicts
        foreach ($schedules as $schedule) {
            // Compare the start and end times of the current schedule to the other schedules
            foreach ($schedules as $compareSchedule) {
                if ($schedule->id != $compareSchedule->id) {
                    // Check if the start time or end time of the current schedule falls within the start and end time of the compared schedule
                    if (($schedule->start_time >= $compareSchedule->start_time && $schedule->start_time <= $compareSchedule->end_time) || ($schedule->end_time >= $compareSchedule->start_time && $schedule->end_time <= $compareSchedule->end_time)) {
                        // Add the conflicting schedule to the array
                        $scheduleConflicts[] = $compareSchedule;
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Schedule conflicts',
            'schedule_conflicts' => $scheduleConflicts,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/staff/schedules",
     *     tags={"Staff Management"},
     *     summary="Create a new schedule for an employee",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *              @OA\Property(property="employee_id", type="integer", example=1),
     *              @OA\Property(property="date", type="string", example="08:00:00"),
     *              @OA\Property(property="start_time", type="string", example="08:00:00"),
     *              @OA\Property(property="end_time", type="string", example="16:00:00"),
     *        )
     *   ),
     *     @OA\Response(
     *         response=201,
     *         description="Schedule created successfully",
     *         @OA\JsonContent(
     *          @OA\Property(
     *              property="schedule",
     *              type="object",
     *              @OA\Property(property="id", type="integer", example=1),
     *              @OA\Property(property="employee_id", type="integer", example=1),
     *              @OA\Property(property="date", type="string", example="2020-01-01"),
     *              @OA\Property(property="start_time", type="string", example="08:00:00"),
     *              @OA\Property(property="end_time", type="string", example="16:00:00"),
     *              @OA\Property(property="status", type="string", example="pending"),
     *              @OA\Property(property="created_at", type="string", example="2020-01-01 00:00:00"),
     *              @OA\Property(property="updated_at", type="string", example="2020-01-01 00:00:00"),
     *         )
     *       )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request",
     *     ),
     * )
     */
    public function createSchedule(Request $request)
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

        $employee = Employee::find($request->employee_id);
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
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
        $user = Employee::where('user_id', auth()->user()->id)->first();
        if (!($user->role_id === 2)  || ($user->role_id === 1 && $employee->manager_id !== $user->id)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|numeric',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $schedule = Schedule::create([
            'employee_id' => $employee->id,
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'status' => 'pending',
            'restaurant_id' => $venue->id,
        ]);

        return response()->json(['message' => 'Schedule created successfully', 'schedule' => $schedule], 201);
    }

    /**
     * @OA\Post(
     *     path="/staff/view-schedule-to-requests",
     *     summary="View schedule time off requests for an employee",
     *     operationId="viewScheduleTimeOff(TO)Requests",
     *     tags={"Staff Management"},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="ID of the employee to view schedule time-off requests for",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Schedule (TO) Requests retrieved successfully"
     *             ),
     *             @OA\Property(
     *                 property="schedule_to_requests",
     *                 type="array",
     *                 @OA\Items(
     *                      @OA\Property(
     *                          property="id",
     *                          type="integer"
     *                      ),
     *                      @OA\Property(
     *                          property="employee_id",
     *                          type="integer"
     *                      ),
     *                      @OA\Property(
     *                          property="date",
     *                          type="string"
     *                      ),
     *                      @OA\Property(
     *                          property="start_time",
     *                          type="string"
     *                      ),
     *                      @OA\Property(
     *                          property="end_time",
     *                          type="string"
     *                      ),
     *                      @OA\Property(
     *                          property="status",
     *                          type="string"
     *                      )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Employee not found"
     *             )
     *         )
     *     )
     * )
     */
    public function viewScheduleTORequests(Request $request)
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

        // Get the employee
        $employee = Employee::find($request->input('employee_id'));

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
        $user = Employee::where('user_id', auth()->user()->id)->first();
        if (!($user->role_id === 2)  || ($user->role_id === 1 && $employee->manager_id !== $user->id)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get all schedule time-off requests for the employee
        $schedules = Schedule::where('employee_id', $employee->id)->get();

        return response()->json([
            'message' => 'Schedule (TO) Requests retrieved successfully',
            'schedule_time_off' => $schedules,
        ]);
    }

     /**
      * @OA\Get (
      *     path="/staff/view-all-schedule-to-requests",
      *     summary="View all schedule time off requests for a restaurant",
      *     operationId="viewAllScheduleTimeOff(TO)Requests",
      *     tags={"Staff Management"},
      *     @OA\Response(
      *     response=200,
      *     description="Successful operation",
      *     @OA\JsonContent(
      *     @OA\Property(
      *     property="message",
      *     type="string",
      *     example="Schedule (TO) Requests retrieved successfully"
      *    ),
      *     @OA\Property(
      *     property="schedule_to_requests",
      *     type="array",
      *     @OA\Items(
      *     @OA\Property(
      *     property="id",
      *     type="integer"
      *   ),
      *     @OA\Property(
      *     property="employee_id",
      *     type="integer"
      *  ),
      *     @OA\Property(
      *     property="date",
      *     type="string"
      * ),
      *     @OA\Property(
      *     property="start_time",
      *     type="string"
      * ),
      *     @OA\Property(
      *     property="end_time",
      *     type="string"
      * ),
      *     @OA\Property(
      *     property="status",
      *     type="string"
      * )
      * )
      * )
      * )
      * ),
      *     @OA\Response(
      *     response=404,
      *     description="Restaurant not found",
      *     @OA\JsonContent(
      *     @OA\Property(
      *     property="message",
      *     type="string",
      *     example="Restaurant not found"
      * )
      * )
      * )
      * )
      */
    public function getAllScheduleTORequests()
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

        // Get all schedule time-off requests for the restaurant
        $schedules = Schedule::where('restaurant_id', $venue->id)->with('employee')->get();

        return response()->json([
            'message' => 'Schedule (TO) Requests retrieved successfully',
            'schedule_time_off' => $schedules,
        ]);
    }

}

