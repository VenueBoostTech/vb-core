<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Company;
use App\Models\AccountingFinance\Workflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class WorkflowAutomationController extends Controller
{
    public function getWorkflows(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $workflows = $company->workflows()->get();
        return response()->json($workflows);
    }

    public function createWorkflow(Request $request): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validatedData = $request->validate([
            'name' => 'required|string',
            'steps' => 'required|array',
            'status' => 'required|in:active,inactive'
        ]);

        $workflow = $company->workflows()->create($validatedData);
        return response()->json($workflow, 201);
    }

    public function updateWorkflow(Request $request, $id): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $workflow = $company->workflows()->findOrFail($id);

        $validatedData = $request->validate([
            'name' => 'string',
            'steps' => 'array',
            'status' => 'in:active,inactive'
        ]);

        $workflow->update($validatedData);
        return response()->json($workflow);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
