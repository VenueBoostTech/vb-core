<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppProject;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Comment;
use App\Models\ChatConversation;
use App\Models\Employee;
use App\Models\Restaurant;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmployeeProjectController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    private function getInitials($name): string
    {
        $words = explode(' ', $name);
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return substr($initials, 0, 2);
    }

    private function formatEmployeeData($employee, $role, $assignedAt = null): array
    {
        $data = [
            'id' => $employee->id,
            'name' => $employee->name,
            'avatar' => $employee->profile_picture
                ? Storage::disk('s3')->temporaryUrl($employee->profile_picture, '+5 minutes')
                : $this->getInitials($employee->name),
            'role' => $role
        ];

        if ($assignedAt) {
            $data['assigned_at'] = $assignedAt;
        }

        return $data;
    }

    public function index(Request $request): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();


        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');


        $projectsQuery = $employee->assignedProjects()
            ->with(['projectManager:id,name,profile_picture', 'timeEntries'])
            ->select([
                'app_projects.id',
                'app_projects.name',
                'app_projects.status',
                'app_projects.start_date',
                'app_projects.end_date',
                'app_projects.estimated_hours'
            ])->orderBy('app_projects.start_date', 'desc');

        if ($search) {
            $projectsQuery->where('app_projects.name', 'like', "%{$search}%");
        }

        $projects = $projectsQuery->paginate($perPage);

        $formattedProjects = $projects->map(function ($project) {
            // Calculate completion percentage based on time entries
            $totalHours = $project->estimated_hours ?? 0;
            $spentHours = $project->timeEntries->sum('hours');
            $completionPercentage = $totalHours > 0
                ? min(round(($spentHours / $totalHours) * 100), 100)
                : 0;

            return [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'start_date' => $project->start_date ? $project->start_date->format('Y-m-d') : null,  // Format date
                'end_date' => $project->end_date ? $project->end_date->format('Y-m-d') : null,      // Format date
                'project_manager' => $project->projectManager ? [
                    'id' => $project->projectManager->id,
                    'name' => $project->projectManager->name,
                    'avatar' => $project->projectManager->profile_picture
                        ? Storage::disk('s3')->temporaryUrl($project->projectManager->profile_picture, '+5 minutes')
                        : $this->getInitials($project->projectManager->name)
                ] : null,
                'time_entries' => [
                    'estimated_hours' => $project->estimated_hours,
                    'spent_hours' => $spentHours
                ],
                'completion_percentage' => $completionPercentage
            ];
        });

        return response()->json([
            'projects' => $formattedProjects,
            'current_page' => $projects->currentPage(),
            'per_page' => $projects->perPage(),
            'total' => $projects->total(),
            'total_pages' => $projects->lastPage(),
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $authEmployee = $this->venueService->employee();
            if ($authEmployee instanceof JsonResponse) return $authEmployee;

            $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
            if (!$venue instanceof Restaurant) {
                return response()->json(['error' => 'Project not found'], 404);
            }

            $project = AppProject::where('venue_id', $venue->id)->with([
                                    'assignedEmployees:id,name,profile_picture',
                                    'projectManager:id,name,profile_picture',
                                    'teamLeaders:id,name,profile_picture',
                                    'operationsManagers:id,name,profile_picture',
                                    'timeEntries'
                                ])
                                ->find($id);
            if (!$project) {
                return response()->json(['error' => 'Project not found'], 404);
            }

            $employee = Employee::where('user_id', auth()->user()->id)->first();
            if (!$employee) {
                return response()->json(['error' => 'Employee not found'], 404);
            }

            // $project = $employee->assignedProjects()
            //     ->with([
            //         'assignedEmployees:id,name,profile_picture',
            //         'projectManager:id,name,profile_picture',
            //         'teamLeaders:id,name,profile_picture',
            //         'operationsManagers:id,name,profile_picture',
            //         'timeEntries'
            //     ])
            //     ->findOrFail($id);
            
            // Calculate completion percentage
            $totalHours = $project->estimated_hours ?? 0;
            $spentHours = $project->timeEntries->sum('hours');
            $completionPercentage = $totalHours > 0
                ? min(round(($spentHours / $totalHours) * 100), 100)
                : 0;

            // Get checklist statistics
            $checklistStats = $this->getChecklistStatistics($project->id);

            // Get comments count
            $commentsCount = Comment::where('project_id', $project->id)
                ->whereNull('parent_id')
                ->count();

            // Get chat conversations count
            $chatCount = 0;

            // Format project manager
            $projectManager = $project->projectManager
                ? $this->formatEmployeeData($project->projectManager, 'Project Manager')
                : null;

            // Format project team (without project manager)
            $projectTeam = [
                'team_leaders' => $project->teamLeaders->map(function ($leader) {
                    return $this->formatEmployeeData($leader, 'Team Leader');
                }),
                'operations_managers' => $project->operationsManagers->map(function ($manager) {
                    return $this->formatEmployeeData($manager, 'Operations Manager');
                }),
                'team_members' => $project->assignedEmployees->map(function ($member) {
                    return $this->formatEmployeeData($member, 'Team Member', $member->pivot->assigned_at);
                })
            ];

            // Build response
            $response = [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'start_date' => $project->start_date ? $project->start_date->format('Y-m-d') : null,
                'end_date' => $project->end_date ? $project->end_date->format('Y-m-d') : null,
                'description' => $project->description,
                'estimated_hours' => $project->estimated_hours,
                'estimated_budget' => $project->estimated_budget,
                'project_type' => $project->project_type,
                'project_category' => $project->project_category,
                'completion_percentage' => $completionPercentage,
                'time_entries' => [
                    'estimated_hours' => $totalHours,
                    'spent_hours' => $spentHours
                ],
                'project_manager' => $projectManager,  // Put at root level
                'team' => $projectTeam,  // Team without project manager
                'shortcuts' => [
                    'checklists' => $checklistStats['formatted'],
                    'checklist_completion_percentage' => $checklistStats['percentage'],
                    'comments' => $commentsCount,
                    'chats' => $chatCount
                ]
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error retrieving project', 'error' => $e->getMessage()], 500);
        }
    }

    private function getChecklistStatistics(int $projectId): array
    {
        $checklists = Checklist::where('project_id', $projectId)->get();

        $totalItems = 0;
        $completedItems = 0;

        foreach ($checklists as $checklist) {
            $items = ChecklistItem::where('checklist_id', $checklist->id);

            $totalItems += $items->count();
            $completedItems += $items->where('status', 'completed')->count();
        }

        return [
            'formatted' => $totalItems > 0 ? "$completedItems/$totalItems" : "0/0",
            'percentage' => $totalItems > 0 ? round(($completedItems / $totalItems) * 100) : 0
        ];
    }
}
