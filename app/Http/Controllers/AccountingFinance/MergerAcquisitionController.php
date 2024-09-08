<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\MergerAcquisition;
use App\Models\AccountingFinance\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class MergerAcquisitionController extends Controller
{
    public function getDueDiligence(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $targetCompanyId = $request->input('target_company_id');
        $dueDiligence = $company->mergerAcquisitions()
            ->where('target_company', $targetCompanyId)
            ->first();

        if (!$dueDiligence) {
            return response()->json(['message' => 'Due diligence not found'], 404);
        }

        // Here you would typically include more detailed information
        // This is a simplified example
        return response()->json($dueDiligence);
    }

    public function performValuation(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'target_company' => 'required|exists:companies,id',
            'proposed_value' => 'required|numeric',
            'proposed_date' => 'required|date',
        ]);

        $valuation = $company->mergerAcquisitions()->create($validatedData);

        return response()->json($valuation, 201);
    }

    public function getIntegrationPlan(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $targetCompanyId = $request->input('target_company_id');
        $mergerAcquisition = $company->mergerAcquisitions()
            ->where('target_company', $targetCompanyId)
            ->first();

        if (!$mergerAcquisition) {
            return response()->json(['message' => 'Merger/Acquisition not found'], 404);
        }

        // Here you would typically include more detailed integration plan
        // This is a simplified example
        $integrationPlan = [
            'merger_acquisition_id' => $mergerAcquisition->id,
            'phases' => [
                ['name' => 'Initial Integration', 'duration' => '3 months'],
                ['name' => 'Operational Integration', 'duration' => '6 months'],
            ],
            'key_milestones' => [
                'IT Systems Integration',
                'Human Resources Alignment',
                'Brand Integration',
            ],
        ];

        return response()->json($integrationPlan);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
