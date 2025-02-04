<?php
namespace App\Http\Controllers\AppSuite;

use App\Enums\FeatureNaming;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Feature;
use App\Models\FeatureUsageCreditHistory;
use App\Models\Feedback;
use App\Models\PlanFeature;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\Subscription;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VBAppCustomersController extends Controller
{
    protected VenueService $venueService;
    protected int $perPage = 20; // Number of customers per page

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function index(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'offset' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $offset = $request->input('offset', 0);

            $query = Customer::where('venue_id', $venue->id);

            $totalCustomers = $query->count();

            $customers = $query->orderBy('created_at', 'DESC')
                ->offset($offset)
                ->limit($this->perPage)
                ->get();

            $result = $customers->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'user_id' => $customer->user_id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'loyalty_level' => 'Bronze'
                ];
            });

            $nextOffset = $offset + $this->perPage;
            $hasMore = $nextOffset < $totalCustomers;

            return response()->json([
                'message' => 'Customers retrieved successfully',
                'customers' => $result,
                'pagination' => [
                    'total' => $totalCustomers,
                    'offset' => $offset,
                    'limit' => $this->perPage,
                    'has_more' => $hasMore,
                    'next_offset' => $hasMore ? $nextOffset : null,
                ],
            ], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $customer = Customer::where('venue_id', $venue->id)
            ->where('id', $id)
            ->with(['feedback' => function ($query) use ($venue) {
                $query->where('venue_id', $venue->id)->with('store');
            }])
            ->first();

        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $result = [
            'id' => $customer->id,
            'user_id' => $customer->user_id,
            'name' => $customer->name,
            'email' => $customer->email,
            'loyalty_level' => 'Bronze',
            'feedback' => $customer->feedback->map(function ($feedback) {
                $feedbackData = [
                    'id' => $feedback->id,
                    'visit_date' => $feedback->visit_date,
                    'overall_satisfaction' => $feedback->overall_satisfaction,
                    'product_quality' => $feedback->product_quality,
                    'staff_knowledge' => $feedback->staff_knowledge,
                    'staff_friendliness' => $feedback->staff_friendliness,
                    'store_cleanliness' => $feedback->store_cleanliness,
                    'value_for_money' => $feedback->value_for_money,
                    'found_desired_product' => $feedback->found_desired_product,
                    'product_feedback' => $feedback->product_feedback,
                    'service_feedback' => $feedback->service_feedback,
                    'improvement_suggestions' => $feedback->improvement_suggestions,
                    'would_recommend' => $feedback->would_recommend,
                    'purchase_made' => $feedback->purchase_made,
                    'purchase_amount' => $feedback->purchase_amount,
                ];

                if ($feedback->store) {
                    $feedbackData['store'] = [
                        'id' => $feedback->store->id,
                        'name' => $feedback->store->name,
                        // Add any other relevant store fields here
                    ];
                } else {
                    $feedbackData['store'] = null;
                }

                return $feedbackData;
            })
        ];

        return response()->json($result);
    }

    public function storeFeedback(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $customer = Customer::where('venue_id', $venue->id)
            ->where('id', $id)
            ->first();

        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'store_id' => 'required|exists:physical_stores,id',
            'sales_associate_id' => 'nullable|exists:users,id',
            'visit_date' => 'required|date',
            'overall_satisfaction' => 'required|integer|min:1|max:10',
            'product_quality' => 'required|integer|min:1|max:10',
            'staff_knowledge' => 'required|integer|min:1|max:10',
            'staff_friendliness' => 'required|integer|min:1|max:10',
            'store_cleanliness' => 'required|integer|min:1|max:10',
            'value_for_money' => 'required|integer|min:1|max:10',
            'found_desired_product' => 'required|boolean',
            'product_feedback' => 'nullable|string',
            'service_feedback' => 'nullable|string',
            'improvement_suggestions' => 'nullable|string',
            'would_recommend' => 'required|boolean',
            'purchase_made' => 'nullable|string',
            'purchase_amount' => 'nullable|numeric|min:0',
            'preferred_communication_channel' => 'required|string',
            'subscribe_to_newsletter' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $feedbackData = $validator->validated();
        $feedbackData['customer_id'] = $customer->id;
        $feedbackData['venue_id'] = $venue->id;

        $feedback = Feedback::create($feedbackData);

        return response()->json([
            'message' => 'Feedback stored successfully',
            'feedback' => $feedback
        ], 201);
    }

    public function listFeedbackOS(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        $feedback = Feedback::with(['customer'])
                            ->where('venue_id', $venue->id)
                            ->orderBy('created_at', 'desc')
                            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'message' => 'Feedback retrieved successfully',
            'data' => $feedback->items(),
            'pagination' => [
                'total' => $feedback->total(),
                'per_page' => $perPage,
                'current_page' => $page,
            ],
        ]);
    }

    public function getFeedbackByIdOS(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $feedback = Feedback::with(['customer'])
                            ->where('venue_id', $venue->id)
                            ->where('id', $id)
                            ->first();

        if (!$feedback) {
            return response()->json(['error' => 'Feedback not found'], 404);
        }

        return response()->json([
            'message' => 'Feedback retrieved successfully',
            'data' => $feedback,
        ], 200);
    }
}
