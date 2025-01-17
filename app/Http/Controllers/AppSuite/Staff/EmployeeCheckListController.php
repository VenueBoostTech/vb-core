<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Services\VenueService;

class EmployeeCheckListController extends Controller
{
    protected VenueService $venueService;
    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }
    // Display a listing of the resource
    public function index($projectId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        
        $checklists = Checklist::where('project_id', $projectId)
                        ->where('venue_id', $authEmployee->restaurant_id)
                        ->with(['checklistItems' => function ($query) {
                            $query->with(['assignedTo' => function ($query) {
                                $query->select('id', 'name', 'email', 'profile_picture');
                            }])->select('id', 'name', 'is_completed', 'checklist_id', 'assigned_to');
                        }])
                        ->get()
                        ->map(function ($checklist) {
                            $totalItems = $checklist->checklistItems->count();
                            $completedItems = $checklist->checklistItems->where('is_completed', true)->count();
                            $checklist->total_items = $totalItems;
                            $checklist->completed_items = $completedItems;
                            $checklist->progress = $totalItems > 0 ? round(($completedItems / $totalItems) * 100, 2) : 0;
                            return $checklist;
                        });
        
        $totalItems = $checklists->sum(function ($checklist) {
            return $checklist->checklistItems->count();
        });
        $completedItems = $checklists->sum(function ($checklist) {
            return $checklist->checklistItems->where('is_completed', 1)->count();
        });
        return response()->json(['data' => $checklists, 'message' => 'Checklists fetched successfully', 'progress' => $completedItems / $totalItems * 100]);
    }

    // Store a newly created resource in storage
    public function store(Request $request, $projectId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $checklist = Checklist::create(array_merge($request->only('name', 'type'), ['project_id' => $projectId], ['venue_id' => $authEmployee->restaurant_id]));

        return response()->json(['message' => 'Checklist created successfully', 'data' => $checklist], 201);
    }


    // Update the specified resource in storage
    public function update(Request $request, $projectId, $id)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $checklist = Checklist::where('id', $id)->where('project_id', $projectId)->first();

        if (!$checklist) {
            return response()->json(['message' => 'Checklist not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|max:255',
            'items' => 'sometimes|required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $checklist->update($request->only('name', 'type', 'items'));

        return response()->json($checklist);
    }

    // Remove the specified resource from storage
    public function destroy($projectId, $id)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $checklist = Checklist::where('id', $id)->where('project_id', $projectId)->first();

        if (!$checklist) {
            return response()->json(['message' => 'Checklist not found'], 404);
        }

        $checklist->delete();

        return response()->json(['message' => 'Checklist deleted successfully']);
    }


    public function addCheckListItem(Request $request, $projectId, $checklistId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();

        $checklist = Checklist::where('id', $checklistId)->where('project_id', $projectId)->first();

        if (!$checklist) {
            return response()->json(['message' => 'Checklist not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'assigned_to' => 'required|string|max:255',
        ]);

        $checklistItem = ChecklistItem::create(array_merge($request->only('name', 'assigned_to'), ['checklist_id' => $checklist->id]));

        return response()->json(['message' => 'Checklist item added successfully', 'data' => $checklistItem], 201);
    }

    public function markAsCompletedUnCompleted($projectId, $checklistId, $itemId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();

        $checklist = Checklist::where('id', $checklistId)->where('project_id', $projectId)->first();

        if (!$checklist) {
            return response()->json(['message' => 'Checklist not found'], 404);
        }


        $checklistItem = ChecklistItem::where('id', $itemId)->where('checklist_id', $checklistId)->first();
        if(!$checklistItem){
            return response()->json(['message' => 'Checklist item not found'], 404);
        }
        if($checklistItem->is_completed)
        {
            $checklistItem->update(['is_completed' => 0]);
        }
        else
        {
            $checklistItem->update(['is_completed' => 1]);
        }
        return response()->json(['message' => 'Checklist item updated successfully'], 200);
    }
}
