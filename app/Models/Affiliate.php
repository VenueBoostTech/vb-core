<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    use HasFactory;

    protected $table = 'affiliates';

    protected $fillable = [
        'first_name',
        'last_name',
        'website',
        'country',
        'registered_type',
        'user_id',
        'affiliate_type_id',
        'affiliate_code'
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function programs(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(AffiliateProgram::class);
    }

    public function affiliatePlans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AffiliatePlan::class);
    }

    public function affiliateType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AffiliateType::class);
    }

    public function venues(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Restaurant::class);
    }

    public function potentialVenueLeads(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PotentialVenueLead::class, 'affiliate_id', 'id');
    }

    public function affiliateWallet(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AffiliateWallet::class);
    }

}
