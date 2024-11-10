<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\AppProjectTimesheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class EmployeeDashboardController extends Controller
{
    public function getDashboardData(Request $request): JsonResponse
    {
        // Return demo data if requested
        if ($request->input('is_demo', false)) {
            return $this->getDemoData();
        }

        // Original implementation for real data
        $employee = auth()->user()->employee;
        $startDate = $request->input('start_date') ? Carbon::parse($request->start_date) : Carbon::now()->startOfMonth();
        $endDate = $request->input('end_date') ? Carbon::parse($request->end_date) : Carbon::now()->endOfMonth();

        try {
            // Weekly Attendance Overview - Split into 10-day periods
            $periods = [
                [$startDate->copy()->startOfMonth(), $startDate->copy()->startOfMonth()->addDays(9)],
                [$startDate->copy()->startOfMonth()->addDays(10), $startDate->copy()->startOfMonth()->addDays(19)],
                [$startDate->copy()->startOfMonth()->addDays(20), $startDate->copy()->endOfMonth()]
            ];

            $weeklyAttendance = [];
            foreach ($periods as $period) {
                $timesheetData = AppProjectTimesheet::where('employee_id', $employee->id)
                    ->whereBetween('clock_in_time', $period)
                    ->select(
                        DB::raw('SUM(CASE WHEN TIME(clock_in_time) <= "09:00:00" THEN total_hours ELSE 0 END) as on_time_hours'),
                        DB::raw('SUM(CASE WHEN TIME(clock_in_time) < "09:00:00" THEN total_hours ELSE 0 END) as early_hours'),
                        DB::raw('SUM(CASE WHEN TIME(clock_in_time) > "09:00:00" THEN total_hours ELSE 0 END) as late_hours')
                    )
                    ->first();

                $weeklyAttendance[] = [
                    'onTime' => round($timesheetData->on_time_hours ?? 0, 2),
                    'early' => round($timesheetData->early_hours ?? 0, 2),
                    'late' => round($timesheetData->late_hours ?? 0, 2)
                ];
            }

            // Task Project Report
            $taskStats = [
                'inProgress' => Task::whereHas('assignedEmployees', function($q) use ($employee) {
                    $q->where('employee_id', $employee->id);
                })
                    ->where('status', Task::STATUS_IN_PROGRESS)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),

                'overdue' => Task::whereHas('assignedEmployees', function($q) use ($employee) {
                    $q->where('employee_id', $employee->id);
                })
                    ->where('due_date', '<', now())
                    ->where('status', '!=', Task::STATUS_DONE)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),

                'upcoming' => Task::whereHas('assignedEmployees', function($q) use ($employee) {
                    $q->where('employee_id', $employee->id);
                })
                    ->where('status', Task::STATUS_TODO)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),

                'completed' => Task::whereHas('assignedEmployees', function($q) use ($employee) {
                    $q->where('employee_id', $employee->id);
                })
                    ->where('status', Task::STATUS_DONE)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count()
            ];

            return response()->json([
                'weeklyAttendance' => $weeklyAttendance,
                'taskReport' => $this->formatTaskReport($taskStats)
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch dashboard data'], 500);
        }
    }

    /**
     * Format task report data
     */
    private function formatTaskReport(array $taskStats): array
    {
        return [
            'inProgressTasks' => [
                'name' => 'In-Progress Tasks',
                'population' => $taskStats['inProgress'],
                'color' => '#1E40AF' // colorsBlue900
            ],
            'overdueTasks' => [
                'name' => 'Overdue Tasks',
                'population' => $taskStats['overdue'],
                'color' => '#60A5FA' // colorsBlue400
            ],
            'upcomingTasks' => [
                'name' => 'Upcoming Tasks',
                'population' => $taskStats['upcoming'],
                'color' => '#93C5FD' // colorsBlue300
            ],
            'completedTasks' => [
                'name' => 'Completed Tasks',
                'population' => $taskStats['completed'],
                'color' => '#3B82F6' // colorsBlue500
            ]
        ];
    }

    public function exportData(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $employee = auth()->user()->employee;
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        try {
            // Get timesheet data
            $timesheets = AppProjectTimesheet::with(['project', 'task'])
                ->where('employee_id', $employee->id)
                ->whereBetween('clock_in_time', [$startDate, $endDate])
                ->get()
                ->map(function($timesheet) {
                    return [
                        'Date' => $timesheet->clock_in_time->format('Y-m-d'),
                        'Project' => $timesheet->project->name,
                        'Task' => $timesheet->task?->name ?? 'N/A',
                        'Clock In' => $timesheet->clock_in_time->format('H:i'),
                        'Clock Out' => $timesheet->clock_out_time?->format('H:i') ?? 'N/A',
                        'Total Hours' => $timesheet->total_hours,
                        'Status' => $timesheet->status
                    ];
                });

            // Create CSV
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="timesheet_export.csv"',
            ];

            $callback = function() use ($timesheets) {
                $file = fopen('php://output', 'w');

                // Add headers
                fputcsv($file, array_keys($timesheets->first() ?? []));

                // Add rows
                foreach ($timesheets as $timesheet) {
                    fputcsv($file, $timesheet);
                }

                fclose($file);
            };

            return Response::stream($callback, 200, $headers);

        } catch (\Exception $e) {
            // Since we can't return JsonResponse due to return type declaration,
            // we'll throw an exception that can be caught by Laravel's exception handler
            throw new \Exception('Failed to export data');
        }
    }

    /**
     * Get demo data for dashboard
     */
    private function getDemoData(): JsonResponse
    {
        // Demo data for weekly attendance
        $weeklyAttendance = [
            [
                'onTime' => 32.5,
                'early' => 12.0,
                'late' => 5.5
            ],
            [
                'onTime' => 28.0,
                'early' => 15.5,
                'late' => 6.5
            ],
            [
                'onTime' => 35.0,
                'early' => 10.0,
                'late' => 4.0
            ]
        ];

        // Demo data for task stats
        $taskStats = [
            'inProgress' => 8,
            'overdue' => 3,
            'upcoming' => 12,
            'completed' => 15
        ];

        return response()->json([
            'weeklyAttendance' => $weeklyAttendance,
            'taskReport' => $this->formatTaskReport($taskStats),
            'periodRange' => [
                'start' => Carbon::now()->startOfMonth()->format('Y-m-d'),
                'end' => Carbon::now()->endOfMonth()->format('Y-m-d')
            ]
        ]);
    }

}
