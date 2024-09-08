<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\AccountsReceivable;
use App\Models\AccountingFinance\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class AccountsReceivableController extends Controller
{
    public function index(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $accountsReceivable = $company->accountsReceivables()->get();
        return response()->json($accountsReceivable);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'customer' => 'required|string',
            'amount' => 'required|numeric',
            'due_date' => 'required|date',
            'status' => 'required|string'
        ]);

        $accountReceivable = $company->accountsReceivables()->create($validatedData);
        return response()->json($accountReceivable, 201);
    }

    public function show(AccountsReceivable $accountsPayable): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $accountsPayable->company_id !== $company->id) {
            return response()->json(['message' => 'Account receivable not found'], 404);
        }

        return response()->json($accountsPayable);
    }

    public function update(Request $request, AccountsReceivable $accountsPayable): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $accountsPayable->company_id !== $company->id) {
            return response()->json(['message' => 'Account receivable not found'], 404);
        }

        $validatedData = $request->validate([
            'customer' => 'string',
            'amount' => 'numeric',
            'due_date' => 'date',
            'status' => 'string'
        ]);

        $accountsPayable->update($validatedData);
        return response()->json($accountsPayable);
    }

    public function destroy(AccountsReceivable $accountsPayable): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $accountsPayable->company_id !== $company->id) {
            return response()->json(['message' => 'Account receivable not found'], 404);
        }

        $accountsPayable->delete();
        return response()->json(null, 204);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
