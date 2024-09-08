<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Risk extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'risk_type',
        'description',
        'impact',
        'likelihood',
        'mitigation_strategy'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function getRiskScore()
    {
        return $this->impact * $this->likelihood;
    }

    public function scopeHighRisk($query)
    {
        return $query->where('impact', '>=', 4)->where('likelihood', '>=', 4);
    }
}
