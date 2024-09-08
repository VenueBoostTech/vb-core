<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialReport extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'type', 'report_date', 'data'];
    protected $casts = ['data' => 'array'];
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    { return $this->belongsTo(Company::class); }
}
