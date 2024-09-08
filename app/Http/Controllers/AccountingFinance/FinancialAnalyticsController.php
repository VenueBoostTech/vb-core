<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Transaction;
use App\Models\AccountingFinance\BankAccount;
use App\Models\AccountingFinance\AccountsPayable;
use App\Models\AccountingFinance\AccountsReceivable;
use App\Models\AccountingFinance\Invoice;
use App\Models\AccountingFinance\Expense;
use App\Models\AccountingFinance\Revenue;
use App\Models\AccountingFinance\ProfitCenter;
use App\Models\AccountingFinance\Company;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class FinancialAnalyticsController extends Controller
{
    public function getDashboard(Request $request): JsonResponse
    {
        $company = $this->getCompany();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $startDate = $request->input('start_date', Carbon::now()->startOfYear());
        $endDate = $request->input('end_date', Carbon::now());

        $data = [
            'total_revenue' => $this->getRevenue($company->id, $startDate, $endDate),
            'total_expenses' => $this->getExpenses($company->id, $startDate, $endDate),
            'net_profit_loss' => $this->calculateNetProfit($company->id, $startDate, $endDate),
            'cash_flow' => $this->calculateCashFlow($company->id, $startDate, $endDate),
            'top_revenue_sources' => $this->getTopRevenueSources($company->id, $startDate, $endDate),
            'top_expense_categories' => $this->getTopExpenseCategories($company->id, $startDate, $endDate),
        ];

        return response()->json($data);
    }

    public function getReports(Request $request): JsonResponse
    {
        $company = $this->getCompany();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $startDate = $request->input('start_date', Carbon::now()->startOfYear());
        $endDate = $request->input('end_date', Carbon::now());

        $data = [
            'income_statement' => $this->generateIncomeStatement($company->id, $startDate, $endDate),
            'balance_sheet' => $this->generateBalanceSheet($company->id, $endDate),
            'cash_flow_statement' => $this->generateCashFlowStatement($company->id, $startDate, $endDate),
            'accounts_receivable_aging' => $this->generateAccountsReceivableAging($company->id, $endDate),
            'accounts_payable_aging' => $this->generateAccountsPayableAging($company->id, $endDate),
        ];

        return response()->json($data);
    }

    public function getKPIs(Request $request): JsonResponse
    {
        $company = $this->getCompany();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $startDate = $request->input('start_date', Carbon::now()->startOfYear());
        $endDate = $request->input('end_date', Carbon::now());

        $data = [
            'gross_profit_margin' => $this->calculateGrossProfitMargin($company->id, $startDate, $endDate),
            'net_profit_margin' => $this->calculateNetProfitMargin($company->id, $startDate, $endDate),
            'current_ratio' => $this->calculateCurrentRatio($company->id, $endDate),
            'debt_to_equity_ratio' => $this->calculateDebtToEquityRatio($company->id, $endDate),
            'accounts_receivable_turnover' => $this->calculateAccountsReceivableTurnover($company->id, $startDate, $endDate),
        ];

        return response()->json($data);
    }

    public function generateCustomReport(Request $request): JsonResponse
    {
        $company = $this->getCompany();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'metrics' => 'required|array',
            'grouping' => 'required|in:month,quarter,year',
            'comparison_period' => 'nullable|in:previous_year,previous_quarter',
        ]);

        // Implementation of custom report generation based on request parameters
        // This would involve querying the database based on the selected metrics,
        // grouping the results, and applying any comparisons

        // For brevity, we'll return a placeholder response
        return response()->json([
            'message' => 'Custom report generated successfully',
            'parameters' => $request->all(),
            // Actual report data would be included here
        ]);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }

    private function getRevenue($companyId, $startDate, $endDate)
    {
        return Revenue::where('company_id', $companyId)
            ->whereBetween('revenue_date', [$startDate, $endDate])
            ->sum('amount');
    }

    private function getExpenses($companyId, $startDate, $endDate)
    {
        return Expense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');
    }

    private function calculateNetProfit($companyId, $startDate, $endDate)
    {
        $revenue = $this->getRevenue($companyId, $startDate, $endDate);
        $expenses = $this->getExpenses($companyId, $startDate, $endDate);
        return $revenue - $expenses;
    }

    private function calculateCashFlow($companyId, $startDate, $endDate)
    {
        $inflows = Transaction::where('company_id', $companyId)
            ->where('type', 'credit')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');
        $outflows = Transaction::where('company_id', $companyId)
            ->where('type', 'debit')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');
        return $inflows - $outflows;
    }

    private function getTopRevenueSources($companyId, $startDate, $endDate)
    {
        return Revenue::where('company_id', $companyId)
            ->whereBetween('revenue_date', [$startDate, $endDate])
            ->select('project_id', \DB::raw('SUM(amount) as total'))
            ->groupBy('project_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
    }

    private function getTopExpenseCategories($companyId, $startDate, $endDate)
    {
        return Expense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->select('category', \DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
    }

    private function generateIncomeStatement($companyId, $startDate, $endDate)
    {
        $revenue = $this->getRevenue($companyId, $startDate, $endDate);
        $expenses = $this->getExpenses($companyId, $startDate, $endDate);
        return [
            'total_revenue' => $revenue,
            'total_expenses' => $expenses,
            'net_income' => $revenue - $expenses,
        ];
    }

    private function generateBalanceSheet($companyId, $endDate)
    {
        $assets = BankAccount::where('company_id', $companyId)->sum('balance');
        $liabilities = AccountsPayable::where('company_id', $companyId)
            ->where('due_date', '<=', $endDate)
            ->sum('amount');
        $equity = $assets - $liabilities;
        return [
            'total_assets' => $assets,
            'total_liabilities' => $liabilities,
            'total_equity' => $equity,
        ];
    }

    private function generateCashFlowStatement($companyId, $startDate, $endDate)
    {
        return [
            'operating_cash_flow' => $this->calculateCashFlow($companyId, $startDate, $endDate),
            'investing_cash_flow' => 0, // Placeholder
            'financing_cash_flow' => 0, // Placeholder
        ];
    }

    private function generateAccountsReceivableAging($companyId, $endDate)
    {
        return AccountsReceivable::where('company_id', $companyId)
            ->where('due_date', '<=', $endDate)
            ->selectRaw('
                SUM(CASE WHEN due_date >= ? THEN amount ELSE 0 END) as current,
                SUM(CASE WHEN due_date < ? AND due_date >= ? THEN amount ELSE 0 END) as days_1_30,
                SUM(CASE WHEN due_date < ? AND due_date >= ? THEN amount ELSE 0 END) as days_31_60,
                SUM(CASE WHEN due_date < ? AND due_date >= ? THEN amount ELSE 0 END) as days_61_90,
                SUM(CASE WHEN due_date < ? THEN amount ELSE 0 END) as days_over_90
            ', [
                $endDate,
                $endDate, $endDate->copy()->subDays(30),
                $endDate->copy()->subDays(30), $endDate->copy()->subDays(60),
                $endDate->copy()->subDays(60), $endDate->copy()->subDays(90),
                $endDate->copy()->subDays(90)
            ])->first();
    }

    private function generateAccountsPayableAging($companyId, $endDate)
    {
        return AccountsPayable::where('company_id', $companyId)
            ->where('due_date', '<=', $endDate)
            ->selectRaw('
                SUM(CASE WHEN due_date >= ? THEN amount ELSE 0 END) as current,
                SUM(CASE WHEN due_date < ? AND due_date >= ? THEN amount ELSE 0 END) as days_1_30,
                SUM(CASE WHEN due_date < ? AND due_date >= ? THEN amount ELSE 0 END) as days_31_60,
                SUM(CASE WHEN due_date < ? AND due_date >= ? THEN amount ELSE 0 END) as days_61_90,
                SUM(CASE WHEN due_date < ? THEN amount ELSE 0 END) as days_over_90
            ', [
                $endDate,
                $endDate, $endDate->copy()->subDays(30),
                $endDate->copy()->subDays(30), $endDate->copy()->subDays(60),
                $endDate->copy()->subDays(60), $endDate->copy()->subDays(90),
                $endDate->copy()->subDays(90)
            ])->first();
    }

    private function calculateGrossProfitMargin($companyId, $startDate, $endDate)
    {
        $revenue = $this->getRevenue($companyId, $startDate, $endDate);
        $expenses = $this->getExpenses($companyId, $startDate, $endDate);
        return $revenue > 0 ? ($revenue - $expenses) / $revenue * 100 : 0;
    }

    private function calculateNetProfitMargin($companyId, $startDate, $endDate)
    {
        $revenue = $this->getRevenue($companyId, $startDate, $endDate);
        $expenses = $this->getExpenses($companyId, $startDate, $endDate);
        return $revenue > 0 ? ($revenue - $expenses) / $revenue * 100 : 0;
    }

    private function calculateCurrentRatio($companyId, $endDate)
    {
        $currentAssets = BankAccount::where('company_id', $companyId)->sum('balance') +
            AccountsReceivable::where('company_id', $companyId)
                ->where('due_date', '<=', $endDate)
                ->sum('amount');
        $currentLiabilities = AccountsPayable::where('company_id', $companyId)
            ->where('due_date', '<=', $endDate)
            ->sum('amount');
        return $currentLiabilities > 0 ? $currentAssets / $currentLiabilities : 0;
    }

    private function calculateDebtToEquityRatio($companyId, $endDate)
    {
        $totalLiabilities = AccountsPayable::where('company_id', $companyId)
            ->where('due_date', '<=', $endDate)
            ->sum('amount');
        $totalEquity = BankAccount::where('company_id', $companyId)->sum('balance') -
            AccountsPayable::where('company_id', $companyId)
                ->where('due_date', '<=', $endDate)
                ->sum('amount');
        return $totalEquity != 0 ? $totalLiabilities / $totalEquity : 0;
    }

    private function calculateAccountsReceivableTurnover($companyId, $startDate, $endDate)
    {
        $netCreditSales = $this->getRevenue($companyId, $startDate, $endDate);
        $averageAccountsReceivable = AccountsReceivable::where('company_id', $companyId)
            ->whereBetween('due_date', [$startDate, $endDate])
            ->avg('amount');
        return $averageAccountsReceivable > 0 ? $netCreditSales / $averageAccountsReceivable : 0;
    }
}
