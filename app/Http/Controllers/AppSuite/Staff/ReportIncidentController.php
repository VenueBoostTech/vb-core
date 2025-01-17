<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VenueService;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use App\Models\ReportIncident;
use Illuminate\Support\Facades\Storage;
class ReportIncidentController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function index(Request $request)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();
        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }
       
        $perPage = $request->query('per_page', 10);
        $page = $request->query('page', 1); 
        $reportIncidents = ReportIncident::with(['constructionSite' => function($query) {
            $query->select('id', 'name');
        }])->where('venue_id', $authEmployee->restaurant_id)
                            ->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'message' => 'Report incidents fetched successfully',
            'data' => $reportIncidents->items(),
            'pagination' => [
                'total' => $reportIncidents->total(),
                'per_page' => $reportIncidents->perPage(),
                'current_page' => $reportIncidents->currentPage(),
                'last_page' => $reportIncidents->lastPage(),
            ]
        ]);
    }

    public function store(Request $request, $constructionSiteId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();
        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        
        // Validation
        $validated = $request->validate([
            'type_of_incident' => 'required|string',
            'date_time' => 'required|date',
            'location' => 'required|string',
            'description' => 'required|string',
            'person_involved' => 'required|string',
            'taken_action' => 'required|string',
            'status' => 'nullable|string',
            'photos' => 'nullable|array',
            'withness_statement' => 'required|string',
            'weather_condition' => 'required|string',
            'lighting_condition' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        
        if ($request->hasFile('photos')) {
            $photos = [];
            foreach ($request->file('photos') as $photo) {
                $path = Storage::disk('s3')->put('photos', $photo);
                $photos[] = $path;
            }
            $validated['photos'] = implode(',', $photos);
        }

        
        $validated['employee_id'] = $employee->id;
        $validated['venue_id'] = $authEmployee->restaurant_id;
        $validated['construction_site_id'] = $constructionSiteId;
        $reportIncident = ReportIncident::create($validated);

        return response()->json([
            'message' => 'Report incident created successfully',
            'data' => $reportIncident,
        ], 201);
    }
    
}
