<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetVariance extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'category',
        'budgeted_amount',
        'actual_amount',
        'variance',
        'variance_percentage',
        'period_start',
        'period_end',
        'status'
    ];

    protected $casts = [
        'budgeted_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'variance' => 'decimal:2',
        'variance_percentage' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    // Relationships
    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    // Methods
    public function calculateVariance()
    {
        $this->variance = $this->actual_amount - $this->budgeted_amount;
        $this->variance_percentage = $this->budgeted_amount != 0
            ? ($this->variance / $this->budgeted_amount) * 100
            : 0;
        $this->save();
    }

    public function isOverBudget()
    {
        return $this->variance > 0;
    }

    public function isUnderBudget()
    {
        return $this->variance < 0;
    }

    public function getVarianceStatus()
    {
        if ($this->variance == 0) return 'On Budget';
        return $this->isOverBudget() ? 'Over Budget' : 'Under Budget';
    }

    // Scopes for querying
    public function scopeOverBudget($query)
    {
        return $query->where('variance', '>', 0);
    }

    public function scopeUnderBudget($query)
    {
        return $query->where('variance', '<', 0);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByPeriod($query, $start, $end)
    {
        return $query->whereBetween('period_start', [$start, $end]);
    }
}
