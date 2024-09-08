<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Budget;
use App\Models\AccountingFinance\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BudgetingController extends Controller
{
    /**
     * Display a listing of the budgets.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $companyAuth = Company::where('user_id', Auth::user()->id)->first();

        if (!$companyAuth) {
            return response()->json(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $budgets = Budget::where('company_id', $companyAuth->id)->get();
        return response()->json($budgets);
    }

    /**
     * Store a newly created budget in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function createBudget(Request $request): JsonResponse
    {
        $rules = [
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'category_allocations' => 'nullable|array',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->only(['name', 'amount', 'start_date', 'end_date', 'category_allocations']);

        $companyAuth = Company::where('user_id', Auth::user()->id)->first();

        if (!$companyAuth) {
            return response()->json(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $data['company_id'] = $companyAuth->id;

        $budget = Budget::create($data);

        return response()->json($budget, Response::HTTP_CREATED);
    }

    /**
     * Update the specified budget in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function updateBudget(Request $request, int $id): JsonResponse
    {
        $rules = [
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'category_allocations' => 'nullable|array',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->only(['name', 'amount', 'start_date', 'end_date', 'category_allocations']);

        $companyAuth = Company::where('user_id', Auth::user()->id)->first();

        if (!$companyAuth) {
            return response()->json(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $budget = Budget::where('company_id', $companyAuth->id)->findOrFail($id);
        $budget->update($data);

        return response()->json($budget);
    }

    /**
     * Perform variance analysis for budgets.
     *
     * @return JsonResponse
     */
    public function varianceAnalysis(): JsonResponse
    {
        $companyAuth = Company::where('user_id', Auth::user()->id)->first();

        if (!$companyAuth) {
            return response()->json(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $budgets = Budget::where('company_id', $companyAuth->id)->get();

        $analysis = $budgets->map(function ($budget) {
            return [
                'budget' => $budget->name,
                'amount' => $budget->amount,
                'variance' => $budget->amount - ($budget->category_allocations ? array_sum($budget->category_allocations) : 0)
            ];
        });

        return response()->json($analysis);
    }
}
