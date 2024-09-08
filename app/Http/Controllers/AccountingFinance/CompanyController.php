<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountingFinance\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $companies = Company::where('user_id', Auth::id())->get();
        return response()->json($companies);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        // Define validation rules
        $rules = [
            'name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|unique:companies,email',
        ];

        // Create a Validator instance
        $validator = Validator::make($request->all(), $rules);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get the authenticated user
        $user = Auth::user();

        // check if the user is logged in
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Check if the user already has a registered company
        if ($user->company) {
            return response()->json(['message' => 'User already has a registered company'], 400);
        }

        // Create a new company
        $company = new Company();
        $company->name = $request->name;
        $company->tax_id = $request->tax_id;
        $company->address = $request->address;
        $company->phone = $request->phone;
        $company->email = $request->email;
        $company->user_id = $user->id;
        $company->save();

        return response()->json([
            'message' => 'Company registered successfully',
            'company' => $company
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): \Illuminate\Http\JsonResponse
    {
        $company = Company::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
        return response()->json($company);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $company = Company::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'sometimes|required|email|unique:companies,email,' . $company->id,
        ]);

        $company->update($validated);

        return response()->json(['message' => 'Company updated successfully', 'company' => $company]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): \Illuminate\Http\JsonResponse
    {
        $company = Company::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $company->delete();

        return response()->json(['message' => 'Company deleted successfully']);
    }
}
