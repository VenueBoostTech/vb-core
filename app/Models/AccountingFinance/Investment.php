<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Investment extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'type',
        'amount',
        'investment_date',
        'expected_return'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'investment_date' => 'date',
        'expected_return' => 'decimal:2',
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
