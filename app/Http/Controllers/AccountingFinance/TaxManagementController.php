<?php

namespace App\Http\Controllers\AccountingFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountingFinance\Company;
use App\Models\AccountingFinance\TaxReport;
use App\Models\AccountingFinance\TaxReturn;
use App\Models\AccountingFinance\TaxCalendarEvent;
use App\Services\TaxCalculationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class TaxManagementController extends Controller
{
    protected $taxCalculationService;

    public function __construct(TaxCalculationService $taxCalculationService)
    {
        $this->taxCalculationService = $taxCalculationService;
    }

    /**
     * Get the list of tax reports.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getReports(Request $request): JsonResponse
    {
        $data = $request->all();
        $rules = [
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'per_page' => 'nullable|integer|min:1|max:100'
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $company = Company::where('user_id', Auth::user()->id)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $reports = $company->taxReports()
            ->when($request->year, function($query, $year) {
                return $query->whereYear('report_date', $year);
            })
            ->paginate($request->per_page ?? 15);

        return response()->json($reports);
    }

    /**
     * File a new tax return.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function fileTaxReturn(Request $request): JsonResponse
    {
        $data = $request->all();
        $rules = [
            'tax_year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'type' => 'required|string|in:income,payroll,sales',
            'total_income' => 'required|numeric|min:0',
            'total_deductions' => 'required|numeric|min:0',
            'total_tax_owed' => 'required|numeric|min:0',
            'filing_date' => 'required|date|before_or_equal:today',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $company = Company::where('user_id', Auth::user()->id)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        try {
            $taxReturn = $company->taxReturns()->create($data);

            return response()->json([
                'message' => 'Tax return filed successfully',
                'tax_return' => $taxReturn
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to file tax return', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get the tax calendar events.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTaxCalendar(Request $request): JsonResponse
    {
        $data = $request->all();
        $rules = [
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'per_page' => 'nullable|integer|min:1|max:100'
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $company = Company::where('user_id', Auth::user()->id)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $events = $company->taxCalendarEvents()
            ->when($request->year, function($query, $year) {
                return $query->whereYear('due_date', $year);
            })
            ->orderBy('due_date')
            ->paginate($request->per_page ?? 15);

        return response()->json($events);
    }

    /**
     * Estimate the tax based on provided data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function estimateTax(Request $request): JsonResponse
    {
        $data = $request->all();
        $rules = [
            'estimated_income' => 'required|numeric|min:0',
            'estimated_deductions' => 'required|numeric|min:0',
            'tax_year' => 'required|integer|min:' . date('Y') . '|max:' . (date('Y') + 1),
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $company = Company::where('user_id', Auth::user()->id)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        try {
            $estimatedTax = $this->taxCalculationService->calculateEstimatedTax(
                $company,
                $data['estimated_income'],
                $data['estimated_deductions'],
                $data['tax_year']
            );

            return response()->json([
                'tax_year' => $data['tax_year'],
                'estimated_taxable_income' => $estimatedTax['taxable_income'],
                'estimated_tax' => $estimatedTax['tax_amount'],
                'effective_tax_rate' => $estimatedTax['effective_tax_rate'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to estimate tax', 'error' => $e->getMessage()], 500);
        }
    }

    public function generateReport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'report_date' => 'required|date',
            'report_type' => 'required|string|in:income,payroll,sales',
            'content' => 'required|array',
        ]);

        $company = Company::where('user_id', Auth::user()->id)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        try {
            $taxReport = $company->taxReports()->create([
                'report_date' => $data['report_date'],
                'report_type' => $data['report_type'],
                'content' => json_encode($data['content']), // JSON encode the content
            ]);

            return response()->json([
                'message' => 'Tax report generated successfully',
                'tax_report' => $taxReport
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate tax report', 'error' => $e->getMessage()], 500);
        }
    }

    public function generateCalendarEvents(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 1),
        ]);

        $company = Company::where('user_id', Auth::user()->id)->first();

        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $events = $this->determineCompanyTaxEvents($company, $data['year']);

        try {
            $createdEvents = [];
            foreach ($events as $event) {
                $createdEvents[] = $company->taxCalendarEvents()->create($event);
            }

            return response()->json([
                'message' => 'Tax calendar events generated successfully',
                'events' => $createdEvents
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate tax calendar events', 'error' => $e->getMessage()], 500);
        }
    }

    private function determineCompanyTaxEvents(Company $company, int $year): array
    {
        $events = [];

        // Annual Tax Return (assuming all companies need to file this)
        $events[] = [
            'event_name' => 'Annual Tax Return Due',
            'due_date' => $year . '-04-15',
            'description' => 'Deadline for filing annual tax return'
        ];

        // Quarterly Estimated Tax Payments (for companies that meet certain criteria)
        if ($this->companyNeedsQuarterlyPayments($company)) {
            $events = array_merge($events, $this->getQuarterlyPaymentEvents($year));
        }

        // VAT Returns (if the company is VAT registered)
        if ($company->is_vat_registered) {
            $events = array_merge($events, $this->getVATReturnEvents($year));
        }

        // Payroll Tax (if the company has employees)
        if ($company->has_employees) {
            $events = array_merge($events, $this->getPayrollTaxEvents($year));
        }

        // Add more event types based on company characteristics and local tax laws

        return $events;
    }

    private function companyNeedsQuarterlyPayments(Company $company): bool
    {
        // Logic to determine if the company needs to make quarterly payments
        // This could be based on the company's annual revenue, business type, etc.
        return $company->annual_revenue > 1000000; // Example threshold
    }

    private function getQuarterlyPaymentEvents(int $year): array
    {
        return [
            [
                'event_name' => 'Q1 Estimated Tax Payment',
                'due_date' => $year . '-04-15',
                'description' => 'First quarter estimated tax payment due'
            ],
            [
                'event_name' => 'Q2 Estimated Tax Payment',
                'due_date' => $year . '-06-15',
                'description' => 'Second quarter estimated tax payment due'
            ],
            [
                'event_name' => 'Q3 Estimated Tax Payment',
                'due_date' => $year . '-09-15',
                'description' => 'Third quarter estimated tax payment due'
            ],
            [
                'event_name' => 'Q4 Estimated Tax Payment',
                'due_date' => ($year + 1) . '-01-15',
                'description' => 'Fourth quarter estimated tax payment due'
            ]
        ];
    }

    private function getVATReturnEvents(int $year): array
    {
        $events = [];
        for ($month = 1; $month <= 12; $month++) {
            $events[] = [
                'event_name' => 'VAT Return - ' . date('F', mktime(0, 0, 0, $month, 10)),
                'due_date' => date('Y-m-d', strtotime($year . '-' . $month . '-20')),
                'description' => 'Monthly VAT return due'
            ];
        }
        return $events;
    }

    private function getPayrollTaxEvents(int $year): array
    {
        $events = [];
        for ($month = 1; $month <= 12; $month++) {
            $events[] = [
                'event_name' => 'Payroll Tax - ' . date('F', mktime(0, 0, 0, $month, 10)),
                'due_date' => date('Y-m-d', strtotime($year . '-' . $month . '-15')),
                'description' => 'Monthly payroll tax payment due'
            ];
        }
        return $events;
    }
}
