<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Expense;
use Illuminate\Console\Command;
use stdClass;

class IntegrateExpensesWithPayroll extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:integrate-expenses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Integrate approved expenses with payroll processing';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function handle()
    {
        // Get the current payroll period
        $payrollPeriod = new StdClass;
        $payrollPeriod->start_date = '2019-01-01';
        $payrollPeriod->end_date = '2019-01-31';


        // Retrieve all approved expenses within the current payroll period
        $expenses = Expense::where('status', 'approved')
            ->whereBetween('date', [$payrollPeriod->start_date, $payrollPeriod->end_date])
            ->get();

        if ($expenses->isEmpty()) {
            $this->info('No expenses to integrate with payroll processing');
            return;
        }

        // Calculate the total expense amount
        $expenseTotal = $expenses->sum('amount');

        // Update the relevant employee's payroll record with the expense amount
        $employee = Employee::find($expenses->first()->employee_id);
        $employee->payroll->expense_amount = $expenseTotal;
        $employee->payroll->save();

        $this->info('Expenses have been successfully integrated with payroll processing for employee id: '.$employee->id);
    }
}
