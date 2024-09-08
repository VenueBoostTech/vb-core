<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'value',
        'acquisition_date',
        'depreciation_method',
        'useful_life',
        'salvage_value',
        'current_value',
        'status'
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'value' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'current_value' => 'decimal:2',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function depreciationEntries()
    {
        return $this->hasMany(DepreciationEntry::class);
    }

    // Methods
    public function calculateDepreciation()
    {
        // Implementation depends on the depreciation method
        // This is a placeholder for the actual calculation
        if ($this->depreciation_method === 'straight_line') {
            return ($this->value - $this->salvage_value) / $this->useful_life;
        }
        // Add other depreciation methods as needed
        return 0;
    }

    public function updateCurrentValue()
    {
        $totalDepreciation = $this->depreciationEntries()->sum('amount');
        $this->current_value = $this->value - $totalDepreciation;
        $this->save();
    }

    public function isFullyDepreciated()
    {
        return $this->current_value <= $this->salvage_value;
    }

    // Scopes for querying
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
