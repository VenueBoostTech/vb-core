<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\AuditTrail;
use App\Models\AccountingFinance\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class AuditTrailController extends Controller
{
    public function index(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $auditTrails = $company->auditTrails()->paginate(15);
        return response()->json($auditTrails);
    }

    public function generateReport(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $report = $company->auditTrails()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->groupBy('action');

        return response()->json($report);
    }

    public function getUserActivity(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $userId = $request->input('user_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $userActivity = $company->auditTrails()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return response()->json($userActivity);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
