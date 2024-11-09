<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AttendanceAnalyticsService
{
    protected string $defaultStartTime;

    public function __construct()
    {
        $this->defaultStartTime = config('attendance.default_start_time', '09:00:00');
    }

    public function getAnalytics(int $venueId, string $timeFrame = 'monthly'): array
    {
        $startDate = $this->getStartDate($timeFrame);

        return [
            'attendanceTrend' => $this->getAttendanceTrend($venueId, $startDate),
            'attendanceOverview' => $this->getAttendanceOverview($venueId, $startDate),
            'departmentAttendance' => $this->getDepartmentAttendance($venueId, $startDate),
            'insights' => $this->getInsights($venueId, $startDate, $timeFrame)
        ];
    }

    public function getAttendanceTrend(int $venueId, Carbon $startDate): array
    {
        $totalStaff = Employee::where('restaurant_id', $venueId)->count();

        $records = DB::table('attendance_records')
            ->join('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->where('attendance_records.venue_id', $venueId)
            ->where('attendance_records.scanned_at', '>=', $startDate)
            ->where('attendance_records.scan_type', 'check_in')
            ->select(
                DB::raw('DATE(attendance_records.scanned_at) as date'),
                DB::raw('COUNT(DISTINCT employees.id) as total_employees'),
                DB::raw("COUNT(DISTINCT CASE WHEN TIME(attendance_records.scanned_at) <= '{$this->defaultStartTime}' THEN employees.id END) as present"),
                DB::raw("COUNT(DISTINCT CASE WHEN TIME(attendance_records.scanned_at) > '{$this->defaultStartTime}' THEN employees.id END) as late"),
                DB::raw("COUNT(DISTINCT CASE WHEN attendance_records.is_within_geofence = 0 THEN employees.id END) as outside_geofence")
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $records->map(function ($record) use ($totalStaff) {
            return [
                'date' => $record->date,
                'total_staff' => $totalStaff,
                'present' => round(($record->present / $totalStaff) * 100, 1),
                'late' => round(($record->late / $totalStaff) * 100, 1),
                'absent' => round((($totalStaff - ($record->present + $record->late)) / $totalStaff) * 100, 1),
                'outside_geofence' => round(($record->outside_geofence / $totalStaff) * 100, 1)
            ];
        })->toArray();
    }

    public function getAttendanceOverview(int $venueId, Carbon $startDate): array
    {
        $workDays = $this->calculateWorkDays($startDate);
        $totalEmployees = Employee::where('restaurant_id', $venueId)->count();
        $totalPossibleAttendance = $workDays * $totalEmployees;

        $stats = DB::table('attendance_records')
            ->join('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->where('attendance_records.venue_id', $venueId)
            ->where('attendance_records.scanned_at', '>=', $startDate)
            ->where('attendance_records.scan_type', 'check_in')
            ->select(
                DB::raw('COUNT(DISTINCT CONCAT(DATE(scanned_at), employee_id)) as total_present'),
                DB::raw("SUM(CASE WHEN TIME(scanned_at) > '{$this->defaultStartTime}' THEN 1 ELSE 0 END) as total_late"),
                DB::raw('SUM(CASE WHEN duration_minutes < 480 THEN 1 ELSE 0 END) as early_leaves'),
                DB::raw('SUM(CASE WHEN is_within_geofence = 0 THEN 1 ELSE 0 END) as outside_geofence')
            )
            ->first();

        return [
            [
                'status' => 'Present',
                'value' => round(($stats->total_present / $totalPossibleAttendance) * 100, 1),
                'total' => $stats->total_present
            ],
            [
                'status' => 'Late',
                'value' => round(($stats->total_late / $totalPossibleAttendance) * 100, 1),
                'total' => $stats->total_late
            ],
            [
                'status' => 'Absent',
                'value' => round((($totalPossibleAttendance - $stats->total_present) / $totalPossibleAttendance) * 100, 1),
                'total' => $totalPossibleAttendance - $stats->total_present
            ],
            [
                'status' => 'Early Leaves',
                'value' => round(($stats->early_leaves / $totalPossibleAttendance) * 100, 1),
                'total' => $stats->early_leaves
            ],
            [
                'status' => 'Outside Geofence',
                'value' => round(($stats->outside_geofence / $totalPossibleAttendance) * 100, 1),
                'total' => $stats->outside_geofence
            ]
        ];
    }

    public function getDepartmentAttendance(int $venueId, Carbon $startDate): array
    {
        $workDays = $this->calculateWorkDays($startDate);

        $stats = DB::table('attendance_records')
            ->join('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->where('attendance_records.venue_id', $venueId)
            ->where('attendance_records.scanned_at', '>=', $startDate)
            ->where('attendance_records.scan_type', 'check_in')
            ->select(
                'departments.id',
                'departments.name as department',
                DB::raw('COUNT(DISTINCT employees.id) as total_employees'),
                DB::raw('COUNT(DISTINCT CONCAT(DATE(attendance_records.scanned_at), employees.id)) as total_attendance'),
                DB::raw("COUNT(DISTINCT CASE WHEN TIME(attendance_records.scanned_at) > '{$this->defaultStartTime}' THEN employees.id END) as late_count"),
                DB::raw('COUNT(DISTINCT CASE WHEN is_within_geofence = 0 THEN employees.id END) as outside_geofence')
            )
            ->groupBy('departments.id', 'departments.name')
            ->get();

        return $stats->map(function ($dept) use ($workDays) {
            $possibleAttendance = $workDays * $dept->total_employees;
            return [
                'department' => $dept->department,
                'total_employees' => $dept->total_employees,
                'attendance_rate' => round(($dept->total_attendance / $possibleAttendance) * 100, 1),
                'late_rate' => round(($dept->late_count / $dept->total_attendance) * 100, 1),
                'geofence_violation_rate' => round(($dept->outside_geofence / $dept->total_attendance) * 100, 1)
            ];
        })->toArray();
    }

    public function getInsights(int $venueId, Carbon $startDate, string $timeFrame): array
    {
        $overview = $this->getAttendanceOverview($venueId, $startDate);
        $deptStats = $this->getDepartmentAttendance($venueId, $startDate);

        $latePatterns = $this->getLatePatterns($venueId, $startDate);
        $peakHours = $this->getPeakHours($venueId, $startDate);

        $presentRate = collect($overview)->firstWhere('status', 'Present')['value'];
        $bestDept = collect($deptStats)->sortByDesc('attendance_rate')->first();
        $worstDept = collect($deptStats)->sortBy('attendance_rate')->first();

        return [
            'summary' => [
                'time_frame' => $timeFrame,
                'overall_attendance_rate' => $presentRate,
                'late_arrival_rate' => collect($overview)->firstWhere('status', 'Late')['value'],
                'early_departure_rate' => collect($overview)->firstWhere('status', 'Early Leaves')['value']
            ],
            'departments' => [
                'best' => [
                    'name' => $bestDept['department'],
                    'rate' => $bestDept['attendance_rate']
                ],
                'needs_improvement' => [
                    'name' => $worstDept['department'],
                    'rate' => $worstDept['attendance_rate']
                ]
            ],
            'patterns' => [
                'highest_late_day' => $latePatterns->day ?? null,
                'peak_check_in_hour' => $peakHours->hour ?? null,
                'geofence_violation_rate' => collect($overview)->firstWhere('status', 'Outside Geofence')['value']
            ]
        ];
    }

    protected function getLatePatterns(int $venueId, Carbon $startDate)
    {
        return DB::table('attendance_records')
            ->where('venue_id', $venueId)
            ->where('scanned_at', '>=', $startDate)
            ->where('scan_type', 'check_in')
            ->whereRaw("TIME(scanned_at) > '{$this->defaultStartTime}'")
            ->select(
                DB::raw('DAYNAME(scanned_at) as day'),
                DB::raw('COUNT(*) as late_count')
            )
            ->groupBy('day')
            ->orderByDesc('late_count')
            ->first();
    }

    protected function getPeakHours(int $venueId, Carbon $startDate)
    {
        return DB::table('attendance_records')
            ->where('venue_id', $venueId)
            ->where('scanned_at', '>=', $startDate)
            ->where('scan_type', 'check_in')
            ->select(
                DB::raw('HOUR(scanned_at) as hour'),
                DB::raw('COUNT(*) as check_in_count')
            )
            ->groupBy('hour')
            ->orderByDesc('check_in_count')
            ->first();
    }

    protected function calculateWorkDays(Carbon $startDate): int
    {
        $workDays = 0;
        $current = $startDate->copy();
        $endDate = now();

        while ($current <= $endDate) {
            if (!$current->isWeekend()) {
                $workDays++;
            }
            $current->addDay();
        }

        return $workDays;
    }

    protected function getStartDate(string $timeFrame): Carbon
    {
        return match($timeFrame) {
            'weekly' => now()->subWeek(),
            'monthly' => now()->subMonth(),
            'quarterly' => now()->subMonths(3),
            'yearly' => now()->subYear(),
            default => now()->subMonth()
        };
    }

    public function exportAttendanceReport(int $venueId, string $timeFrame): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $startDate = $this->getStartDate($timeFrame);

        // Create new spreadsheet
        $spreadsheet = new Spreadsheet();

        // Daily Attendance Sheet
        $this->addDailyAttendanceSheet($spreadsheet, $venueId, $startDate);

        // Department Summary Sheet
        $this->addDepartmentSummarySheet($spreadsheet, $venueId, $startDate);

        // Individual Records Sheet
        $this->addDetailedRecordsSheet($spreadsheet, $venueId, $startDate);

        // Insights Sheet
        $this->addInsightsSheet($spreadsheet, $venueId, $startDate, $timeFrame);

        // Create file
        $fileName = "attendance-report-{$timeFrame}-" . now()->format('Y-m-d') . '.xlsx';
        $tempPath = storage_path('app/temp/' . $fileName);

        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private function addDailyAttendanceSheet(Spreadsheet $spreadsheet, int $venueId, Carbon $startDate): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Daily Attendance');

        // Headers
        $sheet->fromArray([
            ['Date', 'Total Staff', 'Present', 'Late', 'Absent', 'Outside Geofence', 'Early Departures']
        ]);

        // Data
        $records = $this->getAttendanceTrend($venueId, $startDate);
        $rowData = array_map(fn($item) => [
            $item['date'],
            $item['total_staff'],
            $item['present'] . '%',
            $item['late'] . '%',
            $item['absent'] . '%',
            $item['outside_geofence'] . '%',
            $item['early_departures'] ?? '0%'
        ], $records);

        $sheet->fromArray($rowData, null, 'A2');

        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function addDepartmentSummarySheet(Spreadsheet $spreadsheet, int $venueId, Carbon $startDate): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Department Summary');

        // Headers
        $sheet->fromArray([
            ['Department', 'Total Employees', 'Attendance Rate', 'Late Rate', 'Geofence Violation Rate']
        ]);

        // Data
        $deptStats = $this->getDepartmentAttendance($venueId, $startDate);
        $rowData = array_map(fn($dept) => [
            $dept['department'],
            $dept['total_employees'],
            $dept['attendance_rate'] . '%',
            $dept['late_rate'] . '%',
            $dept['geofence_violation_rate'] . '%'
        ], $deptStats);

        $sheet->fromArray($rowData, null, 'A2');

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function addDetailedRecordsSheet(Spreadsheet $spreadsheet, int $venueId, Carbon $startDate): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Detailed Records');

        // Headers
        $sheet->fromArray([
            ['Date', 'Employee', 'Department', 'Scan Type', 'Time', 'Method', 'Location Status', 'Duration']
        ]);

        // Get detailed records
        $records = AttendanceRecord::with(['employee.department'])
            ->where('venue_id', $venueId)
            ->where('scanned_at', '>=', $startDate)
            ->orderBy('scanned_at', 'desc')
            ->get()
            ->map(fn($record) => [
                $record->scanned_at->format('Y-m-d'),
                $record->employee->name,
                $record->employee->department->name ?? 'N/A',
                ucfirst($record->scan_type),
                $record->scanned_at->format('H:i'),
                ucfirst($record->scan_method),
                $record->is_within_geofence ? 'Within Geofence' : 'Outside Geofence',
                $record->duration_minutes ? floor($record->duration_minutes/60) . 'h ' . ($record->duration_minutes%60) . 'm' : 'N/A'
            ]);

        $sheet->fromArray($records->toArray(), null, 'A2');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function addInsightsSheet(Spreadsheet $spreadsheet, int $venueId, Carbon $startDate, string $timeFrame): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Insights');

        $insights = $this->getInsights($venueId, $startDate, $timeFrame);

        // Summary Section
        $sheet->setCellValue('A1', 'Summary');
        $sheet->setCellValue('A2', 'Time Frame');
        $sheet->setCellValue('B2', ucfirst($timeFrame));
        $sheet->setCellValue('A3', 'Overall Attendance Rate');
        $sheet->setCellValue('B3', $insights['summary']['overall_attendance_rate'] . '%');
        $sheet->setCellValue('A4', 'Late Arrival Rate');
        $sheet->setCellValue('B4', $insights['summary']['late_arrival_rate'] . '%');

        // Department Section
        $sheet->setCellValue('A6', 'Department Performance');
        $sheet->setCellValue('A7', 'Best Performing');
        $sheet->setCellValue('B7', $insights['departments']['best']['name']);
        $sheet->setCellValue('C7', $insights['departments']['best']['rate'] . '%');
        $sheet->setCellValue('A8', 'Needs Improvement');
        $sheet->setCellValue('B8', $insights['departments']['needs_improvement']['name']);
        $sheet->setCellValue('C8', $insights['departments']['needs_improvement']['rate'] . '%');

        // Patterns Section
        $sheet->setCellValue('A10', 'Patterns');
        $sheet->setCellValue('A11', 'Highest Late Day');
        $sheet->setCellValue('B11', $insights['patterns']['highest_late_day'] ?? 'N/A');
        $sheet->setCellValue('A12', 'Peak Check-in Hour');
        $sheet->setCellValue('B12', $insights['patterns']['peak_check_in_hour'] ? $insights['patterns']['peak_check_in_hour'] . ':00' : 'N/A');

        foreach (range('A', 'C') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
