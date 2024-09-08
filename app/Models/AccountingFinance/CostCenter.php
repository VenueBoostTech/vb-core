<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'manager',
        'budget'
    ];

    protected $casts = [
        'budget' => 'decimal:2',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    // Scopes
    public function scopeByManager($query, $manager)
    {
        return $query->where('manager', $manager);
    }

    public function scopeByBudgetRange($query, $min, $max)
    {
        return $query->whereBetween('budget', [$min, $max]);
    }

    // Methods
    public function getTotalExpenses()
    {
        return $this->expenses()->sum('amount');
    }

    public function getRemainingBudget()
    {
        return $this->budget - $this->getTotalExpenses();
    }

    public function getBudgetUtilizationPercentage()
    {
        return $this->budget > 0 ? ($this->getTotalExpenses() / $this->budget) * 100 : 0;
    }

    public function isOverBudget()
    {
        return $this->getTotalExpenses() > $this->budget;
    }


    public static function getTopSpendingCenters($limit = 5)
    {
        return self::withSum('expenses as total_expenses', 'amount')
            ->orderByDesc('total_expenses')
            ->limit($limit)
            ->get();
    }

    public static function getLeastSpendingCenters($limit = 5)
    {
        return self::withSum('expenses as total_expenses', 'amount')
            ->orderBy('total_expenses')
            ->limit($limit)
            ->get();
    }
}
