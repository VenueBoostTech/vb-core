<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppProject;
use App\Models\AppProjectTimesheet;
use App\Models\Restaurant;
use App\Models\StaffActivity;
use App\Models\TimesheetBreak;
use App\Services\ActivityTrackingService;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmployeeTimesheetController extends Controller
{
    protected VenueService $venueService;
    protected ActivityTrackingService $activityService;

    public function __construct(
        VenueService $venueService,
        ActivityTrackingService $activityService
    ) {
        $this->venueService = $venueService;
        $this->activityService = $activityService;
    }

    public function clockIn(Request $request, $projectId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }


        $project = AppProject::where('venue_id', $venue->id)->find($projectId);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        // Check if employee already has an active timesheet
        $activeTimesheet = AppProjectTimesheet::where('employee_id', $authEmployee->id)
            ->where('status', 'active')
            ->first();

        if ($activeTimesheet) {
            return response()->json(['error' => 'You already have an active clock-in session'], 400);
        }

        $validator = Validator::make($request->all(), [
            'task_id' => 'nullable|exists:app_project_tasks,id',
            'work_description' => 'nullable|string',
            'location' => 'required|array',
            'location.latitude' => 'required|numeric',
            'location.longitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $timesheet = new AppProjectTimesheet([
            'app_project_id' => $projectId,
            'task_id' => $request->task_id,
            'employee_id' => $authEmployee->id,
            'venue_id' => $venue->id,
            'clock_in_time' => now(),
            'work_description' => $request->work_description,
            'location_data' => $request->location,
            'status' => 'active'
        ]);

        $timesheet->save();

        // Track clock in activity
        $this->activityService->trackTimesheetClockIn($authEmployee, $timesheet);


        return response()->json([
            'message' => 'Successfully clocked in',
            'timesheet' => $timesheet
        ], 201);
    }

    public function clockOut(Request $request, $projectId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // Find active timesheet
        $timesheet = AppProjectTimesheet::where('app_project_id', $projectId)
            ->where('employee_id', $authEmployee->id)
            ->where('status', 'active')
            ->first();

        if (!$timesheet) {
            return response()->json(['error' => 'No active clock-in session found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'location' => 'required|array',
            'location.latitude' => 'required|numeric',
            'location.longitude' => 'required|numeric',
            'work_description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $timesheet->clock_out_time = now();
        $timesheet->status = 'completed';

        $timesheet->location_data = [
            'clock_in' => $timesheet->location_data,
            'clock_out' => $request->location
        ];

        $timesheet->total_hours = Carbon::parse($timesheet->clock_in_time)
                ->diffInMinutes(Carbon::parse($timesheet->clock_out_time)) / 60;

        if ($request->work_description) {
            $timesheet->work_description = $timesheet->work_description
                ? $timesheet->work_description . "\n\nClock-out notes: " . $request->work_description
                : $request->work_description;
        }

        $timesheet->save();


        // Track clock out activity
        $this->activityService->trackTimesheetClockOut($authEmployee, $timesheet);


        return response()->json([
            'message' => 'Successfully clocked out',
            'timesheet' => $timesheet
        ]);
    }

    public function getMyTimesheets(Request $request, $projectId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $perPage = $request->input('per_page', 15);
        $query = AppProjectTimesheet::where('app_project_id', $projectId)
            ->where('venue_id', $venue->id)
            ->where('employee_id', $authEmployee->id)
            ->with(['task:id,name'])
            ->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $timesheets = $query->paginate($perPage);
        // Track timesheet view activity
        $this->activityService->trackTimesheetView($authEmployee, null);


        return response()->json([
            'timesheets' => $timesheets->items(),
            'current_page' => $timesheets->currentPage(),
            'per_page' => $timesheets->perPage(),
            'total' => $timesheets->total(),
            'total_pages' => $timesheets->lastPage(),
        ]);
    }

    public function getCurrentSession(Request $request): JsonResponse
    {

        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $activeTimesheet = AppProjectTimesheet::where('employee_id', $authEmployee->id)
            ->where('status', 'active')
            ->with(['project:id,name', 'task:id,name'])
            ->first();

        if ($activeTimesheet) {
            // Track current session view
            $this->activityService->trackTimesheetView(
                $authEmployee,
                $activeTimesheet
            );
        }

        return response()->json([
            'has_active_session' => !!$activeTimesheet,
            'active_session' => $activeTimesheet
        ]);
    }


    public function getMyBreaks(Request $request, $projectId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // Get the timesheet for today
        $timesheet = AppProjectTimesheet::where('app_project_id', $projectId)
            ->where('employee_id', $authEmployee->id)
            ->where('venue_id', $venue->id)
            ->where('status', 'active')
            ->first();

        if (!$timesheet) {
            return response()->json(['error' => 'No active timesheet found'], 404);
        }

        $breaks = TimesheetBreak::where('timesheet_id', $timesheet->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalBreakMinutes = $breaks->sum(function($break) {
            if (!$break->break_end) return 0;
            return Carbon::parse($break->break_start)
                ->diffInMinutes($break->break_end);
        });

        // Track breaks view activity
        $this->activityService->track(
            $authEmployee,
            StaffActivity::TYPE_BREAKS_VIEW,
            $timesheet,
            [
                'total_breaks' => $breaks->count(),
                'total_break_minutes' => $totalBreakMinutes,
                'active_break' => $breaks->where('break_end', null)->count() > 0,
                'meal_breaks_taken' => $breaks->where('break_type', 'meal')->count(),
                'rest_breaks_taken' => $breaks->where('break_type', 'rest')->count(),
            ]
        );

        return response()->json([
            'breaks' => $breaks,
            'summary' => [
                'total_breaks' => $breaks->count(),
                'total_break_minutes' => $totalBreakMinutes,
                'active_break' => $breaks->where('break_end', null)->first(),
                'meal_breaks_taken' => $breaks->where('break_type', 'meal')->count(),
                'rest_breaks_taken' => $breaks->where('break_type', 'rest')->count(),
            ]
        ]);
    }

    public function startBreak(Request $request, $projectId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $project = AppProject::where('venue_id', $venue->id)->find($projectId);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        // Get active timesheet
        $timesheet = AppProjectTimesheet::where('app_project_id', $projectId)
            ->where('employee_id', $authEmployee->id)
            ->where('status', 'active')
            ->first();

        if (!$timesheet) {
            return response()->json(['error' => 'No active timesheet found. Please clock in first.'], 400);
        }

        // Check for existing active break
        $activeBreak = TimesheetBreak::where('timesheet_id', $timesheet->id)
            ->whereNull('break_end')
            ->first();

        if ($activeBreak) {
            return response()->json(['error' => 'You already have an active break'], 400);
        }

        $validator = Validator::make($request->all(), [
            'break_type' => 'required|in:meal,rest,other',
            'is_paid' => 'required|boolean',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $break = new TimesheetBreak([
            'timesheet_id' => $timesheet->id,
            'venue_id' => $venue->id,
            'break_type' => $request->break_type,
            'is_paid' => $request->is_paid,
            'break_start' => now(),
            'notes' => $request->notes
        ]);

        $break->save();

        // Track break start activity
        $this->activityService->trackBreakStart($authEmployee, $break);

        return response()->json([
            'message' => 'Break started successfully',
            'break' => $break
        ], 201);
    }

    public function endBreak(Request $request, $projectId, $breakId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        // Find the break
        $break = TimesheetBreak::whereHas('timesheet', function($query) use ($authEmployee) {
            $query->where('employee_id', $authEmployee->id);
        })->find($breakId);

        if (!$break) {
            return response()->json(['error' => 'Break not found'], 404);
        }

        if ($break->break_end) {
            return response()->json(['error' => 'Break has already ended'], 400);
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $break->break_end = now();
        if ($request->notes) {
            $break->notes = $break->notes
                ? $break->notes . "\n\nEnd break notes: " . $request->notes
                : $request->notes;
        }

        $break->save();

        // Track break end activity
        $this->activityService->trackBreakEnd($authEmployee, $break);

        $duration = Carbon::parse($break->break_start)
            ->diffInMinutes($break->break_end);

        return response()->json([
            'message' => 'Break ended successfully',
            'break' => $break,
            'duration' => [
                'minutes' => $duration,
                'formatted' => sprintf('%d minutes', $duration)
            ]
        ]);
    }

    public function getTimesheetDetails(Request $request, $projectId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $timesheet = AppProjectTimesheet::where('app_project_id', $projectId)
            ->where('employee_id', $authEmployee->id)
            ->where('venue_id', $venue->id)
            ->with(['breaks' => function($query) {
                $query->orderBy('break_start', 'desc');
            }])
            ->first();

        if (!$timesheet) {
            return response()->json(['error' => 'Timesheet not found'], 404);
        }

        // Calculate various durations
        $totalWorkMinutes = 0;
        $totalBreakMinutes = 0;

        if ($timesheet->clock_out_time) {
            $totalWorkMinutes = Carbon::parse($timesheet->clock_in_time)
                ->diffInMinutes($timesheet->clock_out_time);
        } else {
            $totalWorkMinutes = Carbon::parse($timesheet->clock_in_time)
                ->diffInMinutes(now());
        }

        foreach ($timesheet->breaks as $break) {
            if ($break->break_end) {
                $breakMinutes = Carbon::parse($break->break_start)
                    ->diffInMinutes($break->break_end);
                $totalBreakMinutes += $breakMinutes;
                if (!$break->is_paid) {
                    $totalWorkMinutes -= $breakMinutes;
                }
            }
        }

        $this->activityService->trackTimesheetView($authEmployee, $timesheet);

        return response()->json([
            'timesheet' => $timesheet,
            'summary' => [
                'total_work_time' => [
                    'minutes' => $totalWorkMinutes,
                    'formatted' => sprintf('%d hours %d minutes',
                        floor($totalWorkMinutes / 60),
                        $totalWorkMinutes % 60
                    )
                ],
                'total_break_time' => [
                    'minutes' => $totalBreakMinutes,
                    'formatted' => sprintf('%d hours %d minutes',
                        floor($totalBreakMinutes / 60),
                        $totalBreakMinutes % 60
                    )
                ],
                'breaks_taken' => $timesheet->breaks->count(),
                'status' => $timesheet->status,
                'is_on_break' => $timesheet->breaks->where('break_end', null)->count() > 0
            ]
        ]);
    }


}
