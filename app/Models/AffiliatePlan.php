<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliatePlan extends Model
{
    use HasFactory;

    protected $table = 'affiliate_plans';

    protected $fillable = [
        'affiliate_id',
        'affiliate_program_id',
        'percentage',
        'nr_of_months',
        'fixed_value',
        'custom_plan_amount',
        'plan_id',
        'lifetime',
        'preferred_method',
        'customer_interval_start',
        'customer_interval_end',
        'plan_name'
    ];

    public function affiliate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }

    public function affiliateProgram(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'affiliate_program_id');
    }

    public function pricingPlan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PricingPlan::class, 'plan_id');
    }

    public function affiliateWalletHistory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AffiliateWalletHistory::class, 'affiliate_plan_id');
    }
}
