<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Payroll;

class PayrollSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $employees = Employee::all();

        foreach($employees as $employee) {
            $payroll = new Payroll();
            $payroll->employee_id = $employee->id;
            $payroll->salary = $employee->salary;
            $payroll->bonus = rand(100, 1000);
            $payroll->deductions = rand(50, 500);
            $payroll->taxes = rand(50, 500);
            $payroll->net_pay = $payroll->salary + $payroll->bonus - $payroll->deductions - $payroll->taxes;
            $payroll->restaurant_id = 1;
            $payroll->save();
        }
    }
}
