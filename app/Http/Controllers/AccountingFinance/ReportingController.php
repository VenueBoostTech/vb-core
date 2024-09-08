<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountingFinance\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class ReportingController extends Controller
{
    public function generateCustomReport(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'type' => 'required|string',
            'data' => 'required|array',
        ]);

        $report = $company->reports()->create([
            'type' => 'custom',
            'report_date' => now(),
            'data' => $validatedData['data'],
        ]);

        return response()->json($report, 201);
    }

    public function getScheduledReports(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $scheduledReports = $company->reports()->where('type', 'scheduled')->get();
        return response()->json($scheduledReports);
    }

    public function scheduleReport(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'scheduled_date' => 'required|date',
            'report_config' => 'required|array',
        ]);

        $scheduledReport = $company->reports()->create([
            'type' => 'scheduled',
            'report_date' => $validatedData['scheduled_date'],
            'data' => $validatedData['report_config'],
        ]);

        return response()->json($scheduledReport, 201);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
