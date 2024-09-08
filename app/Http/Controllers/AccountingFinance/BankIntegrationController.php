<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountingFinance\BankAccount;
use App\Models\AccountingFinance\Transaction;
use App\Models\AccountingFinance\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Stripe\Stripe;
use Stripe\Account;
use Stripe\BankAccount as StripeBankAccount;

class BankIntegrationController extends Controller
{
    public function __construct()
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.key'));
    }

    public function connectBank(Request $request): JsonResponse
    {
        $request->validate([
            'account_holder_name' => 'required|string',
            'account_number' => 'required|string',
            'routing_number' => 'required|string',
            'account_holder_type' => 'required|in:individual,company',
        ]);

        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        try {
            // Create or retrieve a Stripe account for the company
            $stripeAccount = $this->getOrCreateStripeAccount($company);

            // Add the bank account to the Stripe account
            $bankAccount = $stripeAccount->external_accounts->create([
                'external_account' => [
                    'object' => 'bank_account',
                    'country' => 'US', // Adjust based on your requirements
                    'currency' => 'usd', // Adjust based on your requirements
                    'account_holder_name' => $request->account_holder_name,
                    'account_holder_type' => $request->account_holder_type,
                    'routing_number' => $request->routing_number,
                    'account_number' => $request->account_number,
                ],
            ]);

            // Save the bank account details in your database
            $localBankAccount = BankAccount::create([
                'company_id' => $company->id,
                'account_number' => $request->account_number,
                'bank_name' => 'Stripe Connected Bank', // You might want to adjust this
                'stripe_bank_account_id' => $bankAccount->id,
                'balance' => 0, // Initial balance, you might want to fetch this from Stripe
            ]);

            return response()->json([
                'message' => 'Bank account connected successfully',
                'bank_account' => $localBankAccount
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to connect bank account', 'error' => $e->getMessage()], 500);
        }
    }



    public function getTransactions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bank_account_id' => 'required|exists:bank_accounts,id',
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

        $bankAccount = BankAccount::findOrFail($request->bank_account_id);

        try {
            // Fetch payouts for the specific bank account
            $payouts = \Stripe\Payout::all([
                'destination' => $bankAccount->stripe_bank_account_id,
                'created' => [
                    'gte' => strtotime($request->start_date),
                    'lte' => strtotime($request->end_date),
                ],
                'expand' => ['data.balance_transaction'],
            ]);

            $transactions = [];
            foreach ($payouts as $payout) {
                if ($payout->balance_transaction) {
                    $transactions[] = [
                        'id' => $payout->balance_transaction->id,
                        'amount' => $payout->amount / 100, // Convert cents to dollars
                        'currency' => $payout->currency,
                        'description' => $payout->description,
                        'date' => date('Y-m-d H:i:s', $payout->created),
                        'type' => $payout->type,
                        'status' => $payout->status,
                    ];
                }
            }

            return response()->json([
                'transactions' => $transactions
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch transactions', 'error' => $e->getMessage()], 500);
        }
    }

    public function syncTransactions(Request $request): JsonResponse
    {
        $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
        ]);

        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $bankAccount = BankAccount::findOrFail($request->bank_account_id);

        try {
            // Fetch the latest transaction date from your database
            $latestTransaction = Transaction::where('bank_account_id', $bankAccount->id)
                ->latest('transaction_date')
                ->first();

            $startDate = $latestTransaction ? $latestTransaction->transaction_date : now()->subDays(30);

            // Fetch transactions from Stripe
            $stripeTransactions = \Stripe\BalanceTransaction::all([
                'type' => 'bank_account',
                'created' => [
                    'gte' => strtotime($startDate),
                ],
            ]);

            // Sync transactions to your database
            foreach ($stripeTransactions->data as $stripeTransaction) {
                Transaction::updateOrCreate(
                    ['stripe_transaction_id' => $stripeTransaction->id],
                    [
                        'company_id' => $company->id,
                        'bank_account_id' => $bankAccount->id,
                        'amount' => $stripeTransaction->amount / 100, // Convert cents to dollars
                        'type' => $stripeTransaction->amount > 0 ? 'income' : 'expense',
                        'description' => $stripeTransaction->description ?? 'Stripe transaction',
                        'transaction_date' => date('Y-m-d H:i:s', $stripeTransaction->created),
                        'reconciled' => false,
                    ]
                );
            }

            return response()->json([
                'message' => 'Transactions synced successfully',
                'synced_count' => count($stripeTransactions->data)
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to sync transactions', 'error' => $e->getMessage()], 500);
        }
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }

    private function getOrCreateStripeAccount(Company $company)
    {
        if ($company->stripe_account_id) {
            return \Stripe\Account::retrieve($company->stripe_account_id);
        }

        $account = \Stripe\Account::create([
            'type' => 'custom',
            'country' => 'US', // Adjust based on your requirements
            'email' => $company->email,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
        ]);

        $company->update(['stripe_account_id' => $account->id]);

        return $account;
    }
}
