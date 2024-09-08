<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Company;
use App\Models\AccountingFinance\ProfitCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class ProfitCenterController extends Controller
{
    public function index(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $profitCenters = $company->profitCenters()->get();
        return response()->json($profitCenters);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'name' => 'required|string',
            'manager' => 'required|string',
            'revenue_target' => 'required|numeric|min:0'
        ]);

        $profitCenter = $company->profitCenters()->create($validatedData);

        return response()->json($profitCenter, 201);
    }

    public function show(ProfitCenter $profitCenter): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $profitCenter->company_id !== $company->id) {
            return response()->json(['message' => 'Profit center not found'], 404);
        }

        return response()->json($profitCenter);
    }

    public function update(Request $request, ProfitCenter $profitCenter): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $profitCenter->company_id !== $company->id) {
            return response()->json(['message' => 'Profit center not found'], 404);
        }

        $validatedData = $request->validate([
            'name' => 'string',
            'manager' => 'string',
            'revenue_target' => 'numeric|min:0'
        ]);

        $profitCenter->update($validatedData);

        return response()->json($profitCenter);
    }

    public function destroy(ProfitCenter $profitCenter): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $profitCenter->company_id !== $company->id) {
            return response()->json(['message' => 'Profit center not found'], 404);
        }

        $profitCenter->delete();

        return response()->json(null, 204);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
