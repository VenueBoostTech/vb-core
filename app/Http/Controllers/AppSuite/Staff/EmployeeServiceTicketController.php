<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VenueService;;

use App\Models\Employee;
use App\Models\ServiceTicket;
use App\Models\AppProject;
use App\Models\Service;

use Illuminate\Support\Facades\Storage;

class EmployeeServiceTicketController extends Controller
{

    protected VenueService $venueService;
    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }


    public function getServiceList()
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $services = Service::where('venue_id', $authEmployee->restaurant_id)
                    ->select('id', 'name')
                    ->get();
        return response()->json(['data' => $services, 'message' => 'Services fetched successfully']);
    }

    /**
     * Display a listing of service tickets.
     */
    public function index(Request $request,$projectId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $project = AppProject::find($projectId);
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }
        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 10);

        $serviceTickets = ServiceTicket::with(['service' => function($query) {
                            $query->select('id', 'name');
                        }, 'project' => function($query) {
                            $query->select('id', 'name');
                        }])
                        ->where('app_project_id', $projectId)
                        ->where('venue_id', $authEmployee->restaurant_id)
                        ->select('id', 'ticket_number', 'status', 'service_id', 'app_project_id', 'service_description', 'materials_used', 'venue_id', 'client_id', 'created_at', 'updated_at')
                        ->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'data' => $serviceTickets->items(),
            'pagination' => [
                'current_page' => $serviceTickets->currentPage(),
                'per_page' => $serviceTickets->perPage(),
                'total' => $serviceTickets->total(),
                'total_pages' => $serviceTickets->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created service ticket in storage.
     */
    public function store(Request $request, $projectId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $project = AppProject::find($projectId);
        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $validated = $request->validate([
            'service_id' => 'required|integer',
            'assigned_to' => 'nullable|integer',
            'service_description' => 'required|string',
            'materials_used' => 'required|string',
            'photos' => 'nullable|file',
            'photo_type' => 'required|string',
        ]);

        $validated['venue_id'] = $authEmployee->restaurant_id;
        $validated['status'] = ServiceTicket::STATUS_SCHEDULED;
        $validated['app_project_id'] = $project->id;
        $validated['client_id'] = $project->client_id;
        $validated['ticket_number'] = ServiceTicket::generateNumber();


        $serviceTicket = ServiceTicket::create($validated);
        if($serviceTicket){
            // Upload photos
            if($request->hasFile('photos')){
                foreach($request->file('photos') as $photo){
                    // S3 Upload 
                    $path = Storage::disk('s3')->putFile('project_service_ticket/images', $photo);

                    $photoPath = $photo->store('service-tickets', 'public');
                    ServiceTicketPhoto::create(['service_ticket_id' => $serviceTicket->id, 'photo_path' => $path, 'photo_type' => $request->photo_type]);
                }
            }
            return response()->json(['message' => 'Service ticket created successfully', 'data' => $serviceTicket], 201);
        } else {
            return response()->json(['message' => 'Service ticket creation failed'], 400);
        }
        
    }

    /**
     * Display the specified service ticket.
     */
    public function show($id)
    {
        $serviceTicket = ServiceTicket::findOrFail($id);
        return response()->json($serviceTicket);
    }

    /**
     * Update the specified service ticket in storage.
     */
    public function update(Request $request, $id)
    {
        $serviceTicket = ServiceTicket::findOrFail($id);

        $validated = $request->validate([
            'venue_id' => 'sometimes|integer',
            'client_id' => 'sometimes|integer',
            'service_id' => 'sometimes|integer',
            'service_request_id' => 'nullable|integer',
            'app_project_id' => 'nullable|integer',
            'assigned_to' => 'nullable|integer',
            'status' => 'sometimes|string|in:' . implode(',', [
                ServiceTicket::STATUS_SCHEDULED,
                ServiceTicket::STATUS_IN_PROGRESS,
                ServiceTicket::STATUS_COMPLETED,
                ServiceTicket::STATUS_PENDING_SIGN_OFF,
                ServiceTicket::STATUS_SIGNED_OFF,
                ServiceTicket::STATUS_CANCELLED,
            ]),
            'service_description' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
        ]);

        $serviceTicket->update($validated);
        return response()->json($serviceTicket);
    }

    /**
     * Remove the specified service ticket from storage.
     */
    public function destroy($id)
    {
        $serviceTicket = ServiceTicket::findOrFail($id);
        $serviceTicket->delete();
        return response()->json(['message' => 'Service ticket deleted successfully.']);
    }
}
