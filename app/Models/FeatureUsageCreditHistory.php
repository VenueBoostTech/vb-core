<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeatureUsageCreditHistory extends Model
{
    use HasFactory;

    protected $table = 'feature_usage_credits_history';

    protected $fillable = [
        'feature_usage_credit_id',
        'transaction_type',
        'amount',
        'used_at_feature',
        'restaurant_referral_id',
        'feature_id',
        'credited_by_discovery_plan_monthly'
    ];

    public function featureUsageCredit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FeatureUsageCredit::class);
    }

    public function restaurantReferral(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RestaurantReferral::class);
    }

    public function feature(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
