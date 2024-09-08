<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountingFinance\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class InvestmentManagementController extends Controller
{
    public function getPortfolio(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $investments = $company->investments()->get();
        return response()->json($investments);
    }

    public function executeTrade(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'type' => 'required|string',
            'amount' => 'required|numeric',
            'investment_date' => 'required|date',
            'expected_return' => 'required|numeric',
        ]);

        $trade = $company->investments()->create($validatedData);
        return response()->json($trade, 201);
    }

    public function getPerformance(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $performance = [
            'total_value' => $company->investments()->sum('amount'),
            'total_return' => $company->investments()->sum('expected_return'),
        ];
        return response()->json($performance);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
