<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    use HasFactory;

    public $fillable = [
        'name',
        'link',
        'active',
        'feature_category',
        'identified_for_plan_name',
        'plan_restriction'
    ];

    public function pricingPlans(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(PricingPlan::class, 'plan_features')
            ->withPivot('usage_credit');
    }

    public function subFeatures(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubFeature::class, 'feature_id');
    }

    public function apiUsages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ApiUsageHistory::class, 'feature_id');
    }

    public function featureUsageCredits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FeatureUsageCredit::class, 'feature_id');
    }
}
