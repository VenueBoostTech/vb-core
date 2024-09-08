<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'tax_year', 'type', 'total_income',
        'total_deductions', 'total_tax_owed', 'filing_date'
    ];

    protected $casts = [
        'filing_date' => 'date',
    ];

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
