<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'address', 'phone', 'email', 'tax_id', 'user_id'];
    public function taxReports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TaxReport::class);
    }

    public function taxReturns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TaxReturn::class);
    }

    public function taxCalendarEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TaxCalendarEvent::class);
    }

    public function forecasts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Forecast::class);
    }

    public function budgets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function bankAccounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function financialReports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FinancialReport::class);
    }

    public function accountsPayables(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccountsPayable::class);
    }

    public function accountsReceivables(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccountsReceivable::class);
    }

    public function fixedAssets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FixedAsset::class);
    }

    public function projects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function generalLedgers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GeneralLedger::class);
    }

    public function journalEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function financialStatements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FinancialStatement::class);
    }

    public function cashFlows(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CashFlow::class);
    }

    public function inventories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function payrolls(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function expenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function reports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function costAccountings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CostAccounting::class);
    }

    public function investments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Investment::class);
    }

    public function risks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Risk::class);
    }

    public function compliances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Compliance::class);
    }

    public function budgetVariances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BudgetVariance::class);
    }

    public function workflows(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function financialPlans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FinancialPlan::class);
    }

    public function assets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function credits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Credit::class);
    }

    public function treasuries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Treasury::class);
    }

    public function costCenters(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CostCenter::class);
    }

    public function profitCenters(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProfitCenter::class);
    }

    public function mergerAcquisitions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MergerAcquisition::class);
    }

    public function fraudDetections(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FraudDetection::class);
    }

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function revenues(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Revenue::class);
    }

    public function auditTrails()
    {
        return $this->hasMany(AuditTrail::class);
    }

    public function suspiciousActivities()
    {
        return $this->hasMany(SuspiciousActivity::class);
    }

    public function financialScenarios()
    {
        return $this->hasMany(FinancialScenario::class);
    }

    public function creditScores()
    {
        return $this->hasMany(CreditScore::class);
    }

    public function cashAccounts()
    {
        return $this->hasMany(CashAccount::class);
    }

}
