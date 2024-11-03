<?php

namespace App\Http\Controllers\v3;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Task;
use App\Models\StaffActivity;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductivityController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
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
            'productivityTrend' => $this->getProductivityTrend($venue->id, $startDate),
            'departmentProductivity' => $this->getDepartmentProductivity($venue->id, $startDate),
            'topPerformers' => $this->getTopPerformers($venue->id, $startDate),
            'insights' => $this->getInsights($venue->id, $startDate, $timeFrame)
        ]);
    }

    private function getProductivityTrend(int $venueId, Carbon $startDate): array
    {
        return Task::where('venue_id', $venueId)
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total_tasks'),
                DB::raw("SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed_tasks")
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($day) {
                return [
                    'date' => $day->date,
                    'productivity' => $day->total_tasks > 0
                        ? round(($day->completed_tasks / $day->total_tasks) * 100, 1)
                        : 0
                ];
            })
            ->toArray();
    }

    private function getDepartmentProductivity(int $venueId, Carbon $startDate): array
    {
        return StaffActivity::where('staff_activities.venue_id', $venueId)
            ->where('staff_activities.created_at', '>=', $startDate)
            ->join('employees', 'staff_activities.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->select(
                'departments.name as department',
                DB::raw('COUNT(*) as total_activities'),
                DB::raw('COUNT(DISTINCT employees.id) as active_employees'),
                DB::raw('COUNT(DISTINCT DATE(staff_activities.created_at)) as active_days'),
                DB::raw('COUNT(CASE WHEN type LIKE "%create%" THEN 1 END) as completed_tasks')
            )
            ->groupBy('departments.id', 'departments.name')
            ->get()
            ->map(function ($dept) use ($startDate) {
                $totalPossibleDays = now()->diffInDays($startDate);
                return [
                    'department' => $dept->department,
                    'productivity' => round(
                        (($dept->total_activities / ($dept->active_employees * $totalPossibleDays)) * 0.4 +
                            ($dept->active_days / $totalPossibleDays) * 0.3 +
                            ($dept->completed_tasks / $dept->total_activities) * 0.3) * 100
                    )
                ];
            })
            ->toArray();
    }

    private function getTopPerformers(int $venueId, Carbon $startDate): array
    {
        return StaffActivity::where('staff_activities.venue_id', $venueId)
            ->where('staff_activities.created_at', '>=', $startDate)
            ->join('employees', 'staff_activities.employee_id', '=', 'employees.id')
            ->select(
                'employees.id',
                'employees.name',
                DB::raw('COUNT(*) as total_activities'),
                DB::raw('COUNT(DISTINCT DATE(staff_activities.created_at)) as active_days'),
                DB::raw('COUNT(CASE WHEN type LIKE "%create%" THEN 1 END) as completed_tasks')
            )
            ->groupBy('employees.id', 'employees.name')
            ->havingRaw('COUNT(*) > 0') // Only include active employees
            ->get()
            ->map(function ($employee) use ($startDate) {
                $totalPossibleDays = now()->diffInDays($startDate);
                return [
                    'name' => $employee->name,
                    'productivity' => round(
                        (($employee->total_activities / $totalPossibleDays) * 0.4 +
                            ($employee->active_days / $totalPossibleDays) * 0.3 +
                            ($employee->completed_tasks / $employee->total_activities) * 0.3) * 100
                    )
                ];
            })
            ->sortByDesc('productivity')
            ->take(5)
            ->values()
            ->toArray();
    }

    private function getInsights(int $venueId, Carbon $startDate, string $timeFrame): array
    {
        // Calculate current period productivity
        $currentPeriodStats = $this->getPeriodStats($venueId, $startDate, now());

        // Calculate previous period productivity
        $previousPeriodStart = $startDate->copy()->subMonth();
        $previousPeriodStats = $this->getPeriodStats($venueId, $previousPeriodStart, $startDate);

        // Get department stats
        $departmentStats = $this->getDepartmentProductivity($venueId, $startDate);
        $bestDepartment = collect($departmentStats)->sortByDesc('productivity')->first();
        $worstDepartment = collect($departmentStats)->sortBy('productivity')->first();

        // Get top performers
        $topPerformers = $this->getTopPerformers($venueId, $startDate);
        $bestPerformers = array_slice($topPerformers, 0, 2);

        return [
            'productivityChange' => $previousPeriodStats['productivity'] > 0
                ? round($currentPeriodStats['productivity'] - $previousPeriodStats['productivity'], 1)
                : 0,
            'timeFrame' => $timeFrame,
            'bestDepartment' => $bestDepartment ?? null,
            'improvementTarget' => $worstDepartment ?? null,
            'topPerformers' => $bestPerformers
        ];
    }

    private function getPeriodStats(int $venueId, Carbon $start, Carbon $end): array
    {
        $tasks = Task::where('venue_id', $venueId)
            ->whereBetween('created_at', [$start, $end])
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed")
            )
            ->first();

        return [
            'productivity' => $tasks->total > 0 ? round(($tasks->completed / $tasks->total) * 100, 1) : 0
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

            // Productivity Trend Sheet
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Productivity Trend');
            $sheet->fromArray([
                ['Date', 'Productivity %']
            ]);

            $trendData = $this->getProductivityTrend($venue->id, $startDate);
            $dailyData = array_map(fn($item) => [
                $item['date'],
                $item['productivity']
            ], $trendData);
            $sheet->fromArray($dailyData, null, 'A2');

            // Department Productivity Sheet
            $deptSheet = $spreadsheet->createSheet();
            $deptSheet->setTitle('Department Productivity');
            $deptSheet->fromArray([
                ['Department', 'Productivity %']
            ]);

            $deptData = $this->getDepartmentProductivity($venue->id, $startDate);
            $deptRows = array_map(fn($item) => [
                $item['department'],
                $item['productivity']
            ], $deptData);
            $deptSheet->fromArray($deptRows, null, 'A2');

            // Top Performers Sheet
            $performersSheet = $spreadsheet->createSheet();
            $performersSheet->setTitle('Top Performers');
            $performersSheet->fromArray([
                ['Employee Name', 'Productivity %']
            ]);

            $performersData = $this->getTopPerformers($venue->id, $startDate);
            $performerRows = array_map(fn($item) => [
                $item['name'],
                $item['productivity']
            ], $performersData);
            $performersSheet->fromArray($performerRows, null, 'A2');

            // Insights Sheet
            $insightsSheet = $spreadsheet->createSheet();
            $insightsSheet->setTitle('Insights');

            $insights = $this->getInsights($venue->id, $startDate, $timeFrame);
            $insightsSheet->setCellValue('A1', 'Productivity Change');
            $insightsSheet->setCellValue('B1', ($insights['productivityChange'] >= 0 ? '+' : '') .
                $insights['productivityChange'] . '% from previous ' . $timeFrame);

            if ($insights['bestDepartment']) {
                $insightsSheet->setCellValue('A2', 'Best Performing Department');
                $insightsSheet->setCellValue('B2', $insights['bestDepartment']['department']);
                $insightsSheet->setCellValue('C2', $insights['bestDepartment']['productivity'] . '%');
            }

            if ($insights['improvementTarget']) {
                $insightsSheet->setCellValue('A3', 'Department Needing Improvement');
                $insightsSheet->setCellValue('B3', $insights['improvementTarget']['department']);
                $insightsSheet->setCellValue('C3', $insights['improvementTarget']['productivity'] . '%');
            }

            // Auto-size columns for all sheets
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                foreach (range('A', $sheet->getHighestColumn()) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            }

            // Create file
            $fileName = "productivity-report-{$timeFrame}-" . now()->format('Y-m-d') . '.xlsx';
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
