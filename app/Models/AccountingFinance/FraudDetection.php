<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FraudDetection extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'detection_method',
        'detection_date',
        'description',
        'potential_impact',
        'status'
    ];

    protected $casts = [
        'detection_date' => 'date',
        'potential_impact' => 'decimal:2',
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
