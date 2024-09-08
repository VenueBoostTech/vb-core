<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'type', 'amount', 'date', 'status'
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
