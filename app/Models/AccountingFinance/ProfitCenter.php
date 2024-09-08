<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfitCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'manager',
        'revenue_target'
    ];

    protected $casts = [
        'revenue_target' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeByManager($query, $manager)
    {
        return $query->where('manager', $manager);
    }
}
