<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountingFinance\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class RiskManagementController extends Controller
{
    public function getRiskAssessment(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $risks = $company->risks()->get();
        return response()->json($risks);
    }

    public function mitigateRisk(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'risk_id' => 'required|exists:risks,id',
            'mitigation_strategy' => 'required|string',
        ]);

        $risk = $company->risks()->findOrFail($validatedData['risk_id']);
        $risk->update(['mitigation_strategy' => $validatedData['mitigation_strategy']]);
        return response()->json($risk);
    }

    public function getExposure(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $highRisks = $company->risks()->where('impact', '>=', 4)->where('likelihood', '>=', 4)->get();
        return response()->json($highRisks);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
