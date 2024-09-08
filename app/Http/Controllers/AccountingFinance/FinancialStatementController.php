<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountingFinance\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class FinancialStatementController extends Controller
{
    public function getBalanceSheet(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $balanceSheet = $company->financialStatements()
            ->where('type', 'balance_sheet')
            ->latest()
            ->first();
        return response()->json($balanceSheet);
    }

    public function getIncomeStatement(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $incomeStatement = $company->financialStatements()
            ->where('type', 'income_statement')
            ->latest()
            ->first();
        return response()->json($incomeStatement);
    }

    public function getCashFlowStatement(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $cashFlowStatement = $company->financialStatements()
            ->where('type', 'cash_flow')
            ->latest()
            ->first();
        return response()->json($cashFlowStatement);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
