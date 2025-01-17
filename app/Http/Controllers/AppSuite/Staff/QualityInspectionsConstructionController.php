<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VenueService;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use App\Models\QualityInspectionsConstruction;
use App\Models\QualityInspectionsConstructionOption;
use App\Models\QualityInspectionsConstructionOptionPhoto;
use Illuminate\Support\Facades\Storage;

class QualityInspectionsConstructionController extends Controller
{

    protected VenueService $venueService;
    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }


    public function index()
    {
        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $perPage = request()->query('per_page', 10);
        $page = request()->query('page', 1);
        try {
            $inspections = QualityInspectionsConstruction::with(['qualityInspectionsConstructionOptions'])
                ->where('venue_id', $authEmployee->restaurant_id)
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'status' => true,
                'message' => 'Quality inspections retrieved successfully',
                'data' => $inspections->items(),
                'pagination' => [
                    'total' => $inspections->total(),
                    'per_page' => $inspections->perPage(),
                    'current_page' => $inspections->currentPage(),
                    'last_page' => $inspections->lastPage(),
                    'from' => $inspections->firstItem(),
                    'to' => $inspections->lastItem()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving quality inspections',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request,$projectId)
    {

        $authEmployee = $this->venueService->employee();
        if ($authEmployee instanceof JsonResponse) return $authEmployee;
        $employee = Employee::where('user_id', auth()->user()->id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        try {
            $validated = $request->validate([
                'location' => 'required|string',
                'inspection_type' => 'required|string', 
                'options' => 'required|array',
                'signature' => 'required|string',
                'options.*.name' => 'required|string',
                'options.*.category' => 'required|string',
                'options.*.comment' => 'required|string',
                'options.*.status' => 'required|in:pass,fail,na',
                'options.*.photos' => 'nullable|array',
                'options.*.photos.*' => 'nullable|image|mimes:jpg,jpeg,png|max:5120'
            ]);
            // Upload signature on s3 it will come in base64 format
            $signature = base64_decode($validated['signature']);
            $fileName = 'signature_' . time() . '.png';
            $path = Storage::disk('s3')->put('quality-inspections-construction-signatures/' . $fileName, $signature);
            if ($path) {
                $filePath = 'quality-inspections-construction-signatures/' . $fileName;
            } else {
                throw new \Exception("Failed to store the signature.");
            }

            $inspection = QualityInspectionsConstruction::create([
                'venue_id' => $authEmployee->restaurant_id,
                'app_project_id' => $projectId,
                'inspector_id' => $employee->id,
                'location' => $validated['location'],
                'inspection_type' => $validated['inspection_type'],
                'signature' => $filePath
            ]);

            foreach ($validated['options'] as $option) {
                $inspectionOption = $inspection->qualityInspectionsConstructionOptions()->create([
                    'name' => $option['name'],
                    'comment' => $option['comment'] ?? null,
                    'category' => $option['category'] ?? null,
                    'status' => $option['status']
                ]);

                if (isset($option['photos'])) {
                    foreach ($option['photos'] as $photo) {
                        $path = Storage::disk('s3')->put('quality-inspections-construction-options-photos', $photo);
                        $inspectionOption->qualityInspectionsConstructionOptionsPhotos()->create([
                            'photo' => $path
                        ]);
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Quality inspection created successfully',
                'data' => $inspection
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error creating quality inspection',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
