<?php

namespace App\Http\Controllers\v3;

use App\Http\Controllers\Controller;

use App\Models\Brand;
use App\Models\PhysicalStore;
use App\Models\Restaurant;
use App\Services\ReportGenerationService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private $reportService;

    public function __construct(ReportGenerationService $reportService)
    {
        $this->reportService = $reportService;
    }

    private function getVenue(): ?Restaurant
    {
        if (!auth()->user()->restaurants->count()) {
            return null;
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return null;
        }

        return auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
    }

    public function getDailyOverviewReport(Request $request, $brandId): \Illuminate\Http\JsonResponse
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);
        $storeId = $request->input('store_id');

        $venue = $this->getVenue();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found or user not eligible'], 404);
        }

        $physicalStore = PhysicalStore::where('id', $storeId)->first();
        if (!$physicalStore) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        if ($physicalStore->venue_id !== $venue->id) {
            return response()->json(['error' => 'Store not found'], 404);
        }


        $brand = Brand::where('id', $brandId)->first();
        if (!$brand) {
            return response()->json(['error' => 'Brand not found'], 404);
        }

        if ($brand->venue_id !== $venue->id) {
            return response()->json(['error' => 'Brand not found'], 404);
        }

        $report = $this->reportService->generateDailyOverviewReport($brand, $year, $month, $storeId);

        return response()->json($report);
    }

    public function getDailySalesInLCReport(Request $request, $brandId): \Illuminate\Http\JsonResponse
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $venue = $this->getVenue();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found or user not eligible'], 404);
        }

        $brand = Brand::where('id', $brandId)->first();
        if (!$brand) {
            return response()->json(['error' => 'Brand not found'], 404);
        }

        if ($brand->venue_id !== $venue->id) {
            return response()->json(['error' => 'Brand not found'], 404);
        }

        $report = $this->reportService->generateDailySalesInLCReport($brand, $year, $month);

        return response()->json($report);
    }

    public function getInventory($brandId): \Illuminate\Http\JsonResponse
    {
        $venue = $this->getVenue();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found or user not eligible'], 404);
        }

        $brand = Brand::where('id', $brandId)->first();
        if (!$brand) {
            return response()->json(['error' => 'Brand not found'], 404);
        }

        if ($brand->venue_id !== $venue->id) {
            return response()->json(['error' => 'Brand not found'], 404);
        }

        $inventory = $this->reportService->calculateInventory($brand);

        return response()->json(['inventory' => $inventory]);
    }

    public function getInventoryByStore($brandId): \Illuminate\Http\JsonResponse
    {
        $venue = $this->getVenue();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found or user not eligible'], 404);
        }

        $brand = Brand::where('id', $brandId)->first();
        if (!$brand) {
            return response()->json(['error' => 'Brand not found'], 404);
        }

        if ($brand->venue_id !== $venue->id) {
            return response()->json(['error' => 'Brand not found'], 404);
        }
        $inventoryByStore = $this->reportService->getInventoryByStore($brand);

        return response()->json(['inventory_by_store' => $inventoryByStore]);
    }

    public function getInventoryTurnoverReport($brandId, Request $request): \Illuminate\Http\JsonResponse
    {
        $brand = Brand::findOrFail($brandId);

        $startDate = $request->input('start_date', now()->subMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $report = $this->reportService->generateInventoryTurnoverReport($brand, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => [
                'brand' => $brand->name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'report' => $report
            ]
        ]);
    }
}
