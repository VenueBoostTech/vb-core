<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubFeature extends Model
{
    use HasFactory;

    public $fillable = [
        'name',
        'link',
        'active',
        'is_main_sub_feature',
        'is_function',
        'plan_restriction',
    ];

    public function feature(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_id');
    }

    public function apiUsages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ApiUsageHistory::class, 'sub_feature_id');
    }
}
