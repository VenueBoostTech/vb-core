<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingPlanPrice extends Model
{
    use HasFactory;

    protected $table = 'pricing_plans_prices';

    protected $fillable = [
        'stripe_id',
        'active',
        'billing_scheme',
        'currency',
        'custom_unit_amount',
        'stripe_product_id',
        'pricing_plan_id',
        'recurring',
        'tax_behavior',
        'type',
        'unit_amount',
        'unit_amount_decimal',
        'trial_period_days',
    ];

    protected $casts = [
        'recurring' => 'json',
    ];

    public function pricingPlan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PricingPlan::class, 'pricing_plan_id');
    }

    public function subscriptionItem(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SubscriptionItem::class, 'item_id');
    }

}
