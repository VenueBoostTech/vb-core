<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class CreditManagementController extends Controller
{
    public function getCreditScores(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $creditScores = $company->creditScores()->get();

        return response()->json($creditScores);
    }

    public function setCreditLimit(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'credit_limit' => 'required|numeric',
        ]);

        $customer = $company->customers()->findOrFail($validatedData['customer_id']);
        $customer->credit_limit = $validatedData['credit_limit'];
        $customer->save();

        return response()->json($customer);
    }

    public function getAgingReport(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // Logic to generate aging report
        $agingReport = []; // This should be populated with actual aging report data

        return response()->json($agingReport);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
