<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountingFinance\CompanyController;
use App\Http\Controllers\AccountingFinance\ForecastingController;
use App\Http\Controllers\AccountingFinance\BudgetingController;
use App\Http\Controllers\AccountingFinance\TaxManagementController;
use App\Http\Controllers\AccountingFinance\BankReconciliationController;
use App\Http\Controllers\AccountingFinance\FinancialAnalyticsController;
use App\Http\Controllers\AccountingFinance\BankIntegrationController;
use App\Http\Controllers\AccountingFinance\AccountingStandardsController;
use App\Http\Controllers\AccountingFinance\AuditTrailController;
use App\Http\Controllers\AccountingFinance\AccountsPayableController;
use App\Http\Controllers\AccountingFinance\AccountsReceivableController;
use App\Http\Controllers\AccountingFinance\FixedAssetController;
use App\Http\Controllers\AccountingFinance\ProjectAccountingController;
use App\Http\Controllers\AccountingFinance\GeneralLedgerController;
use App\Http\Controllers\AccountingFinance\JournalEntryController;
use App\Http\Controllers\AccountingFinance\FinancialStatementController;
use App\Http\Controllers\AccountingFinance\CashFlowController;
use App\Http\Controllers\AccountingFinance\InventoryController;
use App\Http\Controllers\AccountingFinance\PayrollController;
use App\Http\Controllers\AccountingFinance\ExpenseManagementController;
use App\Http\Controllers\AccountingFinance\InvoicingController;
use App\Http\Controllers\AccountingFinance\CurrencyManagementController;
use App\Http\Controllers\AccountingFinance\ReportingController;
use App\Http\Controllers\AccountingFinance\CostAccountingController;
use App\Http\Controllers\AccountingFinance\InvestmentManagementController;
use App\Http\Controllers\AccountingFinance\RiskManagementController;
use App\Http\Controllers\AccountingFinance\ComplianceController;
use App\Http\Controllers\AccountingFinance\BudgetVarianceController;
use App\Http\Controllers\AccountingFinance\WorkflowAutomationController;
use App\Http\Controllers\AccountingFinance\DocumentManagementController;
use App\Http\Controllers\AccountingFinance\FinancialPlanningController;
use App\Http\Controllers\AccountingFinance\AssetManagementController;
use App\Http\Controllers\AccountingFinance\CreditManagementController;
use App\Http\Controllers\AccountingFinance\TreasuryManagementController;
use App\Http\Controllers\AccountingFinance\IntercompanyAccountingController;
use App\Http\Controllers\AccountingFinance\CostCenterController;
use App\Http\Controllers\AccountingFinance\ProfitCenterController;
use App\Http\Controllers\AccountingFinance\MergerAcquisitionController;
use App\Http\Controllers\AccountingFinance\FraudDetectionController;

