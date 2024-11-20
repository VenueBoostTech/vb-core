<?php

namespace App\Http\Controllers\AppSuite\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\LoginActivity;
use App\Models\StaffActivity;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogsController extends Controller
{
    protected $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function index(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        // Build query for staff activities
        $staffActivitiesQuery = StaffActivity::query()
            ->select(
                'id',
                'type as action',
                'employee_id',
                'created_at as timestamp',
                DB::raw("'staff' as source")
            )
            ->with(['employee:id,name'])
            ->where('venue_id', $venue->id);

        // Build query for login activities
        $loginActivitiesQuery = LoginActivity::query()
            ->select(
                'id',
                DB::raw("'User Login' as action"),
                'user_id as employee_id',
                'created_at as timestamp',
                DB::raw("'login' as source")
            )
            ->with(['user:id,name'])
            ->where('venue_id', $venue->id);

        // Apply filters
        if ($request->filled('action')) {
            if ($request->action === 'User Login') {
                $staffActivitiesQuery->where('id', 0); // No staff activities
            } else {
                $staffActivitiesQuery->where('type', $request->action);
                $loginActivitiesQuery->where('id', 0); // No login activities
            }
        }

        if ($request->filled('search')) {
            $staffActivitiesQuery->whereHas('employee', function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            });
            $loginActivitiesQuery->whereHas('user', function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('date')) {
            $date = Carbon::parse($request->date);
            $staffActivitiesQuery->whereDate('created_at', $date);
            $loginActivitiesQuery->whereDate('created_at', $date);
        }

        // Union the queries and paginate
        $allActivities = $staffActivitiesQuery->union($loginActivitiesQuery)
            ->orderBy('timestamp', 'desc')
            ->paginate($request->input('per_page', 10));

        // Format the activities
        $formattedActivities = $allActivities->through(function ($activity) {
            return [
                'id' => $activity->source . '_' . $activity->id,
                'action' => $activity->action,
                'user' => $activity->source === 'staff'
                    ? $activity->employee?->name
                    : $activity->user?->name,
                'timestamp' => $activity->timestamp,
                'details' => $activity->source === 'staff'
                    ? StaffActivity::find($activity->id)->getActivityDescription()
                    : "Login via " . LoginActivity::find($activity->id)->app_source,
                'severity' => $activity->source === 'staff'
                    ? $this->getSeverity($activity->action)
                    : 'Medium',
                'type' => $activity->source
            ];
        });

        // Get summary statistics
        $summary = [
            'total_logs' => StaffActivity::where('venue_id', $venue->id)->count()
                + LoginActivity::where('venue_id', $venue->id)->count(),
            'unique_users' => StaffActivity::where('venue_id', $venue->id)
                    ->distinct('employee_id')
                    ->count()
                + LoginActivity::where('venue_id', $venue->id)
                    ->distinct('user_id')
                    ->count(),
            'actions_today' => StaffActivity::where('venue_id', $venue->id)
                    ->whereDate('created_at', today())
                    ->count()
                + LoginActivity::where('venue_id', $venue->id)
                    ->whereDate('created_at', today())
                    ->count(),
            'critical_events' => StaffActivity::where('venue_id', $venue->id)
                ->whereIn('type', [
                    StaffActivity::TYPE_ISSUE_CREATE,
                    StaffActivity::TYPE_MEDIA_DELETE
                ])
                ->count()
        ];

        return response()->json([
            'logs' => $formattedActivities,
            'summary' => $summary,
            'pagination' => [
                'current_page' => $allActivities->currentPage(),
                'per_page' => $allActivities->perPage(),
                'total' => $allActivities->total(),
                'total_pages' => $allActivities->lastPage()
            ],
            'filters' => [
                'actions' => collect(StaffActivity::TYPES)->merge(['User Login'])->sort()->values()
            ]
        ]);
    }

    private function getSeverity(string $activityType): string
    {
        return match($activityType) {
            StaffActivity::TYPE_ISSUE_CREATE,
            StaffActivity::TYPE_MEDIA_DELETE => 'High',
            StaffActivity::TYPE_WORK_ORDER_CREATE,
            StaffActivity::TYPE_QUALITY_CREATE => 'Medium',
            default => 'Low'
        };
    }

    public function export(Request $request): StreamedResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $request->merge(['per_page' => 1000000]);
        $response = $this->index($request)->getData(true);
        $logs = $response['logs'];

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit_logs.csv"',
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Action', 'User', 'Timestamp', 'Details', 'Severity', 'Type']);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log['action'],
                    $log['user'],
                    $log['timestamp'],
                    $log['details'],
                    $log['severity'],
                    $log['type']
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
