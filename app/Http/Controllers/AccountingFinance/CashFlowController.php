<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountingFinance\Company;
use App\Models\AccountingFinance\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class CashFlowController extends Controller
{
    public function getForecast(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        // Get actual transactions
        $transactions = Transaction::where('company_id', $company->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->get();

        // Calculate cash flow forecast
        $forecast = $this->calculateForecast($transactions, $startDate, $endDate);

        return response()->json(['forecast' => $forecast]);
    }

    public function recordTransaction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'type' => 'required|in:income,expense',
            'description' => 'required|string',
            'transaction_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $transaction = Transaction::create([
            'company_id' => $company->id,
            'amount' => $request->amount,
            'type' => $request->type,
            'description' => $request->description,
            'transaction_date' => $request->transaction_date,
        ]);

        return response()->json(['message' => 'Transaction recorded successfully', 'transaction' => $transaction], 201);
    }

    private function calculateForecast($transactions, $startDate, $endDate)
    {
        $forecast = [];
        $runningBalance = 0;

        for ($date = $startDate; $date <= $endDate; $date->addDay()) {
            $dailyTransactions = $transactions->where('transaction_date', $date->toDateString());

            $income = $dailyTransactions->where('type', 'income')->sum('amount');
            $expense = $dailyTransactions->where('type', 'expense')->sum('amount');

            $runningBalance += $income - $expense;

            $forecast[] = [
                'date' => $date->toDateString(),
                'income' => $income,
                'expense' => $expense,
                'net_cash_flow' => $income - $expense,
                'balance' => $runningBalance,
            ];
        }

        return $forecast;
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
