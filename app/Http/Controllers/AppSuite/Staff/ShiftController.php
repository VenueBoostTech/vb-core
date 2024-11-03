<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'venue_app_key' => 'required|string',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'days_of_week' => 'required|array',
                'days_of_week.*' => 'integer|between:0,6',
            ]);

            $venue = $this->venueService->adminAuthCheck();
            $employee =  $this->venueService->employee();
            $shift = Shift::create([
                'employee_id' => $employee->id,
                'venue_id' => $venue->id,
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'days_of_week' => implode(',', $validated['days_of_week']),
            ]);

            return response()->json($shift, 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }
}
