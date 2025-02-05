<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class ShiftController extends Controller
{
    /**
     * Custom validation messages
     */
    private array $messages = [
        'type.required' => 'Please select a leave type',
        'type.exists' => 'Selected leave type is invalid',
        'start_date.required' => 'Start date is required',
        'start_date.date' => 'Start date must be a valid date',
        'start_date.after_or_equal' => 'Start date must be today or a future date',
        'end_date.required' => 'End date is required',
        'end_date.date' => 'End date must be a valid date',
        'end_date.after_or_equal' => 'End date cannot be before start date',
        'reason.required' => 'Please provide a reason for your time off request',
        'reason.string' => 'Reason must be text',
        'reason.max' => 'Reason cannot exceed 500 characters',
        'reason.min' => 'Reason must be at least 10 characters'
    ];


    public function getShiftsData(Request $request): JsonResponse
    {
        // Return demo data if requested
        if ($request->input('is_demo', false)) {
            return $this->getDemoData();
        }

        try {
            $employee = auth()->user()->employee;
            $currentDate = Carbon::now();

            // Get upcoming shift
            $upcomingShift = Schedule::with('venue')
                ->where('employee_id', $employee->id)
                ->where('date', '>=', $currentDate->toDateString())
                ->where('status', '!=', 'time_off')
                ->orderBy('date')
                ->orderBy('start_time')
                ->first();

            // Get shift list for current month
            $shifts = Schedule::with(['venue', 'leaveType'])
                ->where('employee_id', $employee->id)
                ->whereMonth('date', $currentDate->month)
                ->whereYear('date', $currentDate->year)
                ->orderBy('date', 'desc')
                ->get()
                ->map(function ($schedule) {
                    return [
                        'id' => $schedule->id,
                        'date' => Carbon::parse($schedule->date)->format('d/m/Y'),
                        'startTime' => Carbon::parse($schedule->start_time)->format('H:i'),
                        'endTime' => Carbon::parse($schedule->end_time)->format('H:i'),
                        'status' => $this->determineShiftStatus($schedule),
                        'venue' => $schedule->venue->name,
                        'leaveType' => $schedule->leaveType?->name,
                        'isTimeOff' => $schedule->status === 'time_off'
                    ];
                });


            $recordConfigurations = [
                'manual' => false,
                'qr_code' => true
            ];

            return response()->json([
                'upcomingShift' => $upcomingShift ? [
                    'date' => Carbon::parse($upcomingShift->date)->format('d/m/Y'),
                    'startTime' => Carbon::parse($upcomingShift->start_time)->format('H:i'),
                    'endTime' => Carbon::parse($upcomingShift->end_time)->format('H:i'),
                    'venue' => $upcomingShift->venue->name
                ] : null,
                'recentChanges' => $this->getRecentChanges($employee->id),
                'shifts' => $shifts,
                'recordConfigurations' => $recordConfigurations
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch shifts data'], 500);
        }
    }

    private function getDemoData(): JsonResponse
    {
        $currentDate = Carbon::now();

        return response()->json([
            'upcomingShift' => [
                'date' => $currentDate->addDays(1)->format('d/m/Y'),
                'startTime' => '09:00',
                'endTime' => '17:00',
                'venue' => 'Main Office'
            ],
            'recentChanges' => 'No recent schedule changes. Your shifts are as originally planned.',
            'shifts' => [
                [
                    'id' => 1,
                    'date' => $currentDate->format('d/m/Y'),
                    'startTime' => '09:00',
                    'endTime' => '17:00',
                    'status' => 'Upcoming',
                    'venue' => 'Main Office',
                    'isTimeOff' => false
                ],
                [
                    'id' => 2,
                    'date' => $currentDate->subDay()->format('d/m/Y'),
                    'startTime' => '09:00',
                    'endTime' => '17:00',
                    'status' => 'In Progress',
                    'venue' => 'Main Office',
                    'isTimeOff' => false
                ],
                [
                    'id' => 3,
                    'date' => $currentDate->subDay()->format('d/m/Y'),
                    'startTime' => '09:00',
                    'endTime' => '17:00',
                    'status' => 'Completed',
                    'venue' => 'Main Office',
                    'isTimeOff' => false
                ],
                [
                    'id' => 4,
                    'date' => $currentDate->subDay()->format('d/m/Y'),
                    'startTime' => '09:00',
                    'endTime' => '17:00',
                    'status' => 'Time Off',
                    'venue' => 'N/A',
                    'isTimeOff' => true,
                    'leaveType' => 'Vacation'
                ]
            ],
            'qrSettings' => [
                'isEnabled' => true,
                'isRequired' => true
            ]
        ]);
    }

    public function getLeaveTypes(): JsonResponse
    {
        try {
            $leaveTypes = LeaveType::select('id as value', 'name as label')
                ->orderBy('name')
                ->get();

            return response()->json($leaveTypes);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch leave types'], 500);
        }
    }

    public function getLeaveBalance(): JsonResponse
    {
        try {
            $employee = auth()->user()->employee;
            $currentYear = Carbon::now()->year;

            // Get used leave days
            $usedLeaveDays = Schedule::where('employee_id', $employee->id)
                ->where('status', 'time_off')
                ->whereYear('date', $currentYear)
                ->sum('total_days');

            // Get total annual leave days from settings
            $totalLeaveDays = config('leave.annual_days', 30);

            return response()->json([
                'totalDays' => $totalLeaveDays,
                'usedDays' => (int)$usedLeaveDays,
                'remainingDays' => $totalLeaveDays - (int)$usedLeaveDays
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch leave balance'], 500);
        }
    }

    public function getShiftList(Request $request): JsonResponse
    {
        try {
            $employee = auth()->user()->employee;
            
            $page = $request->input('page', 1); 
            $perPage = $request->input('per_page', 10);
            $shifts = Schedule::with(['employee:id,name'])
                ->where('restaurant_id', $employee->restaurant_id)
                ->orderBy('date', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $employeeOnShift = Employee::where('restaurant_id', $employee->restaurant_id)
                                        ->where('status', 'active')
                                        ->whereDoesntHave('leaveRequests', function ($query) {
                                            $query->where('date', now()->toDateString());
                                        })
                                        ->count();
            $currentlyOnBreak = Employee::where('restaurant_id', $employee->restaurant_id)
                                        ->where('status', 'on-break')
                                        ->count();
            $pendingAlerts = 0;
            $overtimeAlerts = 0;
            $complienceScore = 100;
            return response()->json([
                'data' => $shifts->items(),
                'employeeOnShift' => $employeeOnShift,
                'currentlyOnBreak' => $currentlyOnBreak,
                'pendingAlerts' => $pendingAlerts,
                'overtimeAlerts' => $overtimeAlerts,
                'complienceScore' => $complienceScore,
                'pagination' => [
                    'total' => $shifts->total(),
                    'per_page' => $shifts->perPage(),
                    'current_page' => $shifts->currentPage(),
                    'last_page' => $shifts->lastPage(),
                ],
                'success' => true,
                'message' => 'Shift list retrieved successfully'
            ]);
        } catch (\Exception $e) {
            dd($e);
            return response()->json(['error' => 'Failed to fetch shift list'], 500);
        }
    }

    public function requestTimeOff(Request $request): JsonResponse
    {
        try {
            // Basic validation
            $validator = Validator::make($request->all(), [
                'type' => 'required|exists:leave_types,id',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'required|string|min:10|max:500',
            ], $this->messages);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            $employee = auth()->user()->employee;
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);

            // Additional date validations
            if ($this->isWeekend($startDate) || $this->isWeekend($endDate)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Leave dates cannot start or end on weekends',
                    'errors' => ['dates' => ['Please select only working days']]
                ], 422);
            }

            if ($this->isHoliday($startDate) || $this->isHoliday($endDate)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Leave dates cannot start or end on holidays',
                    'errors' => ['dates' => ['Please select only working days']]
                ], 422);
            }

            // Maximum date range validation (e.g., 30 days)
            if ($startDate->diffInDays($endDate) > 30) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Leave request exceeds maximum allowed duration',
                    'errors' => ['dates' => ['Leave requests cannot exceed 30 days']]
                ], 422);
            }

            // Calculate total working days
            $totalDays = $this->calculateWorkingDays($startDate, $endDate);

            if ($totalDays === 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No working days selected',
                    'errors' => ['dates' => ['Selected date range contains no working days']]
                ], 422);
            }

            // Check leave balance
            $leaveBalanceResponse = $this->getLeaveBalance();
            $leaveBalanceData = json_decode($leaveBalanceResponse->getContent(), true);
            $remainingDays = $leaveBalanceData['remainingDays'];

            if ($totalDays > $remainingDays) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Insufficient leave balance",
                    'errors' => ['balance' => ["You have {$remainingDays} days remaining but requested {$totalDays} days"]]
                ], 422);
            }
            // Check for overlapping requests
            $existingRequests = Schedule::where('employee_id', $employee->id)
                ->where('status', 'time_off')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->where(function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('date', [$startDate, $endDate])
                            ->orWhereBetween('end_date', [$startDate, $endDate]);
                    })->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
                })
                ->first();

            if ($existingRequests) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Date conflict detected',
                    'errors' => [
                        'dates' => ['You already have a leave request for ' .
                            $existingRequests->date->format('M d') . ' to ' .
                            $existingRequests->end_date->format('M d')]
                    ]
                ], 422);
            }

            // Check minimum notice period (e.g., 2 days)
            $minNotice = config('leave.minimum_notice_days', 2);
            if ($startDate->diffInDays(now()) < $minNotice) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient notice period',
                    'errors' => ['dates' => ["Leave requests must be submitted at least {$minNotice} days in advance"]]
                ], 422);
            }

            DB::beginTransaction();

            try {
                // Create the leave request
                $schedule = Schedule::create([
                    'employee_id' => $employee->id,
                    'date' => $startDate,
                    'end_date' => $endDate,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'status' => 'time_off',
                    'leave_type_id' => $validated['type'],
                    'reason' => $validated['reason'],
                    'total_days' => $totalDays,
                    'restaurant_id' => $employee->restaurant_id
                ]);

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Time off request submitted successfully',
                    'data' => [
                        'request_id' => $schedule->id,
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d'),
                        'total_days' => $totalDays,
                        'remaining_balance' => $remainingDays - $totalDays
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit time off request',
                'errors' => ['system' => [$e->getMessage()]]
            ], 500);
        }
    }

    private function determineShiftStatus(Schedule $schedule): string
    {
        if ($schedule->status === 'time_off') {
            return 'Time Off';
        }

        $now = Carbon::now();
        $shiftDate = Carbon::parse($schedule->date);
        $startTime = Carbon::parse($schedule->start_time);
        $endTime = Carbon::parse($schedule->end_time);

        $shiftStart = $shiftDate->copy()->setTimeFrom($startTime);
        $shiftEnd = $shiftDate->copy()->setTimeFrom($endTime);

        if ($now < $shiftStart) {
            return 'Upcoming';
        } elseif ($now >= $shiftStart && $now <= $shiftEnd) {
            return 'In Progress';
        }

        return 'Completed';
    }

    private function getRecentChanges(int $employeeId): string
    {
        $recentChanges = Schedule::where('employee_id', $employeeId)
            ->where('updated_at', '>=', now()->subDays(7))
            ->where('updated_at', '>', DB::raw('created_at'))
            ->count();

        return $recentChanges > 0
            ? "Schedule changes found for {$recentChanges} shifts in the last 7 days"
            : 'No recent schedule changes. Your shifts are as originally planned.';
    }

    /**
     * Check if date is a weekend
     */
    private function isWeekend(Carbon $date): bool
    {
        return $date->isWeekend();
    }

    /**
     * Check if date is a holiday
     */
    private function isHoliday(Carbon $date): bool
    {
        // Implement your holiday checking logic here
        // Example: Check against a holidays table or API
        return false;
    }

    /**
     * Calculate working days between dates
     */
    private function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $days = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            if (!$this->isWeekend($current) && !$this->isHoliday($current)) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    public function getCalendarEvents(Request $request): JsonResponse
    {
        try {
            $employee = auth()->user()->employee;

            // Default to current week if dates not provided
            $startDate = $request->input('start')
                ? Carbon::parse($request->input('start'))
                : Carbon::now()->startOfWeek();

            $endDate = $request->input('end')
                ? Carbon::parse($request->input('end'))
                : Carbon::now()->endOfWeek();

            $schedules = Schedule::with(['employee', 'venue'])
                ->where('restaurant_id', $employee->restaurant_id)
                ->whereBetween('date', [$startDate, $endDate])
                ->get()
                ->map(function ($schedule) {
                    $date = Carbon::parse($schedule->date)->format('Y-m-d');
                    $startTime = Carbon::parse($schedule->start_time)->format('H:i:s');
                    $endTime = Carbon::parse($schedule->end_time)->format('H:i:s');

                    return [
                        'id' => $schedule->id,
                        'title' => $schedule->employee->name . ' - ' .
                            ($schedule->status === 'time_off' ? 'Leave' :
                                ($schedule->schedule_type === 'task' ? 'Task' :
                                    ($schedule->schedule_type === 'job' ? 'Job' : 'Shift'))),
                        'start' => "{$date}T{$startTime}",
                        'end' => "{$date}T{$endTime}",
                        'type' => $schedule->schedule_type ?? 'shift',
                        'backgroundColor' => $this->getEventColor($schedule),
                        'employee' => [
                            'id' => $schedule->employee->id,
                            'name' => $schedule->employee->name
                        ],
                        'venue' => $schedule->venue->name,
                        'status' => $schedule->status
                    ];
                });

            return response()->json($schedules);

        } catch (\Exception $e) {
            \Log::error('Calendar events error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load calendar'], 500);
        }
    }

    public function createSchedule(Request $request): JsonResponse
    {
        try {
            $employee = auth()->user()->employee;

            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'date' => 'required|date',
                'start_time' => 'required',
                'end_time' => 'required|after:start_time',
                'schedule_type' => 'nullable|in:shift,task,job'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check for schedule conflicts
            $hasConflict = Schedule::where('employee_id', $request->employee_id)
                ->where('date', $request->date)
                ->where(function($query) use ($request) {
                    $query->whereTime('start_time', '<', $request->end_time)
                        ->whereTime('end_time', '>', $request->start_time);
                })
                ->exists();

            if ($hasConflict) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Schedule conflicts with existing shifts'
                ], 422);
            }

            $schedule = Schedule::create([
                'employee_id' => $request->employee_id,
                'restaurant_id' => $employee->restaurant_id,
                'date' => $request->date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'schedule_type' => $request->schedule_type ?? 'shift',
                'status' => 'scheduled'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Schedule created successfully',
                'data' => $schedule
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getEventColor(Schedule $schedule): string
    {
        if ($schedule->status === 'time_off') {
            return '#ef4444'; // Red for leave
        }

        return match($schedule->schedule_type) {
            'task' => '#10b981',  // Green for tasks
            'job' => '#f59e0b',   // Orange for jobs
            default => '#3b82f6'  // Blue for regular shifts
        };
    }
}