Route::middleware('auth:api')->group(function () {
    // Worked, api testing done
    Route::apiResource('companies', CompanyController::class);

    // Worked, api testing done
    Route::prefix('forecasting')->group(function () {
        Route::get('/', [ForecastingController::class, 'index']);
        Route::post('generate', [ForecastingController::class, 'generateForecast']);
        Route::get('scenarios', [ForecastingController::class, 'getScenarios']);
        Route::post('scenarios', [ForecastingController::class, 'createScenario']);
    });

    // Worked, api testing done
    Route::prefix('budgeting')->group(function () {
        Route::get('/', [BudgetingController::class, 'index']);
        Route::post('create', [BudgetingController::class, 'createBudget']);
        Route::put('update/{id}', [BudgetingController::class, 'updateBudget']);
        Route::get('variance-analysis', [BudgetingController::class, 'varianceAnalysis']);
    });

    // Worked, api testing done
    Route::prefix('tax-management')->group(function () {
        Route::get('reports', [TaxManagementController::class, 'getReports']);
        Route::post('file', [TaxManagementController::class, 'fileTaxReturn']);
        Route::get('tax-calendar', [TaxManagementController::class, 'getTaxCalendar']);
        Route::post('estimate-tax', [TaxManagementController::class, 'estimateTax']);
        Route::post('generate-report', [TaxManagementController::class, 'generateReport']);
        Route::post('generate-calendar-events', [TaxManagementController::class, 'generateCalendarEvents']);
    });

    // Worked, api testing done
    Route::prefix('bank-reconciliation')->group(function () {
        Route::get('/', [BankReconciliationController::class, 'index']);
        Route::post('reconcile', [BankReconciliationController::class, 'reconcile']);
        Route::get('unreconciled', [BankReconciliationController::class, 'getUnreconciledTransactions']);
        Route::post('create-bank-account', [BankReconciliationController::class, 'createBankAccount']);
        Route::post('create-dummy-transactions', [BankReconciliationController::class, 'createDummyTransactions']);
    });

    // Worked, api testing done
    Route::prefix('analytics')->group(function () {
        Route::get('dashboard', [FinancialAnalyticsController::class, 'getDashboard']);
        Route::get('reports', [FinancialAnalyticsController::class, 'getReports']);
        Route::get('kpis', [FinancialAnalyticsController::class, 'getKPIs']);
        Route::post('custom-report', [FinancialAnalyticsController::class, 'generateCustomReport']);
    });

    // Worked, api testing done
    Route::prefix('bank-integration')->group(function () {
        Route::post('connect', [BankIntegrationController::class, 'connectBank']);
        Route::get('transactions', [BankIntegrationController::class, 'getTransactions']);
        Route::post('sync', [BankIntegrationController::class, 'syncTransactions']);
    });

    // Worked, api testing done
    Route::prefix('accounting-standards')->group(function () {
        Route::get('/', [AccountingStandardsController::class, 'index']);
        Route::post('apply', [AccountingStandardsController::class, 'applyStandard']);
        Route::get('compliance-check', [AccountingStandardsController::class, 'checkCompliance']);
    });

    // Worked, api testing done
    Route::prefix('audit-trail')->group(function () {
        Route::get('/', [AuditTrailController::class, 'index']);
        Route::post('report', [AuditTrailController::class, 'generateReport']);
        Route::get('user-activity', [AuditTrailController::class, 'getUserActivity']);
    });

    // Worked, api testing done
    Route::apiResource('accounts-payable', AccountsPayableController::class);
    // Worked, api testing done
    Route::apiResource('accounts-receivable', AccountsReceivableController::class);
    // Worked, api testing done
    Route::apiResource('fixed-assets', FixedAssetController::class);

    // Worked, api testing done
    Route::prefix('project-accounting')->group(function () {
        Route::get('/', [ProjectAccountingController::class, 'index']);
        Route::post('create', [ProjectAccountingController::class, 'createProject']);
        Route::get('{project}/report', [ProjectAccountingController::class, 'getProjectReport']);
        Route::get('{project}/profitability', [ProjectAccountingController::class, 'getProjectProfitability']);
    });

    // Worked, api testing done
    Route::apiResource('general-ledger', GeneralLedgerController::class);

    // Worked, api testing done
    Route::prefix('journal-entries')->group(function () {
        Route::get('/', [JournalEntryController::class, 'index']);
        Route::post('create', [JournalEntryController::class, 'create']);
        Route::put('update/{id}', [JournalEntryController::class, 'update']);
        Route::delete('delete/{id}', [JournalEntryController::class, 'delete']);
    });

    // Worked, api testing done
    Route::prefix('financial-statements')->group(function () {
        Route::get('balance-sheet', [FinancialStatementController::class, 'getBalanceSheet']);
        Route::get('income-statement', [FinancialStatementController::class, 'getIncomeStatement']);
        Route::get('cash-flow-statement', [FinancialStatementController::class, 'getCashFlowStatement']);
    });

    // Worked, api testing done
    Route::prefix('cash-flow')->group(function () {
        Route::get('forecast', [CashFlowController::class, 'getForecast']);
        Route::post('record-transaction', [CashFlowController::class, 'recordTransaction']);
    });

    // TODO: connect and map with VB
    Route::prefix('inventory')->group(function () {
        Route::get('/', [InventoryController::class, 'index']);
        Route::post('add', [InventoryController::class, 'addItem']);
        Route::put('update/{id}', [InventoryController::class, 'updateItem']);
        Route::get('valuation', [InventoryController::class, 'getInventoryValuation']);
    });

    // TODO: connect and map with VB
    Route::prefix('payroll')->group(function () {
        Route::get('/', [PayrollController::class, 'index']);
        Route::post('run', [PayrollController::class, 'runPayroll']);
        Route::get('tax-filings', [PayrollController::class, 'getTaxFilings']);
    });

    // TODO: add functionality + connect and map with VB
    Route::prefix('expense-management')->group(function () {
        Route::get('/', [ExpenseManagementController::class, 'index']);
        Route::post('submit', [ExpenseManagementController::class, 'submitExpense']);
        Route::put('approve/{id}', [ExpenseManagementController::class, 'approveExpense']);
    });

    // TODO: add functionality + connect and map with VB
    Route::prefix('invoicing')->group(function () {
        Route::get('/', [InvoicingController::class, 'index']);
        Route::post('create', [InvoicingController::class, 'createInvoice']);
        Route::get('overdue', [InvoicingController::class, 'getOverdueInvoices']);
    });

    // Worked, api testing done
    Route::prefix('currency-management')->group(function () {
        Route::get('exchange-rates', [CurrencyManagementController::class, 'getExchangeRates']);
        Route::post('convert', [CurrencyManagementController::class, 'convertCurrency']);
    });

    // Worked, api testing done
    Route::prefix('reporting')->group(function () {
        Route::post('custom', [ReportingController::class, 'generateCustomReport']);
        Route::get('scheduled', [ReportingController::class, 'getScheduledReports']);
        Route::post('schedule', [ReportingController::class, 'scheduleReport']);
    });

    // Worked, api testing done
    Route::prefix('cost-accounting')->group(function () {
        Route::get('/', [CostAccountingController::class, 'index']);
        Route::post('allocate', [CostAccountingController::class, 'allocateCosts']);
        Route::post('analysis', [CostAccountingController::class, 'getCostAnalysis']);
    });

    // Worked, api testing done
    Route::prefix('investment-management')->group(function () {
        Route::get('portfolio', [InvestmentManagementController::class, 'getPortfolio']);
        Route::post('trade', [InvestmentManagementController::class, 'executeTrade']);
        Route::get('performance', [InvestmentManagementController::class, 'getPerformance']);
    });

    // Worked, api testing done
    Route::prefix('risk-management')->group(function () {
        Route::get('assessment', [RiskManagementController::class, 'getRiskAssessment']);
        Route::post('mitigate', [RiskManagementController::class, 'mitigateRisk']);
        Route::get('exposure', [RiskManagementController::class, 'getExposure']);
    });

    // Worked, api testing done
    Route::prefix('compliance')->group(function () {
        Route::get('regulations', [ComplianceController::class, 'getRegulations']);
        Route::post('report', [ComplianceController::class, 'submitComplianceReport']);
        Route::get('audit', [ComplianceController::class, 'initiateAudit']);
    });

    // Worked, api testing done
    Route::prefix('budget-variance')->group(function () {
        Route::get('/', [BudgetVarianceController::class, 'getVarianceReport']);
        Route::post('analyze', [BudgetVarianceController::class, 'analyzeVariance']);
    });

    // Worked, api testing done
    Route::prefix('workflow-automation')->group(function () {
        Route::get('workflows', [WorkflowAutomationController::class, 'getWorkflows']);
        Route::post('create', [WorkflowAutomationController::class, 'createWorkflow']);
        Route::put('update/{id}', [WorkflowAutomationController::class, 'updateWorkflow']);
    });


    // Worked, api testing done
    Route::prefix('document-management')->group(function () {
        Route::get('/', [DocumentManagementController::class, 'index']);
        Route::post('upload', [DocumentManagementController::class, 'uploadDocument']);
        Route::get('search', [DocumentManagementController::class, 'searchDocuments']);
    });

    // Worked, api testing done
    Route::prefix('financial-planning')->group(function () {
        Route::get('long-term', [FinancialPlanningController::class, 'getLongTermPlan']);
        Route::post('create-scenario', [FinancialPlanningController::class, 'createScenario']);
        Route::get('what-if', [FinancialPlanningController::class, 'runWhatIfAnalysis']);
    });

    // Worked, api testing done
    Route::prefix('asset-management')->group(function () {
        Route::get('/', [AssetManagementController::class, 'index']);
        Route::post('depreciate', [AssetManagementController::class, 'depreciate']);
        Route::get('valuation', [AssetManagementController::class, 'getAssetValuation']);
    });

    // Worked, api testing done
    Route::prefix('credit-management')->group(function () {
        Route::get('credit-scores', [CreditManagementController::class, 'getCreditScores']);
        Route::post('set-limit', [CreditManagementController::class, 'setCreditLimit']);
        Route::get('aging-report', [CreditManagementController::class, 'getAgingReport']);
    });

    // Worked, api testing done
    Route::prefix('treasury-management')->group(function () {
        Route::get('cash-position', [TreasuryManagementController::class, 'getCashPosition']);
        Route::post('invest', [TreasuryManagementController::class, 'makeInvestment']);
        Route::get('liquidity', [TreasuryManagementController::class, 'getLiquidityAnalysis']);
    });

    // Worked, api testing done
    Route::prefix('intercompany-accounting')->group(function () {
        Route::get('transactions', [IntercompanyAccountingController::class, 'getTransactions']);
        Route::post('reconcile', [IntercompanyAccountingController::class, 'reconcileTransactions']);
        Route::get('eliminations', [IntercompanyAccountingController::class, 'getEliminations']);
        Route::post('create-dummy-transactions', [IntercompanyAccountingController::class, 'createDummyTransactions']);
    });

    // Worked, api testing done
    Route::apiResource('cost-centers', CostCenterController::class);

    // Worked, api testing done
    Route::apiResource('profit-centers', ProfitCenterController::class);

    // Worked, api testing done
    Route::prefix('merger-acquisition')->group(function () {
        Route::get('due-diligence', [MergerAcquisitionController::class, 'getDueDiligence']);
        Route::post('valuation', [MergerAcquisitionController::class, 'performValuation']);
        Route::get('integration-plan', [MergerAcquisitionController::class, 'getIntegrationPlan']);
    });

    // Worked, api testing done
    Route::prefix('fraud-detection')->group(function () {
        Route::get('anomalies', [FraudDetectionController::class, 'detectAnomalies']);
        Route::post('report', [FraudDetectionController::class, 'reportSuspiciousActivity']);
        Route::get('risk-score', [FraudDetectionController::class, 'getFraudRiskScore']);
    });
});
