<?php

namespace App\Http\Controllers\AppSuite\Staff;
use App\Http\Controllers\Controller;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BusinessController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function updateGeofenceAndQR(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        if (!$venue) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'geofence_coordinates' => 'nullable|array',
            'geofence_coordinates.*.lat' => 'required_with:geofence_coordinates|numeric',
            'geofence_coordinates.*.lng' => 'required_with:geofence_coordinates|numeric',
            'generate_new_qr' => 'boolean',
        ]);

        if (isset($validated['geofence_coordinates'])) {
            $venue->geofence_coordinates = $validated['geofence_coordinates'];
        }

        if (isset($validated['generate_new_qr']) && $validated['generate_new_qr']) {
            $venue->qr_code = Str::random(20);
        }

        $venue->save();

        return response()->json([
            'message' => 'Venue updated successfully',
            'venue' => [
                'id' => $venue->id,
                'geofence_coordinates' => $venue->geofence_coordinates,
                'qr_code' => $venue->qr_code,
            ],
        ]);
    }
}
