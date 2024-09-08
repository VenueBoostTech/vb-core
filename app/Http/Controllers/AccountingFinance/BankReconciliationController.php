<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountingFinance\BankAccount;
use App\Models\AccountingFinance\Transaction;
use App\Models\AccountingFinance\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class BankReconciliationController extends Controller
{
    public function index(): JsonResponse
    {
        $company = Company::where('user_id', Auth::id())->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $bankAccounts = BankAccount::where('company_id', $company->id)->get();
        $reconciledTransactions = Transaction::where('company_id', $company->id)
            ->where('reconciled', true)
            ->orderBy('transaction_date', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'bank_accounts' => $bankAccounts,
            'recent_reconciled_transactions' => $reconciledTransactions
        ]);
    }

    public function reconcile(Request $request): JsonResponse
    {
        $request->validate([
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'exists:transactions,id',
            'bank_account_id' => 'required|exists:bank_accounts,id'
        ]);

        $company = Company::where('user_id', Auth::id())->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $bankAccount = BankAccount::findOrFail($request->bank_account_id);

        $transactions = Transaction::whereIn('id', $request->transaction_ids)
            ->where('company_id', $company->id)
            ->where('reconciled', false)
            ->get();

        foreach ($transactions as $transaction) {
            $transaction->reconciled = true;
            $transaction->save();

            // Update bank account balance
            $bankAccount->balance += $transaction->amount;
        }

        $bankAccount->save();

        return response()->json([
            'message' => 'Transactions reconciled successfully',
            'updated_bank_account_balance' => $bankAccount->balance
        ]);
    }

    public function getUnreconciledTransactions(): JsonResponse
    {
        $company = Company::where('user_id', Auth::id())->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $unreconciledTransactions = Transaction::where('company_id', $company->id)
            ->where('reconciled', false)
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json([
            'unreconciled_transactions' => $unreconciledTransactions
        ]);
    }
    public function createBankAccount(Request $request): JsonResponse
    {
        $request->validate([
            'account_number' => 'required|string',
            'bank_name' => 'required|string',
            'balance' => 'required|numeric|min:0',
        ]);

        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $bankAccount = BankAccount::create([
            'company_id' => $company->id,
            'account_number' => $request->account_number,
            'bank_name' => $request->bank_name,
            'balance' => $request->balance,
        ]);

        return response()->json([
            'message' => 'Bank account created successfully',
            'bank_account' => $bankAccount
        ], 201);
    }

    public function createDummyTransactions(Request $request): JsonResponse
    {
        $request->validate([
            'count' => 'required|integer|min:1|max:100',
        ]);

        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $transactions = [];
        for ($i = 0; $i < $request->count; $i++) {
            $transactions[] = [
                'company_id' => $company->id,
                'amount' => rand(-1000, 1000) / 100, // Random amount between -10 and 10
                'type' => rand(0, 1) ? 'income' : 'expense',
                'description' => 'Dummy transaction ' . ($i + 1),
                'transaction_date' => now()->subDays(rand(0, 30)),
                'reconciled' => false,
            ];
        }

        Transaction::insert($transactions);

        return response()->json([
            'message' => $request->count . ' dummy transactions created successfully',
        ], 201);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
