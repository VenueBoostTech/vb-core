<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VbStoreProductAttribute extends Model
{
    use HasFactory;

    protected $table = 'vb_store_product_attributes';

    protected $fillable = [
        'product_id', 
        'attribute_id', 
        'venue_id',
        'bybest_id'
    ];

    public function attribute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VbStoreAttribute::class, 'attribute_id');
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'vb_store_product_attributes', 'attribute_id', 'product_id')
            ->withPivot('venue_id')
            ->withTimestamps();
    }

    public function option() {
        return $this->belongsTo(VbStoreAttributeOption::class, 'atribute_id', 'id');
    }

    public function variants(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(VbStoreProductVariant::class, 'vb_store_product_variant_attributes', 'attribute_id', 'variant_id')
            ->withPivot('venue_id')
            ->withTimestamps();
    }
}
