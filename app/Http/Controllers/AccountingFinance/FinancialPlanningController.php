<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class FinancialPlanningController extends Controller
{
    public function getLongTermPlan(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $plan = $company->financialPlans()->where('type', 'long_term')->latest()->first();

        return response()->json($plan);
    }

    public function createScenario(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'name' => 'required|string',
            'description' => 'required|string',
            'assumptions' => 'required|array',
        ]);

        $scenario = $company->financialScenarios()->create($validatedData);

        return response()->json($scenario, 201);
    }

    public function runWhatIfAnalysis(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'scenario_id' => 'required|exists:financial_scenarios,id',
        ]);

        // Logic to run what-if analysis
        $analysis = []; // This should be populated with actual analysis data

        return response()->json($analysis);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
