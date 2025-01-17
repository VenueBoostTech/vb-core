<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VenueService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use App\Models\ConstructionSiteIssue;

class ConstructionSiteIssueController extends Controller
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
        $status = $request->query('status', 'all');
        try {

            $constructionSiteIssues = ConstructionSiteIssue::where('venue_id', $authEmployee->restaurant_id)
                                ->where('construction_site_id', $constructionSiteId)
                                ->when($status !== 'all', function ($query) use ($status) {
                                    return $query->where('status', $status);
                                })
                                ->orderBy('created_at', 'desc')
                                ->paginate($perPage, ['*'], 'page', $page);

            $issuesCounts = ConstructionSiteIssue::where('venue_id', $authEmployee->restaurant_id)
                                ->where('construction_site_id', $constructionSiteId)
                                ->selectRaw('
                                    SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END) as open_count,
                                    SUM(CASE WHEN status = "resolved" THEN 1 ELSE 0 END) as resolved_count
                                ')
                                ->first();

            $openIssuesCount = $issuesCounts->open_count;
            $resolvedIssuesCount = $issuesCounts->resolved_count;
            return response()->json([
                'data' => $constructionSiteIssues->items(),
                'pagination' => [
                    'total' => $constructionSiteIssues->total(),
                    'per_page' => $constructionSiteIssues->perPage(),
                    'current_page' => $constructionSiteIssues->currentPage(),
                    'last_page' => $constructionSiteIssues->lastPage(),
                ],
                'open_issues_count' => $openIssuesCount,
                'resolved_issues_count' => $resolvedIssuesCount,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error fetching construction site issues', 'error' => $e->getMessage()], 500);
        }
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
                'location' => 'required|string|max:255',
                'description' => 'nullable|string',
                'priority' => 'required|string|in:low,medium,high,critical',
                'status' => 'required|string|in:open,in-progress,resolved',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $path = Storage::disk('s3')->put('construction_site_issues', $image);
                $validated['image'] = $path;
            }
            $validated['venue_id'] = $authEmployee->restaurant_id;
            $validated['construction_site_id'] = $constructionSiteId;
            $constructionSiteIssue = ConstructionSiteIssue::create($validated);
            return response()->json(['message' => 'Construction site issue created successfully', 'data' => $constructionSiteIssue], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error creating construction site', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
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
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
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
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        try {
            $constructionSiteIssue = ConstructionSiteIssue::where('id', $id)
                ->where('venue_id', $authEmployee->restaurant_id)
                ->firstOrFail();

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'required|string|in:open,in-progress,resolved',
                'priority' => 'required|string|in:low,medium,high,critical',
            ]);

            $constructionSiteIssue->update($validated);

            return response()->json([
                'message' => 'Construction site issue updated successfully', 
                'data' => $constructionSiteIssue
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error updating construction site issue', 'error' => $e->getMessage()], 500);
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

        try {
            $constructionSiteIssue = ConstructionSiteIssue::where('id', $id)
                ->where('venue_id', $authEmployee->restaurant_id)
                ->firstOrFail();
            $constructionSiteIssue->delete();
            return response()->json(['message' => 'Construction site issue deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error deleting construction site issue', 'error' => $e->getMessage()], 500);
        }
    }
}
