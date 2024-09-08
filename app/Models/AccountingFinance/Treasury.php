<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Treasury extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'cash_balance',
        'short_term_investments',
        'long_term_investments',
        'report_date'
    ];

    protected $casts = [
        'cash_balance' => 'decimal:2',
        'short_term_investments' => 'decimal:2',
        'long_term_investments' => 'decimal:2',
        'report_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function getTotalAssets()
    {
        return $this->cash_balance + $this->short_term_investments + $this->long_term_investments;
    }
}
