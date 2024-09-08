<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndustryBrandCustomizationElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'industry', 'industry_id', 'element_name', 'default_name', 'description'
    ];

    public function venueBrandProfileCustomizations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VenueBrandProfileCustomization::class, 'element_id');
    }
}
