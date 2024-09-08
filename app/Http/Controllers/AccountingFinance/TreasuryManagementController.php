<?php

namespace App\Http\Controllers\AccountingFinance;
use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class TreasuryManagementController extends Controller
{
    public function getCashPosition(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $cashPosition = $company->cashAccounts()->sum('balance');

        return response()->json(['cash_position' => $cashPosition]);
    }

    public function makeInvestment(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'amount' => 'required|numeric',
            'type' => 'required|string',
            'duration' => 'required|integer',
            'investment_date' => 'required|date',
            'expected_return' => 'required|numeric'
        ]);

        $investment = $company->investments()->create($validatedData);

        return response()->json($investment, 201);
    }

    public function getLiquidityAnalysis(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Logic to perform liquidity analysis
        $liquidityAnalysis = []; // This should be populated with actual liquidity analysis data

        return response()->json($liquidityAnalysis);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
