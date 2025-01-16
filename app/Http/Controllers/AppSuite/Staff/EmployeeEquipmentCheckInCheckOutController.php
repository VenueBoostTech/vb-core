<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Services\VenueService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EquipmentCheckInCheckOutProcess;
use App\Models\Employee;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
class EmployeeEquipmentCheckInCheckOutController extends Controller
{


    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }
    /**
     * Store a new equipment check in/out process
     */
    public function store(Request $request, $projectId, $equipmentId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $validated = $request->validate([
            'type' => 'required|in:check_in,check_out',
            'notes' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg|max:2048'
        ]);

        $validated['venue_id'] = $authEmployee->restaurant_id;
        $validated['employee_id'] = $employee->id;
        $validated['equipment_id'] = $equipmentId;
        $process = EquipmentCheckInCheckOutProcess::create($validated);
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = Storage::disk('public')->put('equipment-checks', $photo);
                $process->photos()->create([
                    'photo' => $path
                ]);
            }
        }

        return response()->json([
            'message' => 'Equipment check process created successfully',
            'data' => $process
        ], 201);
    }

}
