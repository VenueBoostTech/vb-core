<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountingFinance\JournalEntry;
use App\Models\AccountingFinance\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class JournalEntryController extends Controller
{
    public function index(): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $journalEntries = JournalEntry::where('company_id', $company->id)
            ->orderBy('entry_date', 'desc')
            ->get();

        return response()->json(['journal_entries' => $journalEntries]);
    }

    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entry_date' => 'required|date',
            'description' => 'required|string',
            'entries' => 'required|array',
            'entries.*.account' => 'required|string',
            'entries.*.type' => 'required|in:debit,credit',
            'entries.*.amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $journalEntry = JournalEntry::create([
            'company_id' => $company->id,
            'entry_date' => $request->entry_date,
            'description' => $request->description,
            'entries' => $request->entries,
        ]);

        return response()->json(['message' => 'Journal entry created successfully', 'journal_entry' => $journalEntry], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entry_date' => 'required|date',
            'description' => 'required|string',
            'entries' => 'required|array',
            'entries.*.account' => 'required|string',
            'entries.*.type' => 'required|in:debit,credit',
            'entries.*.amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $journalEntry = JournalEntry::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (!$journalEntry) {
            return response()->json(['message' => 'Journal entry not found'], 404);
        }

        $journalEntry->update([
            'entry_date' => $request->entry_date,
            'description' => $request->description,
            'entries' => $request->entries,
        ]);

        return response()->json(['message' => 'Journal entry updated successfully', 'journal_entry' => $journalEntry]);
    }

    public function delete($id): JsonResponse
    {
        $company = $this->getCompany();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $journalEntry = JournalEntry::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (!$journalEntry) {
            return response()->json(['message' => 'Journal entry not found'], 404);
        }

        $journalEntry->delete();

        return response()->json(['message' => 'Journal entry deleted successfully']);
    }

    private function getCompany()
    {
        return Company::where('user_id', Auth::id())->first();
    }
}
