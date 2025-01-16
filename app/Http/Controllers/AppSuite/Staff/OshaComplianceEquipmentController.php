<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VenueService;
use App\Models\OshaComplianceEquipment;
use Illuminate\Http\JsonResponse;

class OshaComplianceEquipmentController extends Controller
{

    protected VenueService $venueService;
    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $equipments = OshaComplianceEquipment::with(['assigned'])->where('venue_id', $authEmployee->restaurant_id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'data' => $equipments->items(), 
            'pagination' => [
                'total' => $equipments->total(),
                'per_page' => $equipments->perPage(),
                'current_page' => $equipments->currentPage(),
                'last_page' => $equipments->lastPage(),
            ],
            'message' => 'Equipment Compliance fetched successfully',
        ]);    
    }

    /*
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        try {
            $validated = $request->validate([
                'construction_site_id' => 'required|exists:construction_site,id',
                'title' => 'required|string|max:255',
                'last_inspection_date' => 'nullable|date',
                'next_inspection_date' => 'nullable|date', 
                'status' => 'nullable|string|in:compliant,non_compliant,pending',
                'requirements' => 'nullable|array',
                'required_actions' => 'nullable|array',
                'assigned_to' => 'nullable|exists:employees,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
        
        $validated['venue_id'] = $authEmployee->restaurant_id;
        $oshaComplianceEquipment = OshaComplianceEquipment::create($validated);
        return response()->json(['message' => 'Equipment Compliance created successfully', 'data' => $oshaComplianceEquipment], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
