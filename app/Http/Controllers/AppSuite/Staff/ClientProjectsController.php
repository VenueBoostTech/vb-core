<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppProject;
use App\Models\AppClient;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ClientProjectsController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function getClientProjects(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        // Get query parameters
        $searchTerm = $request->input('search', '');
        $status = $request->input('status', 'all');
        $perPage = $request->input('per_page', 15);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        // Base query
        $query = AppProject::where('venue_id', $venue->id)
            ->where('project_category', 'client')
            ->with(['client', 'timeEntries', 'address']);

        // Apply search
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhereHas('client', function ($q) use ($searchTerm) {
                        $q->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('contact_person', 'like', "%{$searchTerm}%");
                    });
            });
        }

        // Apply status filter
        if ($status !== 'all') {
            $statusMap = [
                'active' => AppProject::STATUS_IN_PROGRESS,
                'completed' => AppProject::STATUS_COMPLETED,
                'on-hold' => AppProject::STATUS_ON_HOLD,
                'planning' => AppProject::STATUS_PLANNING,
                'cancelled' => AppProject::STATUS_CANCELLED
            ];

            if (isset($statusMap[$status])) {
                $query->where('status', $statusMap[$status]);
            }
        }

        // Get total counts for statistics
        $stats = [
            'active_projects' => $venue->projects()
                ->where('status', AppProject::STATUS_IN_PROGRESS)
                ->where('project_category', 'client')
                ->count(),
            'total_clients' => $venue->clients()->count(),
            'due_this_month' => $venue->projects()
                ->where('project_category', 'client')
                ->whereMonth('end_date', Carbon::now()->month)
                ->whereYear('end_date', Carbon::now()->year)
                ->count(),
            'previous_month_active' => $venue->projects()
                ->where('status', AppProject::STATUS_IN_PROGRESS)
                ->where('project_category', 'client')
                ->whereMonth('created_at', Carbon::now()->subMonth()->month)
                ->count()
        ];

        // Get paginated projects
        $projects = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        // Format the projects data
        $formattedProjects = $projects->map(function ($project) {
            $totalEstimatedHours = $project->estimated_hours ?? 0;
            $totalWorkedHours = $project->timeEntries->sum('duration') / 3600;
            $progress = $totalEstimatedHours > 0 ? min(100, ($totalWorkedHours / $totalEstimatedHours) * 100) : 0;

            $statusMap = [
                AppProject::STATUS_IN_PROGRESS => 'Active',
                AppProject::STATUS_COMPLETED => 'Completed',
                AppProject::STATUS_ON_HOLD => 'On Hold',
                AppProject::STATUS_PLANNING => 'Pending',
                AppProject::STATUS_CANCELLED => 'Cancelled'
            ];

            return [
                'id' => $project->id,
                'project_name' => $project->name,
                'client' => $project->client?->name ?? 'N/A',
                'client_id' => $project->client?->id, // Added client_id
                'start_date' => $project->start_date?->format('Y-m-d'),
                'due_date' => $project->end_date?->format('Y-m-d'),
                'progress' => round($progress, 2),
                'status' => $statusMap[$project->status] ?? 'Unknown',
                'contact_person' => $project->client?->contact_person,
                'type' => $project->client?->type ?? 'unknown',
                'email' => $project->client?->email,
                'phone' => $project->client?->phone,
                'full_address' => $project->address ? implode(', ', array_filter([
                    $project->address->address_line1,
                    $project->address->city,
                    $project->address->state,
                    $project->address->country,
                    $project->address->postcode
                ])) : null,
            ];
        });

        return response()->json([
            'projects' => $formattedProjects,
            'stats' => $stats,
            'pagination' => [
                'current_page' => $projects->currentPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
                'total_pages' => $projects->lastPage(),
            ]
        ]);
    }
}
