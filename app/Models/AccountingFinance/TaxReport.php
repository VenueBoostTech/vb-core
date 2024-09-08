<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxReport extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'report_date', 'report_type', 'content'];

    protected $casts = [
        'report_date' => 'date',
        'content' => 'array', // This will automatically JSON decode the content
    ];

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
