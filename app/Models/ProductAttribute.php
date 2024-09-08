<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'name',
        'product_id',
    ];

    public function values(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AttributeValue::class, 'attribute_id');
    }

    public function variations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Variation::class, 'attribute_id');
    }
}
