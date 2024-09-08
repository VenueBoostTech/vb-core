<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'item_id',
        'stripe_subscription_id',
        'stripe_item_id',
        'subscription_item_id',
    ];

    public function subscription(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function pricingPlanPrice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PricingPlanPrice::class, 'item_id');
    }
}
