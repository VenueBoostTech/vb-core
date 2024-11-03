<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\AttendanceService;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceController extends Controller
{
    protected VenueService $venueService;
    protected AttendanceService $attendanceService;

    public function __construct(VenueService $venueService, AttendanceService $attendanceService)
    {
        $this->venueService = $venueService;
        $this->attendanceService = $attendanceService;
    }

    public function recordAttendance(Request $request): JsonResponse
    {

        try {
            $validated = $request->validate([
                'scan_method' => 'required|in:nfc,qr',
                'nfc_card_id' => 'required_if:scan_method,nfc',
                'qr_code' => 'required_if:scan_method,qr',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
            ]);


            $venue = $this->venueService->adminAuthCheck();
            $employee =  $this->venueService->employee();

            $result = $this->attendanceService->recordAttendance($employee, $venue, $validated);

            return response()->json($result);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $timeFrame = $request->input('time_frame', 'monthly');
        $startDate = match($timeFrame) {
            'weekly' => now()->subWeek(),
            'monthly' => now()->subMonth(),
            'quarterly' => now()->subMonths(3),
            'yearly' => now()->subYear(),
            default => now()->subMonth()
        };

        return response()->json([
            'attendanceTrend' => $this->getAttendanceTrend($venue->id, $startDate),
            'attendanceOverview' => $this->getAttendanceOverview($venue->id, $startDate),
            'departmentAttendance' => $this->getDepartmentAttendance($venue->id, $startDate),
            'insights' => $this->getInsights($venue->id, $startDate, $timeFrame)
        ]);
    }

    private function getAttendanceTrend(int $venueId, Carbon $startDate): array
    {
        return DB::table('attendance_records')
            ->join('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->where('attendance_records.venue_id', $venueId)
            ->where('attendance_records.scanned_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(attendance_records.scanned_at) as date'),
                DB::raw('COUNT(DISTINCT employees.id) as total_employees'),
                DB::raw('COUNT(DISTINCT CASE
                    WHEN TIME(attendance_records.scanned_at) <= "09:00:00" THEN employees.id
                    END) as present'),
                DB::raw('COUNT(DISTINCT CASE
                    WHEN TIME(attendance_records.scanned_at) > "09:00:00" THEN employees.id
                    END) as late')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($record) {
                $totalStaff = Employee::where('restaurant_id', $venueId)->count();
                return [
                    'date' => $record->date,
                    'present' => round(($record->present / $totalStaff) * 100, 1),
                    'late' => round(($record->late / $totalStaff) * 100, 1),
                    'absent' => round((($totalStaff - ($record->present + $record->late)) / $totalStaff) * 100, 1)
                ];
            })
            ->toArray();
    }

    private function getAttendanceOverview(int $venueId, Carbon $startDate): array
    {
        $totalWorkDays = $startDate->diffInDays(now());
        $totalEmployees = Employee::where('restaurant_id', $venueId)->count();
        $totalPossibleAttendance = $totalWorkDays * $totalEmployees;

        $attendanceStats = DB::table('attendance_records')
            ->join('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->where('attendance_records.venue_id', $venueId)
            ->where('attendance_records.scanned_at', '>=', $startDate)
            ->select(
                DB::raw('COUNT(DISTINCT CONCAT(DATE(scanned_at), employee_id)) as total_present'),
                DB::raw('SUM(CASE WHEN TIME(scanned_at) > "09:00:00" THEN 1 ELSE 0 END) as total_late')
            )
            ->first();

        return [
            [
                'status' => 'Present',
                'value' => round(($attendanceStats->total_present / $totalPossibleAttendance) * 100, 1)
            ],
            [
                'status' => 'Late',
                'value' => round(($attendanceStats->total_late / $totalPossibleAttendance) * 100, 1)
            ],
            [
                'status' => 'Absent',
                'value' => round((($totalPossibleAttendance - $attendanceStats->total_present) / $totalPossibleAttendance) * 100, 1)
            ]
        ];
    }

    private function getDepartmentAttendance(int $venueId, Carbon $startDate): array
    {
        return DB::table('attendance_records')
            ->join('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->where('attendance_records.venue_id', $venueId)
            ->where('attendance_records.scanned_at', '>=', $startDate)
            ->select(
                'departments.name as department',
                DB::raw('COUNT(DISTINCT employees.id) as total_employees'),
                DB::raw('COUNT(DISTINCT CONCAT(DATE(attendance_records.scanned_at), employees.id)) as total_attendance')
            )
            ->groupBy('departments.id', 'departments.name')
            ->get()
            ->map(function ($dept) use ($startDate) {
                $workDays = $startDate->diffInDays(now());
                $possibleAttendance = $workDays * $dept->total_employees;
                return [
                    'department' => $dept->department,
                    'attendance' => round(($dept->total_attendance / $possibleAttendance) * 100, 1)
                ];
            })
            ->toArray();
    }

    private function getInsights(int $venueId, Carbon $startDate, string $timeFrame): array
    {
        // Get overall attendance rate
        $overview = $this->getAttendanceOverview($venueId, $startDate);
        $overallRate = collect($overview)->firstWhere('status', 'Present')['value'];

        // Get department attendance
        $departmentStats = $this->getDepartmentAttendance($venueId, $startDate);
        $bestDept = collect($departmentStats)->sortByDesc('attendance')->first();
        $worstDept = collect($departmentStats)->sortBy('attendance')->first();

        // Get late arrival patterns
        $latePatterns = DB::table('attendance_records')
            ->where('venue_id', $venueId)
            ->where('scanned_at', '>=', $startDate)
            ->whereRaw('TIME(scanned_at) > "09:00:00"')
            ->select(
                DB::raw('DAYNAME(scanned_at) as day'),
                DB::raw('COUNT(*) as late_count')
            )
            ->groupBy('day')
            ->orderByDesc('late_count')
            ->first();

        return [
            'overallRate' => $overallRate,
            'timeFrame' => $timeFrame,
            'bestDepartment' => $bestDept,
            'worstDepartment' => $worstDept,
            'highestLateDay' => $latePatterns?->day ?? null,
            'lateArrivalRate' => collect($overview)->firstWhere('status', 'Late')['value']
        ];
    }

    public function export(Request $request): JsonResponse|BinaryFileResponse
    {
        try {
            $venue = $this->venueService->adminAuthCheck();
            if ($venue instanceof JsonResponse) return $venue;

            $timeFrame = $request->input('time_frame', 'monthly');
            $startDate = match($timeFrame) {
                'weekly' => now()->subWeek(),
                'monthly' => now()->subMonth(),
                'quarterly' => now()->subMonths(3),
                'yearly' => now()->subYear(),
                default => now()->subMonth()
            };

            // Create new spreadsheet
            $spreadsheet = new Spreadsheet();

            // Daily Attendance Sheet
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Daily Attendance');
            $sheet->fromArray([
                ['Date', 'Present %', 'Late %', 'Absent %']
            ]);

            $trendData = $this->getAttendanceTrend($venue->id, $startDate);
            $dailyData = array_map(fn($item) => [
                $item['date'],
                $item['present'],
                $item['late'],
                $item['absent']
            ], $trendData);
            $sheet->fromArray($dailyData, null, 'A2');

            // Overview Sheet
            $overviewSheet = $spreadsheet->createSheet();
            $overviewSheet->setTitle('Overview');
            $overviewSheet->fromArray([
                ['Status', 'Percentage']
            ]);

            $overviewData = $this->getAttendanceOverview($venue->id, $startDate);
            $overviewRows = array_map(fn($item) => [
                $item['status'],
                $item['value']
            ], $overviewData);
            $overviewSheet->fromArray($overviewRows, null, 'A2');

            // Department Sheet
            $deptSheet = $spreadsheet->createSheet();
            $deptSheet->setTitle('Department Attendance');
            $deptSheet->fromArray([
                ['Department', 'Attendance Rate %']
            ]);

            $deptData = $this->getDepartmentAttendance($venue->id, $startDate);
            $deptRows = array_map(fn($item) => [
                $item['department'],
                $item['attendance']
            ], $deptData);
            $deptSheet->fromArray($deptRows, null, 'A2');

            // Insights Sheet
            $insightsSheet = $spreadsheet->createSheet();
            $insightsSheet->setTitle('Insights');

            $insights = $this->getInsights($venue->id, $startDate, $timeFrame);
            $insightsSheet->setCellValue('A1', 'Overall Attendance Rate');
            $insightsSheet->setCellValue('B1', $insights['overallRate'] . '%');

            if ($insights['bestDepartment']) {
                $insightsSheet->setCellValue('A2', 'Best Performing Department');
                $insightsSheet->setCellValue('B2', $insights['bestDepartment']['department']);
                $insightsSheet->setCellValue('C2', $insights['bestDepartment']['attendance'] . '%');
            }

            if ($insights['worstDepartment']) {
                $insightsSheet->setCellValue('A3', 'Department Needing Attention');
                $insightsSheet->setCellValue('B3', $insights['worstDepartment']['department']);
                $insightsSheet->setCellValue('C3', $insights['worstDepartment']['attendance'] . '%');
            }

            if ($insights['highestLateDay']) {
                $insightsSheet->setCellValue('A4', 'Day with Most Late Arrivals');
                $insightsSheet->setCellValue('B4', $insights['highestLateDay']);
                $insightsSheet->setCellValue('C4', 'Late Arrival Rate: ' . $insights['lateArrivalRate'] . '%');
            }

            // Auto-size columns for all sheets
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                foreach (range('A', $sheet->getHighestColumn()) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            }

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

        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => 'Failed to generate export'], 500);
        }
    }
}
