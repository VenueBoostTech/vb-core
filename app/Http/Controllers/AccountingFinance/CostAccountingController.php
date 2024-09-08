<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountingFinance\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class CostAccountingController extends Controller
{
    public function index(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $costAccounting = $company->costAccountings()->paginate(15);
        return response()->json($costAccounting);
    }

    public function allocateCosts(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'cost_object' => 'required|string',
            'direct_costs' => 'required|numeric',
            'indirect_costs' => 'required|numeric',
            'accounting_date' => 'required|date',
        ]);

        $costAllocation = $company->costAccountings()->create($validatedData);
        return response()->json($costAllocation, 201);
    }

    public function getCostAnalysis(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'cost_object' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $costObject = $validatedData['cost_object'];
        $startDate = $validatedData['start_date'];
        $endDate = $validatedData['end_date'];

        $totalCosts = $company->costAccountings()
            ->where('cost_object', $costObject)
            ->whereBetween('accounting_date', [$startDate, $endDate])
            ->sum(\DB::raw('direct_costs + indirect_costs'));

        $averageCosts = $company->costAccountings()
            ->where('cost_object', $costObject)
            ->whereBetween('accounting_date', [$startDate, $endDate])
            ->avg(\DB::raw('direct_costs + indirect_costs'));

        return response()->json([
            'cost_object' => $costObject,
            'total_costs' => $totalCosts,
            'average_costs' => $averageCosts,
        ]);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
