<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    use HasFactory;

    public $fillable = [
        'category',
        'name',
        'description',
        'monthly_cost',
        'yearly_cost',
        'currency',
        'addon_plan_type',
    ];

    public function addonFeatures(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(AddonFeature::class, 'addon_feature_connections', 'addon_id', 'addon_feature_id');
    }

    public function addonSubFeatures(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(AddonSubFeature::class, 'addon_sub_feature_connections', 'addon_id', 'addon_sub_feature_id');
    }

}
