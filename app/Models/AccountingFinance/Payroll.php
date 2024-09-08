<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $table = 'af_payrolls';

    protected $fillable = [
        'company_id',
        'employee_id',
        'pay_date',
        'gross_pay',
        'deductions',
        'net_pay'
    ];

    protected $casts = [
        'pay_date' => 'date',
        'gross_pay' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
