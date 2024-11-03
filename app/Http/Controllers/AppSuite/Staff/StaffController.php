<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppGallery;
use App\Models\AppProject;
use App\Models\Employee;
use App\Models\ProjectIssue;
use App\Models\QualityInspection;
use App\Models\Restaurant;
use App\Models\SuppliesRequest;
use App\Models\WorkOrder;
use App\Services\ActivityTrackingService;
use App\Services\AppNotificationService;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StaffController extends Controller
{
    protected VenueService $venueService;
    protected AppNotificationService $notificationService;
    protected ActivityTrackingService $activityService;

    public function __construct(
        VenueService $venueService,
        AppNotificationService $notificationService,
        ActivityTrackingService $activityService
    ) {
        $this->venueService = $venueService;
        $this->notificationService = $notificationService;
        $this->activityService = $activityService;
    }

    private function getInitials($name): string
    {
        $words = explode(' ', $name);
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return substr($initials, 0, 2);
    }
    public function getAppGalleriesByProjectId(Request $request, $id): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        // Track view activity
        $this->activityService->trackMediaView($authEmployee, $project);

        $perPage = $request->input('per_page', 15);
        $galleries = AppGallery::where('app_project_id', $id)
            ->with('uploader')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Track search if search parameter is present
        if ($request->has('search')) {
            $this->activityService->trackSearch(
                $authEmployee,
                'media',
                $request->input('search')
            );
        }

        $formattedGalleries = $galleries->map(function ($gallery) {
            return [
                'id' => $gallery->id,
                'type' => $gallery->type,
                'status' => 'uploaded',
                'uploaded_at' => Carbon::parse($gallery->created_at)->diffForHumans(),
                'uploader' => [
                    'id' => $gallery->uploader->id,
                    'name' => $gallery->uploader->name,
                    'avatar' => $gallery->uploader->profile_picture
                        ? Storage::disk('s3')->temporaryUrl($gallery->uploader->profile_picture, '+5 minutes')
                        : $this->getInitials($gallery->uploader->name)
                ],
                'content' => $gallery->type === 'image'
                    ? Storage::disk('s3')->temporaryUrl($gallery->photo_path, '+5 minutes')
                    : Storage::disk('s3')->temporaryUrl($gallery->video_path, '+5 minutes')
            ];
        });

        return response()->json([
            'galleries' => $formattedGalleries,
            'current_page' => $galleries->currentPage(),
            'per_page' => $galleries->perPage(),
            'total' => $galleries->total(),
            'total_pages' => $galleries->lastPage(),
        ], 200);
    }


    // Add app gallery for the project
    public function addAppGallery(Request $request, $id): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'photo' => 'nullable|image|max:15360',
            'video' => 'nullable|mimes:mp4,avi,mov|max:102400',
            'type' => 'required|string|in:image,video',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $gallery = new AppGallery();
        $gallery->app_project_id = $project->id;
        $gallery->venue_id = $venue->id;
        $gallery->uploader_id = $authEmployee->id;
        $gallery->type = $request->type;

        if ($request->type === 'image' && $request->hasFile('photo')) {
            $file = $request->file('photo');
            $path = Storage::disk('s3')->putFile('project_galleries/images', $file);
            $gallery->photo_path = $path;
        } elseif ($request->type === 'video' && $request->hasFile('video')) {
            $file = $request->file('video');
            $path = Storage::disk('s3')->putFile('project_galleries/videos', $file);
            $gallery->video_path = $path;
        } else {
            return response()->json(['error' => 'No valid media uploaded'], 400);
        }

        $gallery->save();

        // Track media upload activity
        $this->activityService->trackMediaUpload($authEmployee, $gallery, $project);

        return response()->json([
            'message' => 'Media uploaded successfully',
            'gallery' => $gallery
        ], 201);
    }

    public function removeAppGallery($id): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $gallery = AppGallery::where('venue_id', $venue->id)
            ->with('project')
            ->find($id);

        if (!$gallery) {
            return response()->json(['error' => 'Media not found'], 404);
        }

        if ($gallery->uploader_id !== $authEmployee->id) {
            return response()->json(['error' => 'Unauthorized to delete this media'], 403);
        }

        // Track before deletion to have access to gallery data
        $this->activityService->trackMediaDelete($authEmployee, $gallery, $gallery->project);

        // Delete files from storage
        if ($gallery->photo_path) {
            Storage::disk('s3')->delete($gallery->photo_path);
        }
        if ($gallery->video_path) {
            Storage::disk('s3')->delete($gallery->video_path);
        }

        $gallery->delete();

        return response()->json(['message' => 'Media deleted successfully']);
    }

    public function getSuppliesRequests(Request $request, $id): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        // Track view activity
        $this->activityService->trackSuppliesView($authEmployee, $project);

        $perPage = $request->input('per_page', 15);
        $query = SuppliesRequest::where('venue_id', $venue->id)
            ->where('app_project_id', $id)
            ->with('employee')
            ->orderBy('created_at', 'desc');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });

            // Track search activity
            $this->activityService->trackSearch($authEmployee, 'supplies', $search);
        }

        $suppliesRequests = $query->paginate($perPage);

        return response()->json([
            'supplies_requests' => $suppliesRequests,
            'current_page' => $suppliesRequests->currentPage(),
            'per_page' => $suppliesRequests->perPage(),
            'total' => $suppliesRequests->total(),
            'total_pages' => $suppliesRequests->lastPage(),
        ]);
    }

    public function addSuppliesRequest(Request $request, $id): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'description' => 'required|string',
            'requested_date' => 'required|date',
            'required_date' => 'required|date|after:requested_date',
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $suppliesRequest = new SuppliesRequest($request->all());
        $suppliesRequest->employee_id = $authEmployee->id;
        $suppliesRequest->venue_id = $venue->id;
        $suppliesRequest->app_project_id = $id;
        $suppliesRequest->save();

        // Track activity
        $this->activityService->trackSuppliesCreate($authEmployee, $suppliesRequest, $project);

        return response()->json([
            'message' => 'Supplies request created successfully',
            'supplies_request' => $suppliesRequest
        ], 201);
    }

    public function getQualityInspections(Request $request, $id): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        if (!$authEmployee->hasAnyRoleUpdated('Team Leader')) {
            return response()->json(['error' => 'Employee is not a Team Leader'], 403);
        }

        // Track view activity
        $this->activityService->trackQualityView($authEmployee, $project);

        $perPage = $request->input('per_page', 15);
        $query = QualityInspection::where('venue_id', $venue->id)
            ->where('app_project_id', $id)
            ->where('team_leader_id', $authEmployee->id)
            ->with('teamLeader:id,name')
            ->orderBy('created_at', 'desc');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('remarks', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
            // Track search activity
            $this->activityService->trackSearch($authEmployee, 'quality-inspections', $search);
        }

        $qualityInspections = $query->paginate($perPage);
        $formattedInspections = $qualityInspections->map(function ($inspection) {
            return [
                'id' => $inspection->id,
                'name' => $inspection->name,
                'remarks' => $inspection->remarks,
                'status' => $inspection->status,
                'inspection_date' => $inspection->inspection_date,
                'improvement_suggestions' => $inspection->improvement_suggestions,
                'rating' => $inspection->rating,
                'team_leader' => [
                    'id' => $inspection->teamLeader->id,
                    'name' => $inspection->teamLeader->name,
                ],
            ];
        });

        return response()->json([
            'quality_inspections' => [
                'current_page' => $qualityInspections->currentPage(),
                'data' => $formattedInspections,
                'first_page_url' => $qualityInspections->url(1),
                'from' => $qualityInspections->firstItem(),
                'last_page' => $qualityInspections->lastPage(),
                'last_page_url' => $qualityInspections->url($qualityInspections->lastPage()),
                'next_page_url' => $qualityInspections->nextPageUrl(),
                'path' => $qualityInspections->path(),
                'per_page' => $qualityInspections->perPage(),
                'prev_page_url' => $qualityInspections->previousPageUrl(),
                'to' => $qualityInspections->lastItem(),
                'total' => $qualityInspections->total(),
            ],
        ], 200);
    }

    public function addQualityInspection(Request $request, $id): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        if (!$authEmployee->hasAnyRoleUpdated('Team Leader')) {
            return response()->json(['error' => 'Employee is not a Team Leader'], 403);
        }

        $validator = Validator::make($request->all(), [
            'remarks' => 'required|string',
            'inspection_date' => 'required|date',
            'rating' => 'required|integer|min:1|max:5',
            'improvement_suggestions' => 'nullable|string',
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $qualityInspection = new QualityInspection($request->all());
        $qualityInspection->team_leader_id = $authEmployee->id;
        $qualityInspection->venue_id = $venue->id;
        $qualityInspection->app_project_id = $id;
        $qualityInspection->save();

        // Track creation activity
        $this->activityService->trackQualityCreate($authEmployee, $qualityInspection, $project);

        return response()->json([
            'message' => 'Quality inspection added successfully',
            'quality_inspection' => $qualityInspection
        ], 201);
    }

    public function getWorkOrders(Request $request, $id): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        if (!$authEmployee->hasAnyRoleUpdated('Operations Manager')) {
            return response()->json(['error' => 'Employee is not an Operations Manager'], 403);
        }

        // Track view activity
        $this->activityService->trackWorkOrderView($authEmployee, $project);

        $perPage = $request->input('per_page', 15);
        $query = WorkOrder::where('venue_id', $venue->id)
            ->where('app_project_id', $id)
            ->where('operation_manager_id', $authEmployee->id)
            ->orderBy('created_at', 'desc');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
            // Track search activity
            $this->activityService->trackSearch($authEmployee, 'work-orders', $search);
        }

        $workOrders = $query->paginate($perPage);

        return response()->json([
            'work_orders' => $workOrders->items(),
            'current_page' => $workOrders->currentPage(),
            'per_page' => $workOrders->perPage(),
            'total' => $workOrders->total(),
            'total_pages' => $workOrders->lastPage(),
        ], 200);
    }

    public function addWorkOrder(Request $request, $id): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        if (!$authEmployee->hasAnyRoleUpdated('Operations Manager')) {
            return response()->json(['error' => 'Employee is not an Operations Manager'], 403);
        }

        $validator = Validator::make($request->all(), [
            'description' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'name' => 'nullable|string|max:255',
            'finance_order_id' => 'nullable|numeric',
            'priority' => 'required|in:low,medium,high'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $workOrder = new WorkOrder($request->all());
        $workOrder->operation_manager_id = $authEmployee->id;
        $workOrder->venue_id = $venue->id;
        $workOrder->app_project_id = $id;
        $workOrder->save();

        // Track creation activity
        $this->activityService->trackWorkOrderCreate($authEmployee, $workOrder, $project);

        return response()->json([
            'message' => 'Work order added successfully',
            'work_order' => $workOrder
        ], 201);
    }

    public function getProjectIssues(Request $request, $id): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        // Track view activity
        $this->activityService->trackIssueView($authEmployee, $project);

        $perPage = $request->input('per_page', 15);
        $query = ProjectIssue::where('venue_id', $venue->id)
            ->where('app_project_id', $id)
            ->with('employee')
            ->orderBy('created_at', 'desc');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('issue', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
            // Track search activity
            $this->activityService->trackSearch($authEmployee, 'project-issues', $search);
        }

        $projectIssues = $query->paginate($perPage);

        return response()->json([
            'project_issues' => $projectIssues->items(),
            'current_page' => $projectIssues->currentPage(),
            'per_page' => $projectIssues->perPage(),
            'total' => $projectIssues->total(),
            'total_pages' => $projectIssues->lastPage(),
        ], 200);
    }

    public function addProjectIssue(Request $request, $id): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $project = AppProject::where('venue_id', $venue->id)->find($id);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'issue' => 'required|string',
            'priority' => 'required|in:low,medium,high',
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $projectIssue = new ProjectIssue($request->all());
        $projectIssue->employee_id = $authEmployee->id;
        $projectIssue->venue_id = $venue->id;
        $projectIssue->app_project_id = $id;
        $projectIssue->save();

        // Track creation activity
        $this->activityService->trackIssueCreate($authEmployee, $projectIssue, $project);

        return response()->json([
            'message' => 'Project issue reported successfully',
            'project_issue' => $projectIssue
        ], 201);
    }

}
