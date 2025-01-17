<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VenueService;
use App\Models\Equipment;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EmployeeEquipmentController extends Controller
{

    protected VenueService $venueService;
    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    /**
     * Display a listing of equipment.
     */
    public function index(Request $request)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
       
        $query = Equipment::with(['usageLogs' => function($query) {
                            $query->select('equipment_id', DB::raw('SUM(duration_minutes) as total_duration'))
                                ->groupBy('equipment_id');
                        }])
                        ->where('venue_id', $authEmployee->restaurant_id)
                        ->orderBy('created_at', 'desc');

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $list = $query->paginate($perPage, ['*'], 'page', $page);
        
        $activeEquipment = Equipment::where('venue_id', $authEmployee->restaurant_id)->where('status', Equipment::STATUS_AVAILABLE)->count();
        $totalPurchaseCost = Equipment::where('venue_id', $authEmployee->restaurant_id)->sum('purchase_cost');
        return response()->json([
            'data' => $list->items(),
            'active_equipment' => $activeEquipment,
            'total_purchase_cost' => $totalPurchaseCost,
            'pagination' => [
                'total' => $list->total(),
                'per_page' => $list->perPage(),
                'current_page' => $list->currentPage(),
                'last_page' => $list->lastPage(),
                'from' => $list->firstItem(),
                'to' => $list->lastItem(),
            ],
            'message' => 'Equipment fetched successfully'
        ]);
    }

    /**
     * Store a new equipment record.
     */
    public function store(Request $request)
    {

        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'status' => 'required|in:' . implode(',', [
                Equipment::STATUS_AVAILABLE,
                Equipment::STATUS_IN_USE,
                Equipment::STATUS_MAINTENANCE,
                Equipment::STATUS_RETIRED
            ]),
            'serial_number' => 'required|string|max:100|unique:equipment',
            'purchase_date' => 'required|date',
            'purchase_cost' => 'required|numeric',
            'maintenance_interval_days' => 'required|integer',
            'specifications' => 'nullable|array',
            'last_maintenance_date' => 'nullable|date',
            'next_maintenance_due' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'assigned_to' => 'nullable|exists:employees,id',
        ]);

        $validated['venue_id'] = $authEmployee->restaurant_id;
        $equipment = Equipment::create($validated);

        return response()->json([
            'message' => 'Equipment created successfully',
            'data' => $equipment
        ], 201);
    }

    /**
     * Update the specified equipment.
     */
    public function update(Request $request, $id)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $equipment = Equipment::find($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|max:100', 
            'model' => 'sometimes|string|max:100',
            'status' => 'sometimes|in:' . implode(',', [
                Equipment::STATUS_AVAILABLE,
                Equipment::STATUS_IN_USE,
                Equipment::STATUS_MAINTENANCE,
                Equipment::STATUS_RETIRED
            ]),
            'serial_number' => 'sometimes|string|max:100|unique:equipment,serial_number,' . $equipment->id,
            'purchase_date' => 'sometimes|date',
            'purchase_cost' => 'sometimes|numeric',
            'maintenance_interval_days' => 'sometimes|integer',
            'specifications' => 'sometimes|nullable|array',
            'last_maintenance_date' => 'sometimes|nullable|date',
            'next_maintenance_due' => 'sometimes|nullable|date',
            'location' => 'sometimes|nullable|string|max:255',
            'assigned_to' => 'sometimes|nullable|exists:employees,id',
        ]);

        $equipment->update($validated);

        return response()->json([
            'message' => 'Equipment updated successfully',
            'data' => $equipment
        ]);
    }

    /**
     * Remove the specified equipment.
     */
    public function destroy(Equipment $equipment)
    {
        if ($equipment->isCurrentlyAssigned()) {
            return response()->json([
                'message' => 'Cannot delete equipment that is currently assigned'
            ], 422);
        }

        $equipment->delete();

        return response()->json([
            'message' => 'Equipment deleted successfully'
        ]);
    }

    /**
     * Record maintenance for equipment
     */
    public function recordMaintenance(Request $request, Equipment $equipment)
    {
        $validated = $request->validate([
            'maintenance_date' => 'required|date',
            'maintenance_type' => 'required|string',
            'description' => 'required|string',
            'cost' => 'required|numeric',
            'performed_by' => 'required|string',
            'next_maintenance_due' => 'required|date'
        ]);

        $maintenance = $equipment->recordMaintenance($validated);

        return response()->json([
            'message' => 'Maintenance recorded successfully',
            'data' => $maintenance
        ]);
    }

    /**
     * Start equipment usage
     */
    public function startUsage(Request $request, Equipment $equipment)
    {
        if (!$equipment->isAvailable()) {
            return response()->json([
                'message' => 'Equipment is not available for use'
            ], 422);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'purpose' => 'required|string',
            'expected_duration' => 'required|integer'
        ]);

        try {
            $usage = $equipment->startUsage($validated);
            
            return response()->json([
                'message' => 'Equipment usage started successfully',
                'data' => $usage
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
