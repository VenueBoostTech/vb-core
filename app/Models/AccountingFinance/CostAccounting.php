<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostAccounting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'cost_object',
        'direct_costs',
        'indirect_costs',
        'accounting_date'
    ];

    protected $casts = [
        'direct_costs' => 'decimal:2',
        'indirect_costs' => 'decimal:2',
        'accounting_date' => 'date',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Scopes
    public function scopeByCostObject($query, $costObject)
    {
        return $query->where('cost_object', $costObject);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('accounting_date', [$startDate, $endDate]);
    }

    // Methods
    public function getTotalCosts()
    {
        return $this->direct_costs + $this->indirect_costs;
    }

    public function getDirectCostPercentage()
    {
        $totalCosts = $this->getTotalCosts();
        return $totalCosts > 0 ? ($this->direct_costs / $totalCosts) * 100 : 0;
    }

    public function getIndirectCostPercentage()
    {
        $totalCosts = $this->getTotalCosts();
        return $totalCosts > 0 ? ($this->indirect_costs / $totalCosts) * 100 : 0;
    }

    public static function getTotalCostsByObject($costObject, $startDate, $endDate)
    {
        return self::byCostObject($costObject)
            ->byDateRange($startDate, $endDate)
            ->selectRaw('SUM(direct_costs + indirect_costs) as total_costs')
            ->value('total_costs');
    }

    public static function getAverageCostsByObject($costObject, $startDate, $endDate)
    {
        return self::byCostObject($costObject)
            ->byDateRange($startDate, $endDate)
            ->selectRaw('AVG(direct_costs + indirect_costs) as average_costs')
            ->value('average_costs');
    }
}
