<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'venue_id',
        'pricing_plan_id',
        'pricing_plan_stripe_id',
        'stripe_subscription_id',
        'status',
        'trial_start',
        'trial_end',
        'trial_end_behavior',
        'cancel_at_period_end',
        'automatic_tax_enabled',
        'billing_cycle_anchor',
        'billing_thresholds',
        'cancel_at',
        'canceled_at',
        'cancellation_details',
        'collection_method',
        'created_at',
        'currency',
        'current_period_start',
        'current_period_end',
        'requested_custom',
        'pause_collection'
    ];


    protected $casts = [
        'pause_collection' => 'array',
        'billing_thresholds' => 'array',
        'cancellation_details' => 'array',

    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function pricingPlan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PricingPlan::class);
    }

    public function subscriptionItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }
}
