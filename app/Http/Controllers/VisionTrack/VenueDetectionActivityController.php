<?php

namespace App\Http\Controllers\VisionTrack;

use App\Http\Controllers\Controller;
use App\Models\VtDevice;
use App\Models\VtDetectionActivity;
use App\Models\VtVenueDetectionActivity;
use App\Models\VtDeviceDetectionActivity;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VenueDetectionActivityController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    // Get all global activities
    public function listGlobal(): JsonResponse
    {
        $activities = VtDetectionActivity::where('is_active', true)->get();
        return response()->json($activities);
    }

    // List activities enabled for venue
    public function listAvailable(): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) {
            return $venue;
        }

        $activities = VtVenueDetectionActivity::with('activity')
            ->where('venue_id', $venue->id)
            ->where('is_enabled', true)
            ->get();

        return response()->json($activities);
    }

    // Enable activity for venue
    public function store(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) {
            return $venue;
        }

        $validator = Validator::make($request->all(), [
            'detection_activity_id' => [
                'required',
                Rule::exists('vt_detection_activities', 'id')->where('is_active', true)
            ],
            'config' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $venueActivity = VtVenueDetectionActivity::updateOrCreate(
            [
                'venue_id' => $venue->id,
                'detection_activity_id' => $request->detection_activity_id
            ],
            [
                'is_enabled' => true,
                'config' => $request->config
            ]
        );

        return response()->json([
            'message' => 'Activity enabled for venue successfully',
            'activity' => $venueActivity->load('activity')
        ]);
    }

    // List activities by device
    public function listByDevice(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) {
            return $venue;
        }

        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:vt_devices,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $device = VtDevice::where('venue_id', $venue->id)
            ->findOrFail($request->device_id);

        $activities = VtDeviceDetectionActivity::with(['venueActivity.activity', 'device'])
            ->whereHas('venueActivity', function ($q) use ($venue) {
                $q->where('venue_id', $venue->id)
                    ->where('is_enabled', true);
            })
            ->where('device_id', $device->id)
            ->get();

        return response()->json($activities);
    }

    // Assign activity to device
    public function assignToDevice(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) {
            return $venue;
        }

        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:vt_devices,id',
            'venue_detection_activity_id' => [
                'required',
                Rule::exists('vt_venue_detection_activities', 'id')
                    ->where('venue_id', $venue->id)
                    ->where('is_enabled', true)
            ],
            'config' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $device = VtDevice::where('venue_id', $venue->id)
            ->findOrFail($request->device_id);

        $deviceActivity = VtDeviceDetectionActivity::updateOrCreate(
            [
                'device_id' => $device->id,
                'venue_detection_activity_id' => $request->venue_detection_activity_id
            ],
            [
                'is_active' => true,
                'config' => $request->config
            ]
        );

        return response()->json([
            'message' => 'Activity assigned to device successfully',
            'activity' => $deviceActivity->load('venueActivity.activity')
        ]);
    }

    // Update device activity config
    public function updateDeviceActivity(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) {
            return $venue;
        }

        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:vt_devices,id',
            'is_active' => 'boolean',
            'config' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $device = VtDevice::where('venue_id', $venue->id)
            ->findOrFail($request->device_id);

        $deviceActivity = VtDeviceDetectionActivity::where('device_id', $device->id)
            ->findOrFail($id);

        $deviceActivity->update($request->only(['is_active', 'config']));

        return response()->json([
            'message' => 'Device activity updated successfully',
            'activity' => $deviceActivity->load('venueActivity.activity')
        ]);
    }

    // Delete activity from device
    public function deleteFromDevice(Request $request, $id): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) {
            return $venue;
        }

        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:vt_devices,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $device = VtDevice::where('venue_id', $venue->id)
            ->findOrFail($request->device_id);

        $deviceActivity = VtDeviceDetectionActivity::where('device_id', $device->id)
            ->findOrFail($id);

        $deviceActivity->delete();

        return response()->json(['message' => 'Activity removed from device successfully']);
    }
}
