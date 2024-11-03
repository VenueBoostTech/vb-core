<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VtPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'max_cameras',
        'days_of_activity',
        'price_monthly',
        'price_yearly',
        'features',
    ];

    protected $casts = [
        'features' => 'array',
    ];

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VtSubscription::class, 'vt_plan_id');
    }
}
