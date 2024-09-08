<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialStatement extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'type',
        'statement_date',
        'data'
    ];

    protected $casts = [
        'statement_date' => 'date',
        'data' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
