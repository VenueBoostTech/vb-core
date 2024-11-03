<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppProject;
use App\Models\Comment;
use App\Models\Employee;
use App\Models\Restaurant;
use App\Services\ActivityTrackingService;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CommentController extends Controller
{

    protected VenueService $venueService;
    protected ActivityTrackingService $activityService;

    public function __construct(
        VenueService $venueService,
        ActivityTrackingService $activityService
    ) {
        $this->venueService = $venueService;
        $this->activityService = $activityService;
    }

    public function getComments(Request $request, $projectId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $project = AppProject::where('venue_id', $venue->id)->find($projectId);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $this->activityService->trackCommentView($authEmployee, $project);

        $perPage = $request->input('per_page', 15);

        $comments = Comment::where('project_id', $projectId)
            ->whereNull('parent_id')
            ->with(['employee', 'replies.employee'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $formattedComments = $comments->map(function ($comment) {
            return [
                'id' => $comment->id,
                'comment' => $comment->comment,
                'time_ago' => $comment->time_ago,
                'image' => $comment->image_path ? Storage::disk('s3')->temporaryUrl($comment->image_path, '+5 minutes') : null,
                'employee' => [
                    'id' => $comment->employee->id,
                    'name' => $comment->employee->name,
                    'avatar' => $comment->employee->profile_picture
                        ? Storage::disk('s3')->temporaryUrl($comment->employee->profile_picture, '+5 minutes')
                        : null
                ],
                'replies' => $comment->replies->map(function ($reply) {
                    return [
                        'id' => $reply->id,
                        'comment' => $reply->comment,
                        'time_ago' => $reply->time_ago,
                        'image' => $reply->image_path ? Storage::disk('s3')->temporaryUrl($reply->image_path, '+5 minutes') : null,
                        'employee' => [
                            'id' => $reply->employee->id,
                            'name' => $reply->employee->name,
                            'avatar' => $reply->employee->profile_picture
                                ? Storage::disk('s3')->temporaryUrl($reply->employee->profile_picture, '+5 minutes')
                                : null
                        ]
                    ];
                })
            ];
        });

        return response()->json([
            'comments' => $formattedComments,
            'current_page' => $comments->currentPage(),
            'per_page' => $comments->perPage(),
            'total' => $comments->total(),
            'total_pages' => $comments->lastPage(),
        ]);
    }

    public function addComment(Request $request, $projectId): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $venue = Restaurant::where('id', $authEmployee->restaurant_id)->first();
        if (!$venue instanceof Restaurant) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $project = AppProject::where('venue_id', $venue->id)->find($projectId);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string',
            'image' => 'nullable|image|max:10240', // 10MB max
            'parent_id' => 'nullable|exists:comments,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Handle parent comment validation
        if ($request->parent_id) {
            $parentComment = Comment::find($request->parent_id);
            if (!$parentComment || $parentComment->project_id != $projectId) {
                return response()->json(['error' => 'Invalid parent comment'], 400);
            }
        }

        $comment = new Comment();
        $comment->project_id = $projectId;
        $comment->employee_id = $authEmployee->id;
        $comment->venue_id = $venue->id;
        $comment->comment = $request->comment;
        $comment->parent_id = $request->parent_id;

        // Handle image upload if present
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $filename = Str::random(20) . '.' . $image->getClientOriginalExtension();
            $path = Storage::disk('s3')->putFileAs('project_comments', $image, $filename);
            $comment->image_path = $path;
        }

        $comment->save();

        // Track comment creation activity
        $this->activityService->trackCommentCreate($authEmployee, $comment, $project);


        // Format the response
        $response = [
            'id' => $comment->id,
            'comment' => $comment->comment,
            'time_ago' => $comment->time_ago,
            'image' => $comment->image_path ? Storage::disk('s3')->temporaryUrl($comment->image_path, '+5 minutes') : null,
            'employee' => [
                'id' => $authEmployee->id,
                'name' => $authEmployee->name,
                'avatar' => $authEmployee->profile_picture
                    ? Storage::disk('s3')->temporaryUrl($authEmployee->profile_picture, '+5 minutes')
                    : null
            ]
        ];

        return response()->json(['message' => 'Comment added successfully', 'comment' => $response], 201);
    }

    public function deleteComment($id): JsonResponse
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;

        $comment = Comment::where('employee_id', $authEmployee->id)->find($id);
        if (!$comment) {
            return response()->json(['error' => 'Comment not found or unauthorized'], 404);
        }

        // Delete image from storage if exists
        if ($comment->image_path) {
            Storage::disk('s3')->delete($comment->image_path);
        }

        // Track comment deletion before actually deleting
        $this->activityService->trackCommentDelete(
            $authEmployee,
            $comment,
            $comment->project
        );

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }
}

