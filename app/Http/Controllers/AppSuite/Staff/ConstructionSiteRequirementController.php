<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use App\Models\ConstructionSiteRequirement;

class ConstructionSiteRequirementController extends Controller
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
        $type = $request->query('type', 'all');

        $constructionSiteRequirements = ConstructionSiteRequirement::with(['assigned'])->where('venue_id', $authEmployee->restaurant_id)
                                ->when($constructionSiteId, function ($query) use ($constructionSiteId) {
                                    return $query->where('construction_site_id', $constructionSiteId);
                                })
                                ->when($type !== 'all', function ($query) use ($type) {
                                    return $query->where('type', $type);
                                })
                                ->orderBy('created_at', 'desc')
                                ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'message' => 'Construction site requirements fetched successfully',
            'data' => $constructionSiteRequirements->items(),
            'pagination' => [
                'total' => $constructionSiteRequirements->total(),
                'per_page' => $constructionSiteRequirements->perPage(),
                'current_page' => $constructionSiteRequirements->currentPage(),
                'last_page' => $constructionSiteRequirements->lastPage(),
            ]
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
                'description' => 'nullable|string',
                'type' => 'required|string|in:site_specific,general',
                'status' => 'required|string|in:pending,in_progress,completed,complaint,action_required',
                'assigned_to' => 'required|exists:employees,id',
                'last_check_date' => 'nullable|date',
            ]);

            $validated['venue_id'] = $authEmployee->restaurant_id;
            $validated['construction_site_id'] = $constructionSiteId;
            
            $constructionSiteRequirement = ConstructionSiteRequirement::create($validated);
            return response()->json(['message' => 'Construction site requirement created successfully', 'data' => $constructionSiteRequirement], 201);
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

        $constructionSiteRequirement = ConstructionSiteRequirement::where('id', $id)
            ->where('venue_id', $authEmployee->restaurant_id)
            ->first();
        if (!$constructionSiteRequirement) return response()->json(['message' => 'Construction site requirement not found'], 404);

        $constructionSiteRequirement->update($request->all());
        return response()->json(['message' => 'Construction site requirement updated successfully', 'data' => $constructionSiteRequirement]);
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

        $constructionSiteRequirement = ConstructionSiteRequirement::where('id', $id)
            ->where('venue_id', $authEmployee->restaurant_id)
            ->first();
        if (!$constructionSiteRequirement) return response()->json(['message' => 'Construction site requirement not found'], 404);

        $constructionSiteRequirement->delete();
        return response()->json(['message' => 'Construction site requirement deleted successfully']);   
    }
}
