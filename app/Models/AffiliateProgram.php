<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateProgram extends Model
{
    use HasFactory;

    protected $table = 'affiliate_programs';

    protected $fillable = [
        'name',
        'description',
        'status',
        'commission_fee',
        'ap_unique'
    ];

    public function affiliates(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Affiliate::class);
    }

    public function affiliatePlans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AffiliatePlan::class);
    }

}
