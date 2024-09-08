<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountingFinance\Project;
use App\Models\AccountingFinance\Expense;
use App\Models\AccountingFinance\Revenue;
use App\Models\AccountingFinance\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class ProjectAccountingController extends Controller
{
    public function index(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $projects = Project::where('company_id', $company->id)->get();
        return response()->json(['projects' => $projects]);
    }

    public function createProject(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'budget' => 'required|numeric|min:0',
            'status' => 'required|in:planning,in_progress,completed,on_hold'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $project = Project::create([
            'company_id' => $company->id,
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'budget' => $request->budget,
            'status' => $request->status
        ]);

        return response()->json(['message' => 'Project created successfully', 'project' => $project], 201);
    }

    public function getProjectReport(Project $project): JsonResponse
    {
        if ($project->company_id !== $this->getCompany()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $expenses = Expense::where('company_id', $project->company_id)
            ->whereBetween('expense_date', [$project->start_date, $project->end_date])
            ->get();

        $revenues = Revenue::where('project_id', $project->id)->get();

        $totalRevenue = $revenues->sum('amount');
        $totalExpenses = $expenses->sum('amount');

        return response()->json([
            'project' => $project,
            'expenses' => $expenses,
            'revenues' => $revenues,
            'total_expenses' => $totalExpenses,
            'total_revenue' => $totalRevenue,
            'profit' => $totalRevenue - $totalExpenses,
            'budget_remaining' => $project->budget - $totalExpenses,
        ]);
    }

    public function getProjectProfitability(Project $project): JsonResponse
    {
        if ($project->company_id !== $this->getCompany()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $expenses = Expense::where('company_id', $project->company_id)
            ->whereBetween('expense_date', [$project->start_date, $project->end_date])
            ->sum('amount');

        $revenue = Revenue::where('project_id', $project->id)->sum('amount');

        $profitability = [
            'total_revenue' => $revenue,
            'total_expenses' => $expenses,
            'profit' => $revenue - $expenses,
            'profit_margin' => $revenue > 0 ? (($revenue - $expenses) / $revenue) * 100 : 0,
            'roi' => $expenses > 0 ? (($revenue - $expenses) / $expenses) * 100 : 0,
        ];

        return response()->json(['profitability' => $profitability]);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
