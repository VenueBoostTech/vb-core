<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VenueService;
use App\Models\ConstructionSiteSafetyChecklist;
use Illuminate\Http\JsonResponse;
use App\Models\ConstructionSiteSafetyChecklistItem;

class ConstructionSiteSafetyChecklistController extends Controller
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
    public function index(Request $request, $constructionSiteId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $perPage = $request->query('per_page', 10);
        $page = $request->query('page', 1); 

        $constructionSiteSafetyChecklists = ConstructionSiteSafetyChecklist::with(['assigned:id,name', 'items'])
                                ->where('venue_id', $authEmployee->restaurant_id)
                                ->when($constructionSiteId, function ($query) use ($constructionSiteId) {
                                    return $query->where('construction_site_id', $constructionSiteId);
                                })
                                ->withCount(['items as total_items'])
                                ->withCount(['items as completed_items' => function ($query) {
                                    $query->where('is_completed', true);
                                }])
                                ->orderBy('created_at', 'desc')
                                ->paginate($perPage, ['*'], 'page', $page);

        $constructionSiteSafetyChecklists->each(function ($checklist) {
            $checklist->progress = $checklist->total_items > 0 
                ? round(($checklist->completed_items / $checklist->total_items) * 100) 
                : 0;
        });

        return response()->json([
            'data' => $constructionSiteSafetyChecklists->items(),
            'pagination' => [
                'total' => $constructionSiteSafetyChecklists->total(),
                'per_page' => $constructionSiteSafetyChecklists->perPage(),
                'current_page' => $constructionSiteSafetyChecklists->currentPage(),
                'last_page' => $constructionSiteSafetyChecklists->lastPage(),
            ],
            'message' => 'Construction site safety checklists fetched successfully',
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request, $constructionSiteId)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'assigned_to' => 'required|exists:employees,id',
                'due_date' => 'required|date',
                'items' => 'required|array',
                'items.*.title' => 'required|string|max:255',
                'items.*.is_completed' => 'required|boolean',
            ]);

            $validated['venue_id'] = $authEmployee->restaurant_id;
            $validated['construction_site_id'] = $constructionSiteId;

            $constructionSiteSafetyChecklist = ConstructionSiteSafetyChecklist::create($validated);
            foreach ($validated['items'] as $item) {
                ConstructionSiteSafetyChecklistItem::create([
                    'title' => $item['title'],
                    'is_completed' => $item['is_completed'],
                    'checklist_id' => $constructionSiteSafetyChecklist->id
                ]);
            }
            
            return response()->json([
                'message' => 'Construction site safety checklist created successfully', 
                'data' => $constructionSiteSafetyChecklist
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error creating construction site requirement', 'error' => $e->getMessage()], 500);
        }
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
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'assigned_to' => 'required|exists:employees,id',
                'due_date' => 'required|date',
                'items' => 'required|array',
                'items.*.id' => 'nullable|exists:construction_site_safety_checklist_items,id',
                'items.*.title' => 'required|string|max:255',
                'items.*.is_completed' => 'required|boolean',
            ]);

            $constructionSiteSafetyChecklist = ConstructionSiteSafetyChecklist::findOrFail($id);
            $constructionSiteSafetyChecklist->update($validated);
            // Get existing item IDs
            $existingItemIds = $constructionSiteSafetyChecklist->items->pluck('id')->toArray();
            
            // Track processed item IDs
            $processedItemIds = [];
            
            foreach ($validated['items'] as $item) {
                if (isset($item['id'])) {
                    // Update existing item
                    ConstructionSiteSafetyChecklistItem::where('id', $item['id'])
                        ->update([
                            'title' => $item['title'],
                            'is_completed' => $item['is_completed']
                        ]);
                    $processedItemIds[] = $item['id'];
                } else {
                    ConstructionSiteSafetyChecklistItem::create([
                        'title' => $item['title'],
                        'is_completed' => $item['is_completed'],
                        'checklist_id' => $constructionSiteSafetyChecklist->id
                    ]);
                }
            }
            
            // Delete items that weren't in the update
            $itemsToDelete = array_diff($existingItemIds, $processedItemIds);
            if (!empty($itemsToDelete)) {
                ConstructionSiteSafetyChecklistItem::whereIn('id', $itemsToDelete)->delete();
            }
            
            
            return response()->json(['message' => 'Construction site safety checklist updated successfully', 'data' => $constructionSiteSafetyChecklist]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error updating construction site safety checklist', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $constructionSiteSafetyChecklist = ConstructionSiteSafetyChecklist::findOrFail($id);
        $constructionSiteSafetyChecklist->delete();

        return response()->json(['message' => 'Construction site safety checklist deleted successfully']);
    }

    /**
     * Update completion status of a checklist item
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateItemStatus(Request $request, $checkListId,$id)
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        try {
            $validated = $request->validate([
                'is_completed' => 'required|boolean'
            ]);

            $checklistItem = ConstructionSiteSafetyChecklistItem::where('checklist_id', $checkListId)->findOrFail($id);
            $checklistItem->update([
                'is_completed' => $validated['is_completed']
            ]);

            return response()->json([
                'message' => 'Checklist item status updated successfully',
                'data' => $checklistItem
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error updating checklist item status', 'error' => $e->getMessage()], 500);
        }
    }

}
