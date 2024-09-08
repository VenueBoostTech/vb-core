<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountingFinance\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class ComplianceController extends Controller
{
    public function getRegulations(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $regulations = $company->compliances()->select('regulation')->distinct()->get();
        return response()->json($regulations);
    }

    public function submitComplianceReport(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'regulation' => 'required|string',
            'status' => 'required|in:compliant,non-compliant',
            'compliance_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $complianceReport = $company->compliances()->create($validatedData);
        return response()->json($complianceReport, 201);
    }

    public function initiateAudit(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $overdue = $company->compliances()->where('compliance_date', '<', now())
            ->where('status', '!=', 'compliant')->get();
        $upcoming = $company->compliances()->where('compliance_date', '>=', now())
            ->where('compliance_date', '<=', now()->addDays(30))->get();

        return response()->json([
            'overdue_compliance' => $overdue,
            'upcoming_compliance' => $upcoming,
        ]);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
