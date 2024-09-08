<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'type',
        'start_date',
        'end_date',
        'objectives',
        'strategies',
        'financial_projections'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'objectives' => 'array',
        'strategies' => 'array',
        'financial_projections' => 'array'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
