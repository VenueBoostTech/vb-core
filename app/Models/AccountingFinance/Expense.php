<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $table = 'af_expenses';

    protected $fillable = [
        'company_id',
        'category',
        'amount',
        'expense_date',
        'description',
        'cost_center_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }


    public function costCenter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('expense_date', [$start, $end]);
    }
}
