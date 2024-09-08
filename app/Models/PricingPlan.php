<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'monthly_cost',
        'yearly_cost',
        'currency',
        'features',
        'short_code',
        'category',
        'stripe_id',
        'is_custom',
        'description',
        'unit_label',
        'is_freemium',
    ];

    public function setMonthlyCostAttribute($value)
    {
        $this->attributes['monthly_cost'] = $value * 100;
    }

    public function setYearlyCostAttribute($value)
    {
        $this->attributes['yearly_cost'] = $value * 100;
    }

    public function getFeaturesAttribute($value): array
    {
        return explode(',', $value);
    }
    public function setFeaturesAttribute($value)
    {
        $this->attributes['features'] = implode(',', $value);
    }

    public function featuresList(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features', 'plan_id', 'feature_id')
            ->withPivot('usage_credit');
    }

    public function subFeatures(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(SubFeature::class, 'plan_sub_features', 'plan_id', 'sub_feature_id');
    }

    public function affiliatePlans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AffiliatePlan::class);
    }

    public function venueLeadInfo(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(VenueLeadInfo::class);
    }

    public function pricingPlanPrices(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(PricingPlanPrice::class);
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
