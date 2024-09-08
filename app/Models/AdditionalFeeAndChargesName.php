<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalFeeAndChargesName extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function additionalFeeAndCharges(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AdditionalFeeAndCharge::class);
    }
}
