<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\GeneralLedger;
use Illuminate\Http\Request;
use App\Models\AccountingFinance\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;


class GeneralLedgerController extends Controller
{
    public function index(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $ledgerEntries = $company->generalLedgers()->paginate(15);
        return response()->json($ledgerEntries);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'account_name' => 'required|string',
            'account_type' => 'required|string',
            'balance' => 'required|numeric',
        ]);

        $ledgerEntry = $company->generalLedgers()->create($validatedData);
        return response()->json($ledgerEntry, 201);
    }

    public function show(GeneralLedger $generalLedger): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $generalLedger->company_id !== $company->id) {
            return response()->json(['message' => 'General ledger entry not found'], 404);
        }

        return response()->json($generalLedger);
    }

    public function update(Request $request, GeneralLedger $generalLedger): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $generalLedger->company_id !== $company->id) {
            return response()->json(['message' => 'General ledger entry not found'], 404);
        }

        $validatedData = $request->validate([
            'account_name' => 'string',
            'account_type' => 'string',
            'balance' => 'numeric',
        ]);

        $generalLedger->update($validatedData);
        return response()->json($generalLedger);
    }

    public function destroy(GeneralLedger $generalLedger): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $generalLedger->company_id !== $company->id) {
            return response()->json(['message' => 'General ledger entry not found'], 404);
        }

        $generalLedger->delete();
        return response()->json(null, 204);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }

}
