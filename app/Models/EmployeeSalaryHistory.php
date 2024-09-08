<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'salary', 'salary_frequency',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
