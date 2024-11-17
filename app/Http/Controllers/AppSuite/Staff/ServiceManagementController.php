<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Models\Service;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ServiceManagementController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    // Category Methods
    public function listCategories(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $perPage = $request->input('per_page', 15);
        $categories = ServiceCategory::where('venue_id', $venue->id)
            ->withCount(['services', 'activeServices'])
            ->paginate($perPage);

        foreach ($categories as $category) {
            $category->last_updated = $category->updated_at->diffForHumans();
        }

        return response()->json([
            'categories' => [
                'data' => $categories->items(),
                'current_page' => $categories->currentPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'total_pages' => $categories->lastPage(),
            ],
            'stats' => [
                'total_categories' => ServiceCategory::where('venue_id', $venue->id)->count(),
                'total_active_services' => Service::where('venue_id', $venue->id)
                    ->where('status', 'Active')
                    ->count(),
                'most_popular' => $this->getMostPopularCategory($venue->id)
            ]
        ]);
    }

    public function createCategory(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:service_categories,name,NULL,id,venue_id,' . $venue->id,
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $category = ServiceCategory::create([
                'venue_id' => $venue->id,
                'name' => $validator->validated()['name'],
                'slug' => Str::slug($validator->validated()['name']),
                'description' => $validator->validated()['description'] ?? null
            ]);

            return response()->json($category, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create category: ' . $e->getMessage()], 500);
        }
    }

    public function updateCategory(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $category = ServiceCategory::where('venue_id', $venue->id)->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:service_categories,name,' . $id . ',id,venue_id,' . $venue->id,
                'description' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $category->update([
                'name' => $validator->validated()['name'],
                'slug' => Str::slug($validator->validated()['name']),
                'description' => $validator->validated()['description'] ?? $category->description
            ]);

            return response()->json($category);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Category not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update category: ' . $e->getMessage()], 500);
        }
    }

    public function deleteCategory($id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $category = ServiceCategory::where('venue_id', $venue->id)->findOrFail($id);

            // Check if category has services
            if ($category->services()->count() > 0) {
                return response()->json([
                    'error' => 'Cannot delete category with associated services'
                ], 400);
            }

            $category->delete();
            return response()->json(['message' => 'Category deleted successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Category not found'], 404);
        }
    }

    // Service Methods
    public function listServices(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $perPage = $request->input('per_page', 15);
        $services = Service::with('category')
            ->where('venue_id', $venue->id)
            ->when($request->filled('category_id'), function ($query) use ($request) {
                $query->where('category_id', $request->category_id);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->paginate($perPage);

        return response()->json([
            'services' => [
                'data' => $services->items(),
                'current_page' => $services->currentPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
                'total_pages' => $services->lastPage(),
            ]
        ]);
    }

    public function createService(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:service_categories,id',
            'price_type' => 'required|in:Fixed,Variable,Quote',
            'base_price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'status' => 'required|in:Active,Inactive'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $validated = $validator->validated();

            // Verify category belongs to venue
            $category = ServiceCategory::where('venue_id', $venue->id)
                ->findOrFail($validated['category_id']);

            $service = Service::create([
                'venue_id' => $venue->id,
                'category_id' => $category->id,
                'name' => $validated['name'],
                'price_type' => $validated['price_type'],
                'base_price' => $validated['base_price'],
                'duration' => $validated['duration'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status']
            ]);

            return response()->json($service->load('category'), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create service: ' . $e->getMessage()], 500);
        }
    }

    public function updateService(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;


        $service = Service::where('venue_id', $venue->id)->findOrFail($id);

        try {
            $service = Service::where('venue_id', $venue->id)->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'category_id' => 'sometimes|required|exists:service_categories,id',
                'price_type' => 'sometimes|required|in:Fixed,Variable,Quote',
                'base_price' => 'sometimes|required|numeric|min:0',
                'duration' => 'sometimes|required|integer|min:1',
                'description' => 'nullable|string',
                'status' => 'sometimes|required|in:Active,Inactive'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $validated = $validator->validated();

            if (isset($validated['category_id'])) {
                // Verify new category belongs to venue
                ServiceCategory::where('venue_id', $venue->id)
                    ->findOrFail($validated['category_id']);
            }

            $service->update($validated);
            return response()->json($service->load('category'));
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Service not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update service: ' . $e->getMessage()], 500);
        }
    }

    public function deleteService($id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        try {
            $service = Service::where('venue_id', $venue->id)->findOrFail($id);
            $service->delete();
            return response()->json(['message' => 'Service deleted successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Service not found'], 404);
        }
    }

    private function getMostPopularCategory($venueId): ?string
    {
        return ServiceCategory::where('venue_id', $venueId)
            ->withCount('activeServices')
            ->orderBy('active_services_count', 'desc')
            ->first()
            ?->name;
    }

    public function show($id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $service = Service::where('venue_id', $venue->id)
            ->with([
                'category',
                'serviceRequests' => function($query) {
                    $query->latest()->take(5)->with(['client', 'assignedStaff']);
                }
            ])
            ->find($id);

        if (!$service) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        $formattedService = [
            'id' => $service->id,
            'name' => $service->name,
            'price_type' => $service->price_type,
            'base_price' => number_format($service->base_price, 2),
            'duration' => $service->duration,
            'description' => $service->description,
            'status' => $service->status,
            'category' => [
                'id' => $service->category->id,
                'name' => $service->category->name
            ],
            'service_requests' => $service->serviceRequests->map(function($request) {
                return [
                    'id' => $request->id,
                    'reference' => $request->reference,
                    'status' => $request->status,
                    'requested_date' => $request->requested_date,
                    'client' => [
                        'id' => $request->client->id,
                        'name' => $request->client->name
                    ],
                    'assigned_to' => $request->assignedStaff ? [
                        'id' => $request->assignedStaff->id,
                        'name' => $request->assignedStaff->name
                    ] : null
                ];
            })
        ];

        return response()->json($formattedService);
    }
}
