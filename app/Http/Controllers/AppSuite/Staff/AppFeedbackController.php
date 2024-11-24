<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppFeedback;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppFeedbackController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function getFeedbackStats(): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $allFeedback = AppFeedback::forVenue($venue->id);
        $lastMonth = Carbon::now()->subMonth();

        // Get current average rating
        $currentAvg = $allFeedback->avg('rating') ?? 0;

        // Get last month's average
        $lastMonthAvg = $allFeedback
            ->where('created_at', '<', $lastMonth)
            ->avg('rating') ?? 0;

        // Calculate statistics
        $stats = [
            'average_rating' => round($currentAvg, 1),
            'rating_change' => round($currentAvg - $lastMonthAvg, 1),
            'total_reviews' => $allFeedback
                ->where('created_at', '>=', $lastMonth)
                ->count(),
            'satisfaction_rate' => $allFeedback
                    ->where('rating', '>=', 4)
                    ->count() / max($allFeedback->count(), 1) * 100,
            'response_rate' => $allFeedback
                    ->whereNotNull('admin_response')
                    ->count() / max($allFeedback->count(), 1) * 100
        ];

        return response()->json($stats);
    }

    public function getFeedbackList(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $query = AppAppFeedback::forVenue($venue->id)
            ->with(['client', 'project'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('client', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })->orWhere('comment', 'like', "%{$search}%");
            });
        }

        if ($request->has('rating')) {
            $query->withRating($request->input('rating'));
        }

        $feedbacks = $query->paginate($request->input('per_page', 10));

        return response()->json([
            'data' => $feedbacks->map(function ($feedback) {
                return [
                    'id' => $feedback->id,
                    'client_name' => $feedback->client?->name ?? 'Anonymous',
                    'project_name' => $feedback->project?->name,
                    'type' => $feedback->type,
                    'rating' => $feedback->rating,
                    'comment' => $feedback->comment,
                    'admin_response' => $feedback->admin_response,
                    'created_at' => $feedback->created_at->diffForHumans(),
                    'responded_at' => $feedback->responded_at?->diffForHumans()
                ];
            }),
            'meta' => [
                'current_page' => $feedbacks->currentPage(),
                'per_page' => $feedbacks->perPage(),
                'total' => $feedbacks->total(),
                'total_pages' => $feedbacks->lastPage(),
            ]
        ]);
    }

    public function respond(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'response' => 'required|string|min:10|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $feedback = AppFeedback::forVenue($venue->id)->findOrFail($id);

        $feedback->update([
            'admin_response' => $request->input('response'),
            'responded_at' => Carbon::now()
        ]);

        return response()->json([
            'message' => 'Response added successfully',
            'feedback' => $feedback
        ]);
    }
}
