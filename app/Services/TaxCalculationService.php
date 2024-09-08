<?php

namespace App\Services;

use App\Models\AccountingFinance\Company;

class TaxCalculationService
{
    public function calculateEstimatedTax(Company $company, float $income, float $deductions, int $year): array
    {
        $taxableIncome = $income - $deductions;

        // This is a simplified progressive tax rate example
        $taxRates = [
            10000 => 0.10,
            30000 => 0.15,
            100000 => 0.25,
            INF => 0.30
        ];

        $taxAmount = 0;
        $remainingIncome = $taxableIncome;

        foreach ($taxRates as $bracket => $rate) {
            if ($remainingIncome > 0) {
                $taxableAtThisRate = min($remainingIncome, $bracket);
                $taxAmount += $taxableAtThisRate * $rate;
                $remainingIncome -= $taxableAtThisRate;
            } else {
                break;
            }
        }

        $effectiveTaxRate = $taxAmount / $taxableIncome;

        return [
            'taxable_income' => $taxableIncome,
            'tax_amount' => $taxAmount,
            'effective_tax_rate' => $effectiveTaxRate
        ];
    }
}
