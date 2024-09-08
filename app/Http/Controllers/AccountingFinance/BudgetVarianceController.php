<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Company;
use App\Models\AccountingFinance\Budget;
use App\Models\AccountingFinance\BudgetVariance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class BudgetVarianceController extends Controller
{
    public function getVarianceReport(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }


        $validatedData = $request->validate([
            'budget_id' => 'required|exists:budgets,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date'
        ]);

        $budget = Budget::findOrFail($validatedData['budget_id']);

        $variances = BudgetVariance::where('budget_id', $budget->id)
            ->byPeriod($validatedData['start_date'], $validatedData['end_date'])
            ->get();

        $report = [
            'budget_name' => $budget->name,
            'period' => [
                'start' => $validatedData['start_date'],
                'end' => $validatedData['end_date']
            ],
            'variances' => $variances->map(function ($variance) {
                return [
                    'category' => $variance->category,
                    'budgeted_amount' => $variance->budgeted_amount,
                    'actual_amount' => $variance->actual_amount,
                    'variance' => $variance->variance,
                    'variance_percentage' => $variance->variance_percentage,
                    'status' => $variance->getVarianceStatus()
                ];
            }),
            'total_variance' => $variances->sum('variance'),
            'average_variance_percentage' => $variances->avg('variance_percentage')
        ];

        return response()->json($report);
    }

    public function analyzeVariance(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'budget_id' => 'required|exists:budgets,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'categories' => 'required|array'
        ]);

        $budget = Budget::findOrFail($validatedData['budget_id']);

        $variances = BudgetVariance::where('budget_id', $budget->id)
            ->byPeriod($validatedData['start_date'], $validatedData['end_date'])
            ->whereIn('category', $validatedData['categories'])
            ->get();

        $analysis = [
            'budget_name' => $budget->name,
            'period' => [
                'start' => $validatedData['start_date'],
                'end' => $validatedData['end_date']
            ],
            'categories_analyzed' => $validatedData['categories'],
            'variances' => $variances->map(function ($variance) {
                return [
                    'category' => $variance->category,
                    'variance' => $variance->variance,
                    'variance_percentage' => $variance->variance_percentage,
                    'status' => $variance->getVarianceStatus()
                ];
            }),
            'overall_status' => $this->getOverallStatus($variances),
            'recommendations' => $this->generateRecommendations($variances)
        ];

        return response()->json($analysis);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }

    private function getOverallStatus($variances)
    {
        $totalVariance = $variances->sum('variance');
        if ($totalVariance > 0) {
            return 'Overall Over Budget';
        } elseif ($totalVariance < 0) {
            return 'Overall Under Budget';
        } else {
            return 'On Budget';
        }
    }

    private function generateRecommendations($variances)
    {
        // This is a placeholder. In a real-world scenario, you'd implement more sophisticated logic.
        $recommendations = [];
        foreach ($variances as $variance) {
            if ($variance->isOverBudget()) {
                $recommendations[] = "Consider reducing expenses in the {$variance->category} category.";
            } elseif ($variance->isUnderBudget()) {
                $recommendations[] = "The {$variance->category} category is under budget. Consider reallocating resources if needed.";
            }
        }
        return $recommendations;
    }
}
