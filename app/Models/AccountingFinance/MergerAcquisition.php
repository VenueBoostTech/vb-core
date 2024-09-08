<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MergerAcquisition extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'target_company',
        'proposed_value',
        'proposed_date',
        'status'
    ];

    protected $casts = [
        'proposed_value' => 'decimal:2',
        'proposed_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
