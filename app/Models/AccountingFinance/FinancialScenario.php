<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialScenario extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'assumptions'
    ];

    protected $casts = [
        'assumptions' => 'array'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
