<?php

namespace App\Http\Controllers\VisionTrack;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\VtSubscription;
use App\Models\VtPlan;
use App\Services\VtSubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\VenueService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class VtClientsController extends Controller
{
    protected VenueService $venueService;
    protected VtSubscriptionService $subscriptionService;

    public function __construct(
        VenueService $venueService,
        VtSubscriptionService $subscriptionService
    ) {
        $this->venueService = $venueService;
        $this->subscriptionService = $subscriptionService;
    }

    public function index(Request $request): JsonResponse
    {
        // Ensure admin authentication
        $this->venueService->adminAuthCheck();

        $query = Restaurant::query()
            ->whereHas('vtSubscription', function ($query) {
                $query->where('status', 'active');
            })
            ->with(['vtSubscription' => function ($query) {
                $query->select([
                    'id',
                    'restaurant_id',
                    'vt_plan_id',
                    'status',
                    'billing_cycle',
                    'current_period_end',
                    'created_at'
                ]);
            }, 'vtSubscription.plan' => function ($query) {
                $query->select([
                    'id',
                    'name',
                    'slug',
                    'max_cameras',
                    'price_monthly',
                    'price_yearly'
                ]);
            }])
            ->select([
                'id',
                'name',
                'email',
                'phone_number',
                'status',
                'venue_type',
                'created_at'
            ]);

        // Apply filters
        if ($request->has('plan')) {
            $query->whereHas('vtSubscription.plan', function ($query) use ($request) {
                $query->where('slug', $request->plan);
            });
        }

        if ($request->has('billing_cycle')) {
            $query->whereHas('vtSubscription', function ($query) use ($request) {
                $query->where('billing_cycle', $request->billing_cycle);
            });
        }

        // Apply search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortField = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Paginate results
        $perPage = $request->input('per_page', 15);
        $clients = $query->paginate($perPage);

        // Transform the data to include only what's necessary
        $transformedData = $clients->through(function ($client) {
            return [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone_number,
                'status' => $client->status,
                'venue_type' => $client->venue_type,
                'created_at' => $client->created_at,
                'subscription' => [
                    'id' => $client->vtSubscription->id,
                    'status' => $client->vtSubscription->status,
                    'billing_cycle' => $client->vtSubscription->billing_cycle,
                    'current_period_end' => $client->vtSubscription->current_period_end,
                    'plan' => [
                        'name' => $client->vtSubscription->plan->name,
                        'slug' => $client->vtSubscription->plan->slug,
                        'max_cameras' => $client->vtSubscription->plan->max_cameras,
                        'price' => $client->vtSubscription->billing_cycle === 'monthly'
                            ? $client->vtSubscription->plan->price_monthly
                            : $client->vtSubscription->plan->price_yearly
                    ]
                ]
            ];
        });

        return response()->json([
            'data' => $transformedData,
            'meta' => [
                'total' => $clients->total(),
                'per_page' => $clients->perPage(),
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
            ]
        ]);
    }

    public function show($id): JsonResponse
    {
        // Ensure admin authentication
        $this->venueService->adminAuthCheck();

        $client = Restaurant::with([
            'vtSubscription.plan',
            'devices'
        ])
            ->select([
                'id',
                'name',
                'email',
                'phone_number',
                'status',
                'venue_type',
                'timezone'
            ])
            ->findOrFail($id);

        // Transform the data to include only VT-related information
        $transformedData = [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone_number' => $client->phone_number,
            'status' => $client->status,
            'venue_type' => $client->venue_type,
            'timezone' => $client->timezone,
            'vt_subscription' => $client->vtSubscription ? [
                'id' => $client->vtSubscription->id,
                'status' => $client->vtSubscription->status,
                'billing_cycle' => $client->vtSubscription->billing_cycle,
                'current_period_start' => $client->vtSubscription->current_period_start,
                'current_period_end' => $client->vtSubscription->current_period_end,
                'plan' => $client->vtSubscription->plan ? [
                    'id' => $client->vtSubscription->plan->id,
                    'name' => $client->vtSubscription->plan->name,
                    'slug' => $client->vtSubscription->plan->slug,
                    'max_cameras' => $client->vtSubscription->plan->max_cameras,
                    'features' => $client->vtSubscription->plan->features,
                    'price' => $client->vtSubscription->billing_cycle === 'monthly'
                        ? $client->vtSubscription->plan->price_monthly
                        : $client->vtSubscription->plan->price_yearly
                ] : null
            ] : null,
            'devices' => $client->devices->map(function ($device) {
                return [
                    'id' => $device->id,
                    'name' => $device->name,
                    'status' => $device->status,
                    'streams' => $device->streams->map(function ($stream) {
                        return [
                            'id' => $stream->id,
                            'name' => $stream->name,
                            'status' => $stream->status
                        ];
                    })
                ];
            })
        ];

        return response()->json([
            'data' => $transformedData
        ]);
    }

    public function getSubscriptionStats(): JsonResponse
    {
        // Ensure admin authentication
        $this->venueService->adminAuthCheck();

        $stats = [
            'total_active' => VtSubscription::where('status', 'active')->count(),
            'by_plan' => VtSubscription::where('status', 'active')
                ->selectRaw('vt_plan_id, count(*) as count')
                ->groupBy('vt_plan_id')
                ->with('plan:id,name')
                ->get(),
            'by_billing_cycle' => VtSubscription::where('status', 'active')
                ->selectRaw('billing_cycle, count(*) as count')
                ->groupBy('billing_cycle')
                ->get(),
            'recent_subscriptions' => Restaurant::whereHas('vtSubscription', function ($query) {
                $query->where('status', 'active')
                    ->orderBy('created_at', 'desc');
            })
                ->with('vtSubscription.plan')
                ->limit(5)
                ->get()
        ];

        return response()->json([
            'data' => $stats
        ]);
    }

    public function getExpiringSoon(): JsonResponse
    {
        // Ensure admin authentication
        $this->venueService->adminAuthCheck();

        $expiringClients = Restaurant::whereHas('vtSubscription', function ($query) {
            $query->where('status', 'active')
                ->whereNull('canceled_at')
                ->whereDate('current_period_end', '<=', now()->addDays(7))
                ->whereDate('current_period_end', '>', now());
        })
            ->with(['vtSubscription.plan'])
            ->get();

        return response()->json([
            'data' => $expiringClients
        ]);
    }

    public function getPlans(Request $request): JsonResponse
    {
        // Ensure admin authentication
        $this->venueService->adminAuthCheck();

        $query = VtPlan::query();

        // Apply search if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Count active subscriptions for each plan
        $plans = $query->withCount(['subscriptions' => function ($query) {
            $query->where('status', 'active');
        }])->get();

        // Add price comparison and savings calculation
        $plans->each(function ($plan) {
            $plan->yearly_savings = ($plan->price_monthly * 12) - $plan->price_yearly;
            $plan->monthly_equivalent = round($plan->price_yearly / 12, 2);
        });

        return response()->json([
            'data' => $plans,
            'meta' => [
                'total' => $plans->count(),
            ]
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        // Ensure admin authentication
        $this->venueService->adminAuthCheck();

        // Create validator
        $validator = Validator::make($request->all(), [
            'restaurant_id' => [
                'required',
                'integer',
                'exists:restaurants,id',
                Rule::unique('vt_subscriptions', 'restaurant_id')
                    ->where(function ($query) {
                        return $query->where('status', 'active');
                    })
            ],
            'plan_id' => 'required|integer|exists:vt_plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'trial_days' => 'nullable|integer|min:0',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $validated = $validator->validated();

            $restaurant = Restaurant::findOrFail($validated['restaurant_id']);
            $plan = VtPlan::findOrFail($validated['plan_id']);

            // Calculate trial end date if trial days provided
            $trialEndsAt = null;
            if (!empty($validated['trial_days'])) {
                $trialEndsAt = Carbon::now()->addDays($validated['trial_days']);
            }

            // Create subscription
            $subscription = $this->subscriptionService->createSubscription(
                $restaurant,
                $plan,
                $validated['billing_cycle'],
                $trialEndsAt
            );

            DB::commit();

            // Transform the response
            $transformedData = [
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'billing_cycle' => $subscription->billing_cycle,
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end,
                    'trial_ends_at' => $subscription->trial_ends_at,
                ],
                'plan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'max_cameras' => $plan->max_cameras,
                    'price' => $validated['billing_cycle'] === 'monthly'
                        ? $plan->price_monthly
                        : $plan->price_yearly
                ],
                'venue' => [
                    'id' => $restaurant->id,
                    'name' => $restaurant->name,
                    'email' => $restaurant->email
                ]
            ];

            return response()->json([
                'message' => 'Subscription created successfully',
                'data' => $transformedData
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
