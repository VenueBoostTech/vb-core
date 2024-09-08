<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateType extends Model
{
    use HasFactory;

    protected $table = 'affiliate_types';

    protected $fillable = ['name', 'description', 'category'];


    public function affiliates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Affiliate::class);
    }

}
