<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Company;
use App\Models\AccountingFinance\IntercompanyTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class IntercompanyAccountingController extends Controller
{

    public function createDummyTransactions(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $request->validate([
            'number_of_transactions' => 'required|integer|min:1|max:100',
        ]);

        $numberOfTransactions = $request->input('number_of_transactions');

        DB::beginTransaction();

        try {
            // Ensure we have at least 3 companies (including the current one)
            $this->ensureMinimumCompanies();

            // Get all company IDs except the current company
            $otherCompanyIds = Company::where('id', '!=', $company->id)->pluck('id')->toArray();

            $createdTransactions = [];

            for ($i = 0; $i < $numberOfTransactions; $i++) {
                $isFromCurrentCompany = rand(0, 1) == 1;
                $fromCompanyId = $isFromCurrentCompany ? $company->id : $otherCompanyIds[array_rand($otherCompanyIds)];
                $toCompanyId = $isFromCurrentCompany ? $otherCompanyIds[array_rand($otherCompanyIds)] : $company->id;

                $transaction = IntercompanyTransaction::create([
                    'from_company_id' => $fromCompanyId,
                    'to_company_id' => $toCompanyId,
                    'amount' => rand(1000, 1000000) / 100, // Random amount between 10.00 and 10,000.00
                    'transaction_date' => now()->subDays(rand(0, 365)), // Random date within the last year
                    'description' => 'Dummy transaction ' . ($i + 1),
                ]);

                $createdTransactions[] = $transaction;
            }

            DB::commit();

            return response()->json([
                'message' => "$numberOfTransactions dummy transactions created successfully",
                'transactions' => $createdTransactions
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create dummy transactions: ' . $e->getMessage()], 500);
        }
    }

    private function ensureMinimumCompanies()
    {
        $companyCount = Company::count();
        $companiesToCreate = max(0, 3 - $companyCount);

        for ($i = 0; $i < $companiesToCreate; $i++) {
            Company::create([
                'name' => 'Dummy Company ' . ($i + 1),
                'user_id' => Auth::id(), // Assuming all companies are owned by the current user for simplicity
                // Add any other required fields for your Company model
            ]);
        }
    }

    public function getTransactions(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $transactions = IntercompanyTransaction::where('from_company_id', $company->id)
            ->orWhere('to_company_id', $company->id)
            ->with(['fromCompany', 'toCompany'])
            ->get();

        return response()->json($transactions);
    }

    public function reconcileTransactions(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'exists:intercompany_transactions,id'
        ]);

        // In a real-world scenario, you would implement the reconciliation logic here
        // For this example, we'll just mark the transactions as reconciled

        $transactions = IntercompanyTransaction::whereIn('id', $validatedData['transaction_ids'])
            ->where(function ($query) use ($company) {
                $query->where('from_company_id', $company->id)
                    ->orWhere('to_company_id', $company->id);
            })
            ->update(['reconciled' => true]);

        return response()->json([
            'message' => 'Transactions reconciled successfully',
            'reconciled_count' => $transactions
        ]);
    }

    public function getEliminations(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        // In a real-world scenario, you would implement complex elimination logic here
        // For this example, we'll just return a sum of all intercompany transactions

        $eliminations = IntercompanyTransaction::where('from_company_id', $company->id)
            ->orWhere('to_company_id', $company->id)
            ->sum('amount');

        return response()->json([
            'total_eliminations' => $eliminations
        ]);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
