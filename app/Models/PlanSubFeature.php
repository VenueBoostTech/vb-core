<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanSubFeature extends Model
{
    use HasFactory;

    public $fillable = [
        'plan_id',
        'sub_feature_id',
    ];
}
