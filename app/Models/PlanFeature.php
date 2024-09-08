<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanFeature extends Model
{
    use HasFactory;

    public $fillable = [
        'plan_id',
        'feature_id',
        'usage_credit',
        'whitelabel_access',
        'allow_vr_ar',
        'used_in_plan',
        'unlimited_usage_credit',
        'feature_level'
    ];
}
