<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\PayrollIntegrationSettings;
use App\Services\PayrollIntegration\PayrollIntegrationFactory;
use App\Services\PayrollSyncService;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function syncPayroll(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $settings = PayrollIntegrationSettings::where('venue_id', $venue->id)
            ->where('is_active', true)
            ->first();

        if (!$settings) {
            return response()->json(['error' => 'No active payroll integration found'], 404);
        }

        try {
            $provider = PayrollIntegrationFactory::create(
                $settings->provider,
                $settings->credentials
            );

            $syncService = new PayrollSyncService($provider);

            $result = $syncService->syncTimesheets([
                // timesheet data
            ]);

            return response()->json([
                'message' => 'Sync completed successfully',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Sync failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
