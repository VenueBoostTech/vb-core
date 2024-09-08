<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;


class AssetManagementController extends Controller
{
    public function index(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $assets = $company->assets()->paginate(15);

        return response()->json($assets);
    }

    public function depreciate(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'depreciation_amount' => 'required|numeric',
        ]);

        $asset = $company->assets()->findOrFail($validatedData['asset_id']);
        $asset->current_value -= $validatedData['depreciation_amount'];
        $asset->save();

        return response()->json($asset);
    }

    public function getAssetValuation(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $totalValue = $company->assets()->sum('current_value');

        return response()->json(['total_asset_value' => $totalValue]);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
