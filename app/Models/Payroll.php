<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $table = 'payrolls';

    protected $fillable = [
        'salary',
        'bonus',
        'deductions',
        'taxes',
        'net_pay',
        'employee_id',
        'hours_worked',
        'overtime_hours',
        'overtime_pay',
        'month',
        'year',
        'restaurant_id'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
