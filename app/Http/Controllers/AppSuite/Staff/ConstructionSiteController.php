<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VenueService;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use App\Models\ConstructionSite;
use App\Models\ConstructionSiteCheckInOut;
use App\Models\Address;
use App\Services\AppNotificationService;
use App\Models\ConstructionSiteTeam;
use Illuminate\Support\Facades\DB;
use App\Models\Task;
class ConstructionSiteController extends Controller
{

    protected VenueService $venueService;
    protected AppNotificationService $notificationService;
    public function __construct(VenueService $venueService, AppNotificationService $notificationService)
    {
        $this->venueService = $venueService;
        $this->notificationService = $notificationService;
    }
    /**
     * List all construction sites
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
       
        $perPage = $request->query('per_page', 10);
        $page = $request->query('page', 1); 
        try {
            $sites = ConstructionSite:: with(['address', 'manager'])
                                        ->where('venue_id', $authEmployee->restaurant_id)
                                        ->orderBy('created_at', 'desc')
                                        ->paginate($perPage, ['*'], 'page', $page);
            $activeSites = ConstructionSite::where('venue_id', $authEmployee->restaurant_id)->where('status', 'active')->count();
            $totalWorkers = ConstructionSite::where('venue_id', $authEmployee->restaurant_id)->sum('no_of_workers');
            
            $projectsCount = ConstructionSite::where('venue_id', $authEmployee->restaurant_id)->whereNotNull('app_project_id')->distinct('app_project_id')->count('app_project_id');
            return response()->json([
                'data' => $sites->items(),
                'total_workers' => $totalWorkers,
                'active_sites' => $activeSites,
                'projects_count' => $projectsCount,
                'pagination' => [
                    'total' => $sites->total(),
                    'per_page' => $sites->perPage(),
                    'current_page' => $sites->currentPage(),
                    'last_page' => $sites->lastPage(),
                    'from' => $sites->firstItem(),
                    'to' => $sites->lastItem()
                ],
                'message' => 'Construction sites retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error retrieving construction sites', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new construction site
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
       
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'app_project_id' => 'required|exists:app_projects,id',
                'type' => 'required|string',
                'status' => 'required|string|in:active,inactive,completed,on-hold,maintenance',
                'description' => 'nullable|string',
                'address' => 'required|string',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'manager' => 'required|exists:employees,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'no_of_workers' => 'required|integer',
                'team_id' => 'required|array|min:1',
                'team_id.*' => 'exists:teams,id',
            ]);

            $site = DB::transaction(function() use ($validated, $authEmployee) {
                $address = Address::create([
                    'address_line1' => $validated['address'],
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude']
                ]);
            
                unset($validated['address'], $validated['latitude'], $validated['longitude']);

                $validated['venue_id'] = $authEmployee->restaurant_id;
                $validated['address_id'] = $address->id;
                $site = ConstructionSite::create($validated);

                foreach($validated['team_id'] as $teamId){
                    ConstructionSiteTeam::create([
                        'construction_site_id' => $site->id,
                        'team_id' => $teamId
                    ]);
                }

                return $site;
            });

            if($site->manager){
                $employee = Employee::find($site->manager);
                $content = [
                    'construction_site_id' => (string)$site->id,
                ];
                $notification = $this->notificationService->sendNotification($employee, 'new_construction_site_assigned', 'You have been assigned to a new construction site: ' . $site->name, $content);
                if($notification){
                    $this->notificationService->sendPushNotificationToUser($employee, 'new_construction_site_assigned', 'You have been assigned to a new construction site: ' . $site->name, [
                            'construction_site_id' => (string)$site->id,
                            'venue_id' => (string)$authEmployee->restaurant_id,
                            'click_action' => 'construction_site_details',
                            'priority' => 'high'
                        ], $notification);
                }
            }
            return response()->json([
                'message' => 'Construction site created successfully',
                'data' => $site
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error creating construction site', 'error' => $e->getMessage()], 500);
        }
    }


    public function update(Request $request, $constructionSiteId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();
        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $constructionSite = ConstructionSite::find($constructionSiteId);
        if (!$constructionSite) {
            return response()->json(['message' => 'Construction site not found'], 404);
        }
        $constructionSite->update($request->all());
        return response()->json(['message' => 'Construction site updated successfully', 'data' => $constructionSite]);
    }

    public function destroy($constructionSiteId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();
        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        
        $constructionSite = ConstructionSite::find($constructionSiteId);
        if (!$constructionSite) {
            return response()->json(['message' => 'Construction site not found'], 404);
        }
        $constructionSite->delete();
        return response()->json(['message' => 'Construction site deleted successfully']);
    }

    // Check if any existing check in exists for the employee
    public function checkInExists(Request $request, $constructionSiteId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();
        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $checkIn = ConstructionSiteCheckInOut::where('employee_id', $employee->id)->where('construction_site_id', $constructionSiteId)->whereNull('check_out_time')->first();
        return response()->json(['message' => 'Check in exists', 'data' => $checkIn]);
    }

    public function checkIn(Request $request, $constructionSiteId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $validated = $request->validate([
            'location' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric'
        ]);
        $validated['employee_id'] = $employee->id;
        $validated['construction_site_id'] = $constructionSiteId;
        $validated['check_in_time'] = now();
        ConstructionSiteCheckInOut::create($validated);
        return response()->json(['message' => 'Check in successful']);
    }

    public function checkOut(Request $request, $constructionSiteId, $checkInId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $checkIn = ConstructionSiteCheckInOut::where('id', $checkInId)
                    ->where('construction_site_id', $constructionSiteId)
                    ->where('employee_id', $employee->id)
                    ->whereNull('check_out_time')
                    ->first();
        if (!$checkIn) {
            return response()->json(['message' => 'Check in not found'], 404);
        }
        $checkIn->check_out_time = now();
        $checkIn->save();
        return response()->json(['message' => 'Check out successful']);
    }

    public function show($id)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        try {
            $site = ConstructionSite::with(['address', 'manager'])
                                        ->where('id', $id)
                                        ->where('venue_id', $authEmployee->restaurant_id)
                                        ->firstOrFail();
            
            $site->report_incident_count = $site->reportIncidents()->count();
            $site->team_member_count = $site->teams()->with('team.employees')->get()->sum(function($siteTeam) {
                return $siteTeam->team->employees->count();
            });

            $site->task_count = $site->tasks()->count();
            $site->task_completed_count = $site->tasks()->where('status', 'done')->count();

            $employee = Employee::where('user_id', auth()->user()->id)->first();
            
            $site->current_tasks = Task::whereHas('assignedEmployees', function($query) use ($employee) {
                    $query->where('employee_id', $employee->id);
                })
                ->where('construction_site_id', $id)
                ->orderBy('created_at', 'desc')
                ->take(3)
                ->get(['id', 'name', 'description', 'priority', 'status', 'due_date']);
            return response()->json([
                'data' => $site,
                'message' => 'Construction sites retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error retrieving construction sites', 'error' => $e->getMessage()], 500);
        }
    }
}
