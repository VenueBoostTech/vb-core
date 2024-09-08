<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Company;
use App\Models\AccountingFinance\CostCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class CostCenterController extends Controller
{
    public function index(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $costCenters = $company->costCenters()->get()->map(function ($costCenter) {
            return [
                'id' => $costCenter->id,
                'name' => $costCenter->name,
                'manager' => $costCenter->manager,
                'budget' => $costCenter->budget,
                'total_expenses' => $costCenter->getTotalExpenses(),
                'remaining_budget' => $costCenter->getRemainingBudget(),
                'budget_utilization' => $costCenter->getBudgetUtilizationPercentage(),
                'is_over_budget' => $costCenter->isOverBudget()
            ];
        });

        return response()->json([
            'cost_centers' => $costCenters,
            'top_spending_centers' => CostCenter::getTopSpendingCenters(),
            'least_spending_centers' => CostCenter::getLeastSpendingCenters()
        ]);
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
            'budget' => 'required|numeric|min:0'
        ]);

        $costCenter = $company->costCenters()->create($validatedData);

        return response()->json($costCenter, 201);
    }

    public function show(CostCenter $costCenter): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $costCenter->company_id !== $company->id) {
            return response()->json(['message' => 'Cost center not found'], 404);
        }

        return response()->json([
            'id' => $costCenter->id,
            'name' => $costCenter->name,
            'manager' => $costCenter->manager,
            'budget' => $costCenter->budget,
            'total_expenses' => $costCenter->getTotalExpenses(),
            'remaining_budget' => $costCenter->getRemainingBudget(),
            'budget_utilization' => $costCenter->getBudgetUtilizationPercentage(),
            'is_over_budget' => $costCenter->isOverBudget()
        ]);
    }

    public function update(Request $request, CostCenter $costCenter): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $costCenter->company_id !== $company->id) {
            return response()->json(['message' => 'Cost center not found'], 404);
        }

        $validatedData = $request->validate([
            'name' => 'string',
            'manager' => 'string',
            'budget' => 'numeric|min:0'
        ]);

        $costCenter->update($validatedData);

        return response()->json($costCenter);
    }

    public function destroy(CostCenter $costCenter): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $costCenter->company_id !== $company->id) {
            return response()->json(['message' => 'Cost center not found'], 404);
        }

        $costCenter->delete();

        return response()->json(null, 204);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
