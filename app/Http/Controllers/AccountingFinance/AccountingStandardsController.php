<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Company;
use App\Models\AccountingFinance\Compliance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class AccountingStandardsController extends Controller
{
    public function index(): JsonResponse
    {
        $company = $this->getCompany();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $complianceRecords = $company->compliances()->get();

        return response()->json([
            'compliance_records' => $complianceRecords
        ]);
    }

    public function applyStandard(Request $request): JsonResponse
    {
        $request->validate([
            'regulation' => 'required|string',
            'compliance_date' => 'required|date',
            'notes' => 'nullable|string'
        ]);

        $company = $this->getCompany();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $compliance = Compliance::updateOrCreate(
            [
                'company_id' => $company->id,
                'regulation' => $request->regulation
            ],
            [
                'status' => 'pending',
                'compliance_date' => $request->compliance_date,
                'notes' => $request->notes
            ]
        );

        return response()->json([
            'message' => 'Accounting standard applied successfully',
            'compliance_record' => $compliance
        ]);
    }

    public function checkCompliance(): JsonResponse
    {
        $company = $this->getCompany();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $complianceResults = $company->compliances()->get()->map(function ($compliance) {
            return [
                'regulation' => $compliance->regulation,
                'status' => $compliance->status,
                'is_compliant' => $compliance->isCompliant(),
                'is_overdue' => $compliance->isOverdue(),
                'days_until_due' => $compliance->daysUntilDue(),
                'compliance_date' => $compliance->compliance_date,
                'notes' => $compliance->notes
            ];
        });

        return response()->json([
            'compliance_results' => $complianceResults
        ]);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
