<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\AccountsPayable;
use App\Models\AccountingFinance\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class AccountsPayableController extends Controller
{
    public function index(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $accountsPayable = $company->accountsPayables()->get();
        return response()->json($accountsPayable);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'vendor' => 'required|string',
            'amount' => 'required|numeric',
            'due_date' => 'required|date',
            'status' => 'required|string'
        ]);

        $accountPayable = $company->accountsPayables()->create($validatedData);
        return response()->json($accountPayable, 201);
    }

    public function show(AccountsPayable $accountsPayable): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $accountsPayable->company_id !== $company->id) {
            return response()->json(['message' => 'Account payable not found'], 404);
        }

        return response()->json($accountsPayable);
    }

    public function update(Request $request, AccountsPayable $accountsPayable): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $accountsPayable->company_id !== $company->id) {
            return response()->json(['message' => 'Account payable not found'], 404);
        }

        $validatedData = $request->validate([
            'vendor' => 'string',
            'amount' => 'numeric',
            'due_date' => 'date',
            'status' => 'string'
        ]);

        $accountsPayable->update($validatedData);
        return response()->json($accountsPayable);
    }

    public function destroy(AccountsPayable $accountsPayable): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $accountsPayable->company_id !== $company->id) {
            return response()->json(['message' => 'Account payable not found'], 404);
        }

        $accountsPayable->delete();
        return response()->json(null, 204);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
