<?php

namespace App\Http\Controllers\AppSuite;

use App\Http\Controllers\Controller;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppWhitelabelController extends Controller
{
    protected VenueService $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function webAppConfig(Request $request): JsonResponse
    {

        try {

            $validated = $request->validate([
                'venue_app_key' => 'required|string',
                'app_source' => 'nullable|string',
            ]);

            $venue = $this->venueService->getVenueByAppCode($validated['venue_app_key']);

            // Use a default app_source if not provided
            $appSource = $validated['app_source'] ?? 'default_app';

            $appConfiguration = $this->venueService->getSimplifiedAppConfiguration($venue, $appSource);

            return response()->json($appConfiguration);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                return response()->json(['error' => $e->getMessage()], 404);
            }
            if ($e->getCode() === 400) {
                return response()->json(['error' => $e->getMessage()], 400);
            }
            \Sentry\captureException($e);
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }
}
