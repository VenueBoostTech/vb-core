<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashFlow extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'transaction_date',
        'amount',
        'type',
        'description'
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    public function scopeInflow($query)
    {
        return $query->where('amount', '>', 0);
    }

    public function scopeOutflow($query)
    {
        return $query->where('amount', '<', 0);
    }

    // Methods
    public function isInflow()
    {
        return $this->amount > 0;
    }

    public function isOutflow()
    {
        return $this->amount < 0;
    }

    public static function getTotalCashFlow($startDate, $endDate)
    {
        return self::whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');
    }

    public static function getNetCashFlow($startDate, $endDate)
    {
        $inflow = self::whereBetween('transaction_date', [$startDate, $endDate])
            ->where('amount', '>', 0)
            ->sum('amount');

        $outflow = self::whereBetween('transaction_date', [$startDate, $endDate])
            ->where('amount', '<', 0)
            ->sum('amount');

        return $inflow + $outflow; // outflow is negative, so we add it
    }
}
