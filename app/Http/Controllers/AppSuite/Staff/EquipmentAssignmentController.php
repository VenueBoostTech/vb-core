<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\VenueService;
use App\Models\EquipmentAssignment;
use App\Models\ConstructionSite;
use App\Models\Task;
use App\Models\Equipment;
class EquipmentAssignmentController extends Controller
{

    protected $venueService;

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
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $equipmentAssignments = EquipmentAssignment::with(['equipment', 'assignable', 'assignedEmployee'])
                                ->where('venue_id', $authEmployee->restaurant_id);
        if ($search) {
            $equipmentAssignments->where(function($query) use ($search) {
                $query->whereHas('equipment', function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                })
                ->orWhereHas('assignable', function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                })
                ->orWhereHas('assignedEmployee', function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                });
            });
        }
        $equipmentAssignments = $equipmentAssignments->orderBy($sortBy, $sortOrder)
                                ->paginate($perPage, ['*'], 'page', $page);

        $activeAssignments = EquipmentAssignment::where('venue_id', $authEmployee->restaurant_id)
            ->where('status', EquipmentAssignment::STATUS_ACTIVE)
            ->count();
        $unassignedEquipments = Equipment::where('venue_id', $authEmployee->restaurant_id)
            ->whereDoesntHave('assignments', function($query) {
                $query->where('status', EquipmentAssignment::STATUS_ACTIVE);
            })
            ->count();

        return response()->json([
            'message' => 'Equipment assignments fetched successfully',
            'data' => $equipmentAssignments->items(),
            'active_assignments' => $activeAssignments,
            'available_operator' => 0,
            'unassigned_equipments' => $unassignedEquipments,
            'pagination' => [
                'total' => $equipmentAssignments->total(),
                'per_page' => $equipmentAssignments->perPage(),
                'current_page' => $equipmentAssignments->currentPage(),
                'last_page' => $equipmentAssignments->lastPage()
            ]
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        try {
            $validated = $request->validate([
                'construction_site_id' => 'required|exists:construction_site,id',
                'equipment_id' => 'required|exists:equipment,id',
                'assigned_to' => 'required|exists:employees,id',
                'assigned_at' => 'required|date',
                'return_expected_at' => 'nullable|date',
                'returned_at' => 'nullable|date', 
                'status' => 'required|string',
                'notes' => 'nullable|string',
            ]);

            $validated['venue_id'] = $authEmployee->restaurant_id;
            $equipmentAssignment = new EquipmentAssignment($validated);
            $equipmentAssignment->assignable()->associate(
                ConstructionSite::findOrFail($validated['construction_site_id'])
            );
            $equipmentAssignment->save();

            return response()->json([
                'message' => 'Equipment assignment created successfully',
                'data' => $equipmentAssignment
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error creating construction site', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {

        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        try {
            $equipmentAssignment = EquipmentAssignment::findOrFail($id);
            $equipmentAssignment->update($request->all());
            return response()->json(['message' => 'Equipment assignment updated successfully', 'data' => $equipmentAssignment], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error updating equipment assignment', 'error' => $e->getMessage()], 500);
        }
    }

   
}
