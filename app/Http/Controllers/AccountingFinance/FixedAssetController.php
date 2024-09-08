<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\FixedAsset;
use App\Models\AccountingFinance\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class FixedAssetController extends Controller
{
    public function index(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $fixedAssets = $company->fixedAssets()->get();
        return response()->json($fixedAssets);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'name' => 'required|string',
            'purchase_price' => 'required|numeric',
            'purchase_date' => 'required|date',
            'useful_life' => 'required|integer',
            'salvage_value' => 'required|numeric'
        ]);

        $fixedAsset = $company->fixedAssets()->create($validatedData);
        return response()->json($fixedAsset, 201);
    }

    public function show(FixedAsset $fixedAsset): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $fixedAsset->company_id !== $company->id) {
            return response()->json(['message' => 'Fixed asset not found'], 404);
        }

        return response()->json($fixedAsset);
    }

    public function update(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $fixedAsset->company_id !== $company->id) {
            return response()->json(['message' => 'Fixed asset not found'], 404);
        }

        $validatedData = $request->validate([
            'name' => 'string',
            'purchase_price' => 'numeric',
            'purchase_date' => 'date',
            'useful_life' => 'integer',
            'salvage_value' => 'numeric'
        ]);

        $fixedAsset->update($validatedData);
        return response()->json($fixedAsset);
    }

    public function destroy(FixedAsset $fixedAsset): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company || $fixedAsset->company_id !== $company->id) {
            return response()->json(['message' => 'Fixed asset not found'], 404);
        }

        $fixedAsset->delete();
        return response()->json(null, 204);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
